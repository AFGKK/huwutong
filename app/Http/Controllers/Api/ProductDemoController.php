<?php

namespace App\Http\Controllers\Api;

use App\Models\Product;
use App\Models\ProductDemo;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProductDemoController extends Controller
{
    public function index(Product $product): JsonResponse
    {
        try {
            $demos = $product->demos()->orderBy('sort_order')->get();
        } catch (\Exception $e) {
            $demos = collect();
        }
        return response()->json([
            'success' => true,
            'data' => [
                'demos' => $demos,
                'demo_enabled' => $product->demo_enabled,
                'demo_images' => $product->demo_images ?? [],
            ],
        ]);
    }

    public function store(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'platform' => 'required|string|max:100',
            'site_url' => 'nullable|string|max:500',
            'account' => 'nullable|string|max:200',
            'password' => 'nullable|string|max:200',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $demo = $product->demos()->create($validated);

        return response()->json(['success' => true, 'data' => $demo], 201);
    }

    public function update(Request $request, ProductDemo $demo): JsonResponse
    {
        $validated = $request->validate([
            'platform' => 'required|string|max:100',
            'site_url' => 'nullable|string|max:500',
            'account' => 'nullable|string|max:200',
            'password' => 'nullable|string|max:200',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $demo->update($validated);

        return response()->json(['success' => true, 'data' => $demo]);
    }

    public function destroy(ProductDemo $demo): JsonResponse
    {
        $demo->delete();
        return response()->json(['success' => true]);
    }

    public function updateSettings(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'demo_enabled' => 'boolean',
            'demo_images' => 'nullable|array',
            'demo_images.*.label' => 'required_with:demo_images|string|max:100',
            'demo_images.*.url' => 'required_with:demo_images|string|max:500',
        ]);

        $product->update($validated);

        return response()->json(['success' => true]);
    }

    /**
     * Frontend: get public demo info for a product
     */
    public function publicShow(Product $product): JsonResponse
    {
        if (!$product->demo_enabled) {
            return response()->json(['success' => false, 'message' => __('app.controller_compat.product_demo_msg_86')], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'demos' => $product->demos()->orderBy('sort_order')->get(),
                'demo_images' => $product->demo_images ?? [],
            ],
        ]);
    }
}
