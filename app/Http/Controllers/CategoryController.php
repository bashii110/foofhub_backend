<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Tymon\JWTAuth\Facades\JWTAuth;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = Cache::remember('categories_list', 600, function () {
            return Category::active()
                ->withCount(['products' => fn ($q) => $q->where('is_available', true)])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();
        });

        return response()->json(['categories' => $categories]);
    }

    public function store(Request $request): JsonResponse
    {
        $v = $request->validate([
            'name'       => 'required|string|unique:categories|max:100',
            'icon'       => 'nullable|string|max:50',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $category = Category::create($v);
        Cache::forget('categories_list');

        return response()->json(['message' => 'Category created', 'category' => $category], 201);
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        $v = $request->validate([
            'name'       => 'sometimes|string|unique:categories,name,' . $category->id . '|max:100',
            'icon'       => 'sometimes|nullable|string|max:50',
            'is_active'  => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer|min:0',
        ]);

        $category->update($v);
        Cache::forget('categories_list');

        return response()->json(['message' => 'Category updated', 'category' => $category]);
    }

    public function destroy(Category $category): JsonResponse
    {
        if ($category->products()->where('is_available', true)->exists()) {
            return response()->json([
                'message' => 'Cannot delete a category that has active products. Remove or disable products first.',
            ], 409);
        }

        $category->delete();
        Cache::forget('categories_list');

        return response()->json(['message' => 'Category deleted']);
    }
}