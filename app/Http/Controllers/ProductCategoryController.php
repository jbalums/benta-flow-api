<?php

namespace App\Http\Controllers;

use App\Models\ProductCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductCategoryController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'categories' => ProductCategory::query()->latest()->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:product_categories,name'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $category = ProductCategory::create($validated);

        return response()->json([
            'message' => 'Product category created successfully.',
            'category' => $category,
        ], 201);
    }

    public function show(ProductCategory $productCategory): JsonResponse
    {
        return response()->json([
            'category' => $productCategory,
        ]);
    }

    public function update(Request $request, ProductCategory $productCategory): JsonResponse
    {
        $validated = $request->validate([
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('product_categories', 'name')->ignore($productCategory->id),
            ],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        $productCategory->update($validated);

        return response()->json([
            'message' => 'Product category updated successfully.',
            'category' => $productCategory->fresh(),
        ]);
    }

    public function destroy(ProductCategory $productCategory): JsonResponse
    {
        $productCategory->delete();

        return response()->json([
            'message' => 'Product category deleted successfully.',
        ]);
    }
}
