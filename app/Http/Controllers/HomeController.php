<?php

namespace App\Http\Controllers;

use App\Models\Partner;
use App\Models\Product;
use App\Models\Setting;

class HomeController extends Controller
{
    public function index()
    {
        // عرض أحدث أربعة منتجات دون الحاجة إلى حقل featured
        $products = Product::orderByDesc('id')
            ->limit(4)
            ->get();

        // استخراج التصنيفات من جميع المنتجات
        $categories = Product::query()
            ->whereNotNull('category')
            ->where('category', '<>', '')
            ->select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->values();

        $settings = Setting::find(1);

        $publicSettings = $settings
            ? $settings->only(array_keys(Setting::publicDefaults()))
            : Setting::publicDefaults();

        return response()->json([
            'data' => [
                'settings' => $publicSettings,
                'products' => $products,
                'categories' => $products->isEmpty()
                    ? []
                    : ['All', ...$categories->all()],
                'partners' => Partner::orderByDesc('id')->get(),
                'has_products' => $products->isNotEmpty(),
                'empty_message' => $products->isEmpty()
                    ? 'No products have been added yet.'
                    : null,
            ],
        ]);
    }
}
