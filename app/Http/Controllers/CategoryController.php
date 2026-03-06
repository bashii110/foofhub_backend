<?php
namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class CategoryController extends Controller
{
    public function index()
    {
        return response()->json([
            'categories' => Category::withCount('products')->get(),
        ]);
    }

    public function store(Request $request)
    {
        if (!JWTAuth::user()->isStaff()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $v = $request->validate([
            'name' => 'required|string|unique:categories|max:100',
            'icon' => 'nullable|string|max:50',
        ]);

        $category = Category::create($v);
        return response()->json(['message' => 'Category created', 'category' => $category], 201);
    }

    public function update(Request $request, Category $category)
    {
        if (!JWTAuth::user()->isStaff()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $v = $request->validate([
            'name' => 'sometimes|string|unique:categories,name,'.$category->id.'|max:100',
            'icon' => 'sometimes|nullable|string|max:50',
        ]);

        $category->update($v);
        return response()->json(['message' => 'Category updated', 'category' => $category]);
    }

    public function destroy(Category $category)
    {
        if (!JWTAuth::user()->isStaff()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $category->delete();
        return response()->json(['message' => 'Category deleted']);
    }
}