<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class ProductController extends Controller
{
    private function authorizeAdmin(Request $request): void
    {
        abort_unless(
            $request->user()?->is_admin,
            403,
            'Admin access is required.'
        );
    }

    private function validatedData(
        Request $request,
        bool $updating = false
    ): array {
        $required = $updating
            ? ['sometimes', 'required']
            : ['required'];

        return $request->validate([
            'name' => [
                ...$required,
                'string',
                'max:255',
            ],
            'price' => [
                ...$required,
                'numeric',
                'decimal:0,2',
                'min:0',
                'max:9999999999.99',
            ],
            'stock' => [
                ...$required,
                'integer',
                'min:0',
                'max:4294967295',
            ],
            'category' => [
                ...$required,
                'string',
                'max:255',
            ],
            'subtitle' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
            'description' => [
                'sometimes',
                'nullable',
                'string',
            ],
            'dimensions' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
            'whats_included' => [
                'sometimes',
                'nullable',
                'string',
                'max:10000',
            ],
            'image_url' => [
                'sometimes',
                'nullable',
                'url:http,https',
                'max:2048',
            ],
            'images' => [
                'sometimes',
                'nullable',
                'array',
                'max:10',
            ],
            'images.*' => [
                'required',
                'url:http,https',
                'max:2048',
            ],
            'retained_images' => [
                'sometimes',
                'required',
                'json',
                'max:30000',
            ],
            'image_files' => [
                'sometimes',
                'array',
                'max:10',
            ],
            'image_files.*' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:4096',
            ],
        ], [
            'required' => 'This field is required.',
            'name.string' => 'Please enter a valid product name.',
            'name.max' => 'The name must not exceed 255 characters.',
            'price.numeric' => 'Please enter a valid price.',
            'price.decimal' => 'Use no more than two decimal places.',
            'price.min' => 'The price must be zero or greater.',
            'price.max' => 'The price must not exceed 9999999999.99.',
            'stock.integer' => 'Stock must be a whole number.',
            'stock.min' => 'Stock must be zero or greater.',
            'stock.max' => 'Stock must not exceed 4294967295.',
            'category.string' => 'Please select a valid category.',
            'category.max' => 'The category must not exceed 255 characters.',
            'subtitle.max' => 'The short summary must not exceed 255 characters.',
            'dimensions.max' => 'Dimensions must not exceed 255 characters.',
            'whats_included.max' => "What's included must not exceed 10000 characters.",
            'image_files.max' => 'A product can have no more than 10 images in total.',
            'image_files.*.image' => 'Choose a valid JPG, PNG, or WebP image.',
            'image_files.*.mimes' => 'Choose a valid JPG, PNG, or WebP image.',
            'image_files.*.max' => 'Each image must not exceed 4 MB.',
        ]);
    }

    private function rawImagePaths(Product $product): array
    {
        $images = json_decode(
            $product->getRawOriginal('images') ?? '[]',
            true
        );

        return array_values(array_unique(array_filter([
            ...(is_array($images) ? $images : []),
            $product->getRawOriginal('image_url'),
        ], fn ($path) => is_string($path) && $path !== '')));
    }

    private function localImagePath(string $value): ?string
    {
        if (str_starts_with($value, 'products/')) {
            return $value;
        }

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('public');

        $prefix = rtrim(
            $disk->url('products'),
            '/'
        ).'/';

        if (str_starts_with($value, $prefix)) {
            return 'products/'.substr($value, strlen($prefix));
        }

        return null;
    }

    private function deleteUnusedImages(
        array $oldImages,
        array $newImages = []
    ): void {
        $retained = array_filter(array_map(
            fn ($path) => $this->localImagePath($path),
            $newImages
        ));

        foreach ($oldImages as $image) {
            $path = $this->localImagePath($image);

            if ($path && ! in_array($path, $retained, true)) {
                Storage::disk('public')->delete($path);
            }
        }
    }

    private function saveProduct(
        Request $request,
        Product $product
    ): Product {
        $data = $this->validatedData($request, $product->exists);
        $oldImages = $this->rawImagePaths($product);
        $uploadedPaths = [];
        $retainedImages = $oldImages;
        $hasRetainedImages = array_key_exists('retained_images', $data);

        if ($hasRetainedImages) {
            $submitted = json_decode($data['retained_images'], true);
            Validator::make(['retained_images' => $submitted], [
                'retained_images' => ['present', 'array', 'max:10'],
                'retained_images.*' => ['required', 'string', 'max:2048', 'distinct'],
            ])->validate();
            if (! array_is_list($submitted)) {
                throw ValidationException::withMessages([
                    'retained_images' => 'Retained images must be a list.',
                ]);
            }
            $retainedImages = [];
            foreach ($submitted as $image) {
                $matched = null;
                foreach ($oldImages as $oldImage) {
                    $publicUrl = str_starts_with($oldImage, 'products/')
                        ? Storage::disk('public')->url($oldImage)
                        : $oldImage;
                    if ($image === $oldImage || $image === $publicUrl) {
                        $matched = $oldImage;
                        break;
                    }
                }
                if ($matched === null) {
                    throw ValidationException::withMessages([
                        'retained_images' => 'An image does not belong to this product. Reload the product and try again.',
                    ]);
                }
                $retainedImages[] = $matched;
            }
            $retainedImages = array_values(array_unique($retainedImages));
        }

        $files = $request->file('image_files', []);
        $manageImages = $hasRetainedImages || count($files) > 0;
        if ($manageImages && count($retainedImages) + count($files) > 10) {
            throw ValidationException::withMessages([
                'image_files' => 'A product can have no more than 10 images in total.',
            ]);
        }
        unset($data['image_files'], $data['retained_images']);

        try {
            if ($manageImages) {
                foreach ($files as $file) {
                    $path = $file->store('products', 'public');
                    if ($path === false) {
                        throw new RuntimeException('A product image could not be stored.');
                    }
                    $uploadedPaths[] = $path;
                }
                $data['images'] = [...$retainedImages, ...$uploadedPaths];
                $data['image_url'] = $data['images'][0] ?? null;
            } elseif (array_key_exists('images', $data)) {
                $data['image_url'] = $data['images'][0] ?? $data['image_url'] ?? null;
            }

            $product->fill($data);
            $product->save();
        } catch (Throwable $exception) {
            $this->deleteUnusedImages($uploadedPaths);
            throw $exception;
        }

        $this->deleteUnusedImages($oldImages, $this->rawImagePaths($product));

        return $product->fresh();
    }

    public function index(Request $request)
    {
        $this->authorizeAdmin($request);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
        ]);

        $query = Product::query();

        if (! empty($filters['search'])) {
            $query->where(
                'name',
                'like',
                '%'.$filters['search'].'%'
            );
        }

        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        return response()->json(
            $query->orderBy('id')->paginate(10)
        );
    }

    public function store(Request $request)
    {
        $this->authorizeAdmin($request);

        return response()->json([
            'message' => 'The product has been added.',
            'data' => $this->saveProduct($request, new Product),
        ], 201);
    }

    public function show(Request $request, Product $product)
    {
        $this->authorizeAdmin($request);

        return response()->json([
            'data' => $product,
        ]);
    }

    public function update(Request $request, Product $product)
    {
        $this->authorizeAdmin($request);

        return response()->json([
            'message' => 'The product has been modified.',
            'data' => $this->saveProduct($request, $product),
        ]);
    }

    public function destroy(Request $request, Product $product)
    {
        $this->authorizeAdmin($request);

        $images = $this->rawImagePaths($product);

        $product->delete();

        $this->deleteUnusedImages($images);

        return response()->json([
            'message' => 'The product has been deleted.',
        ]);
    }
}
