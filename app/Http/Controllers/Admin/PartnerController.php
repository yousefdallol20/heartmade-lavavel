<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class PartnerController extends Controller
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
        bool $updating
    ): array {
        $data = $request->validate([
            'name' => [
                ...($updating ? ['sometimes', 'required'] : ['required']),
                'string',
                'max:255',
            ],

            'url' => [
                'sometimes',
                'nullable',
                'string',
                'max:2048',
                function ($attribute, $value, $fail) {
                    if ($value === null || $value === '') {
                        return;
                    }

                    // قبول بريد إلكتروني أو رابط يبدأ بـ HTTP أو HTTPS
                    $isEmail = filter_var(
                        $value,
                        FILTER_VALIDATE_EMAIL
                    ) !== false;

                    $isUrl = filter_var(
                        $value,
                        FILTER_VALIDATE_URL
                    ) !== false
                        && preg_match('~^https?://~i', $value);

                    if (! $isEmail && ! $isUrl) {
                        $fail(
                            'Enter a valid email address or an HTTP/HTTPS URL.'
                        );
                    }
                },
            ],

            'image' => [
                'sometimes',
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:4096',
            ],

            'image_url' => [
                'sometimes',
                'nullable',
                'url:http,https',
                'max:255',
            ],
        ]);

        $hasFile = $request->hasFile('image');
        $hasImageUrl = $request->filled('image_url');

        // منع إرسال ملف ورابط صورة معاً
        if ($hasFile && $hasImageUrl) {
            throw ValidationException::withMessages([
                'image' => 'Provide an image file or an image URL, not both.',
            ]);
        }

        // الصورة إجبارية عند إنشاء الشريك
        if (! $updating && ! $hasFile && ! $hasImageUrl) {
            throw ValidationException::withMessages([
                'image' => 'A partner image is required.',
            ]);
        }

        // منع تفريغ الصورة الحالية دون توفير بديل
        if (
            $updating
            && ! $hasFile
            && ! $hasImageUrl
            && (
                array_key_exists('image', $data)
                || array_key_exists('image_url', $data)
            )
        ) {
            throw ValidationException::withMessages([
                'image' => 'The partner image cannot be removed.',
            ]);
        }

        return $data;
    }

    private function deleteLocalImage(?string $path): void
    {
        // حذف الملفات المحلية التابعة للشركاء فقط
        if ($path && str_starts_with($path, 'partners/')) {
            Storage::disk('public')->delete($path);
        }
    }

    private function savePartner(
        Request $request,
        Partner $partner
    ): Partner {
        $data = $this->validatedData($request, $partner->exists);

        $oldImage = $partner->getRawOriginal('image');
        $uploadedPath = null;

        // إزالة حقول الطلب قبل تجهيز البيانات للحفظ
        unset($data['image'], $data['image_url']);

        if ($request->filled('image_url')) {
            $data['image'] = $request->input('image_url');
        }

        if (
            $partner->exists
            && $data === []
            && ! $request->hasFile('image')
        ) {
            throw ValidationException::withMessages([
                'partner' => 'Provide at least one field to update.',
            ]);
        }

        try {
            if ($request->hasFile('image')) {
                $path = $request->file('image')
                    ->store('partners', 'public');

                if ($path === false) {
                    throw new RuntimeException(
                        'The image could not be stored.'
                    );
                }

                $uploadedPath = $path;
                $data['image'] = $path;
            }

            // التحقق من توفر صورة قبل حفظ السجل
            $finalImage = $data['image'] ?? $oldImage;

            if (! is_string($finalImage) || trim($finalImage) === '') {
                throw ValidationException::withMessages([
                    'image' => 'A partner image is required.',
                ]);
            }

            $partner->fill($data);
            $partner->save();
        } catch (Throwable $exception) {
            // حذف الملف الجديد إذا فشل الحفظ
            $this->deleteLocalImage($uploadedPath);

            throw $exception;
        }

        // حذف الصورة القديمة بعد نجاح استبدالها
        if ($oldImage !== $partner->getRawOriginal('image')) {
            $this->deleteLocalImage($oldImage);
        }

        return $partner->fresh();
    }

    public function index(Request $request)
    {
        $this->authorizeAdmin($request);

        return response()->json([
            'data' => Partner::orderByDesc('id')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeAdmin($request);

        $partner = $this->savePartner(
            $request,
            new Partner
        );

        return response()->json([
            'message' => 'Partner added successfully.',
            'data' => $partner,
        ], 201);
    }

    public function show(Request $request, Partner $partner)
    {
        $this->authorizeAdmin($request);

        return response()->json([
            'data' => $partner,
        ]);
    }

    public function update(Request $request, Partner $partner)
    {
        $this->authorizeAdmin($request);

        $partner = $this->savePartner(
            $request,
            $partner
        );

        return response()->json([
            'message' => 'Partner updated successfully.',
            'data' => $partner,
        ]);
    }

    public function destroy(Request $request, Partner $partner)
    {
        $this->authorizeAdmin($request);

        $image = $partner->getRawOriginal('image');

        $partner->delete();

        // حذف صورة الشريك المحلية بعد حذف سجله
        $this->deleteLocalImage($image);

        return response()->json([
            'message' => 'Partner deleted successfully.',
        ]);
    }
}
