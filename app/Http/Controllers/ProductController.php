<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // التحقق من خيارات البحث والتصفية والترتيب
        $filters = $request->validate([
            'search' => [
                'nullable',
                'string',
                'max:255',
            ],

            'category' => [
                'nullable',
                'string',
                'max:255',
            ],

            'sort' => [
                'nullable',
                Rule::in([
                    'latest',
                    'price-low',
                    'price-high',
                ]),
            ],

            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ]);

        $query = Product::query();

        // البحث في الاسم والعنوان المختصر والفئة
        $search = trim($filters['search'] ?? '');

        if ($search !== '') {
            $query->where(function ($query) use ($search) {
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhere('subtitle', 'like', '%'.$search.'%')
                    ->orWhere('category', 'like', '%'.$search.'%');
            });
        }

        // تصفية المنتجات حسب الفئة
        $category = trim($filters['category'] ?? '');

        if ($category !== '' && $category !== 'All') {
            $query->where('category', $category);
        }

        // ترتيب المنتجات مع تثبيت ترتيب المنتجات المتساوية بالسعر
        switch ($filters['sort'] ?? 'latest') {
            case 'price-low':
                $query->orderBy('price')
                    ->orderByDesc('id');
                break;

            case 'price-high':
                $query->orderByDesc('price')
                    ->orderByDesc('id');
                break;

            default:
                $query->orderByDesc('id');
                break;
        }

        $products = $query
            ->paginate((int) ($filters['per_page'] ?? 100))
            ->withQueryString();

        return response()->json($products);
    }

    public function show(Product $product): JsonResponse
    {
        // Laravel يعيد خطأ 404 تلقائياً إذا لم يكن المنتج موجوداً
        return response()->json([
            'data' => $product,
        ]);
    }
}
