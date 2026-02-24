<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'products' => Product::query()->with(['store', 'categories'])->latest()->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:100', 'unique:products,sku'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'category_ids' => ['required', 'array', 'min:1'],
            'category_ids.*' => ['integer', 'distinct', 'exists:product_categories,id'],
        ]);

        $categoryIds = $validated['category_ids'];
        unset($validated['category_ids']);

        $product = Product::create($validated);
        $product->categories()->sync($categoryIds);

        return response()->json([
            'message' => 'Product created successfully.',
            'product' => $product->load(['store', 'categories']),
        ], 201);
    }

    public function show(Product $product): JsonResponse
    {
        return response()->json([
            'product' => $product->load(['store', 'categories']),
        ]);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'store_id' => ['sometimes', 'required', 'integer', 'exists:stores,id'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'sku' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
                Rule::unique('products', 'sku')->ignore($product->id),
            ],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'stock_quantity' => ['sometimes', 'required', 'integer', 'min:0'],
            'category_ids' => ['sometimes', 'required', 'array', 'min:1'],
            'category_ids.*' => ['integer', 'distinct', 'exists:product_categories,id'],
        ]);

        $categoryIds = $validated['category_ids'] ?? null;
        unset($validated['category_ids']);

        $product->update($validated);

        if ($categoryIds !== null) {
            $product->categories()->sync($categoryIds);
        }

        return response()->json([
            'message' => 'Product updated successfully.',
            'product' => $product->fresh()->load(['store', 'categories']),
        ]);
    }

    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json([
            'message' => 'Product deleted successfully.',
        ]);
    }
}
