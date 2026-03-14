<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Http\Resources\ProductResource;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $q = Product::with('category')->where('is_available', true);

        if ($request->filled('category_id')) {
            $q->where('category_id', $request->category_id);
        }

        if ($request->filled('search')) {
            $q->where('name', 'LIKE', '%' . $request->search . '%');
        }

        if ($request->filled('popular') && $request->popular === 'true') {
            $q->where('is_popular', true);
        }

        if ($request->filled('all') && $request->all === 'true') {
            $q = Product::with('category');

            if ($request->filled('search')) {
                $q->where('name', 'LIKE', '%' . $request->search . '%');
            }

            if ($request->filled('category_id')) {
                $q->where('category_id', $request->category_id);
            }
        }

        return ProductResource::collection(
            $q->orderByDesc('created_at')->paginate(20)
        );
    }

    public function show(Product $product)
    {
        $product->load('category');
        return new ProductResource($product);
    }

    public function store(Request $request)
    {
        if (!JWTAuth::user()->isStaff()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $v = $request->validate([
            'category_id'      => 'required|exists:categories,id',
            'name'             => 'required|string|min:2|max:200',
            'description'      => 'nullable|string',
            'price'            => 'required|numeric|min:0',
            'preparation_time' => 'sometimes|integer|min:1',
            'calories'         => 'sometimes|integer|min:0',
            'ingredients'      => 'sometimes|array',
            'is_popular'       => 'sometimes|boolean',
            'image'            => 'sometimes|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        $imagePath = null;

        if ($request->hasFile('image') && $request->file('image')->isValid()) {
            $path = $request->file('image')->store('products', 'public');
            $imagePath = '/storage/' . $path;
        }

        $product = Product::create([
            'category_id'      => $v['category_id'],
            'name'             => $v['name'],
            'description'      => $v['description'] ?? null,
            'price'            => $v['price'],
            'preparation_time' => $v['preparation_time'] ?? 20,
            'calories'         => $v['calories'] ?? 0,
            'ingredients'      => $v['ingredients'] ?? [],
            'is_popular'       => $v['is_popular'] ?? false,
            'is_available'     => true,
            'image_path'       => $imagePath,
        ]);

        $product->load('category');

        return response()->json([
            'message' => 'Product created',
            'product' => new ProductResource($product),
        ], 201);
    }

    public function update(Request $request, Product $product)
    {
        if (!JWTAuth::user()->isStaff()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $v = $request->validate([
            'name'             => 'sometimes|string|min:2|max:200',
            'description'      => 'sometimes|nullable|string',
            'price'            => 'sometimes|numeric|min:0',
            'preparation_time' => 'sometimes|integer|min:1',
            'calories'         => 'sometimes|integer|min:0',
            'ingredients'      => 'sometimes|array',
            'is_popular'       => 'sometimes|boolean',
            'is_available'     => 'sometimes|boolean',
            'image'            => 'sometimes|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        // Handle image if provided
        if ($request->hasFile('image') && $request->file('image')->isValid()) {
            if ($product->image_path) {
                $oldPath = str_replace('/storage/', '', $product->image_path);
                if (Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }
            }
            $path = $request->file('image')->store('products', 'public');
            $v['image_path'] = '/storage/' . $path;
        }

        $product->update($v);
        $product->load('category');

        return response()->json([
            'message' => 'Product updated',
            'product' => new ProductResource($product),
        ]);
    }

    public function updateWithImage(Request $request, Product $product)
    {
        if (!JWTAuth::user()->isStaff()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $v = $request->validate([
            'name'        => 'sometimes|string|min:2|max:200',
            'description' => 'sometimes|nullable|string',
            'price'       => 'sometimes|numeric|min:0',
            'category_id' => 'sometimes|exists:categories,id',
            'image'       => 'required|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        // Delete old image
        if ($product->image_path) {
            $oldPath = str_replace('/storage/', '', $product->image_path);
            if (Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        // Store new image
        $path = $request->file('image')->store('products', 'public');
        $v['image_path'] = '/storage/' . $path;

        unset($v['image']);

        $product->update($v);
        $product->load('category');

        return response()->json([
            'message' => 'Product updated',
            'product' => new ProductResource($product),
        ]);
    }

    public function destroy(Product $product)
    {
        if (!JWTAuth::user()->isStaff()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($product->image_path) {
            $oldPath = str_replace('/storage/', '', $product->image_path);
            if (Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        $product->delete();

        return response()->json([
            'message' => 'Product deleted'
        ]);
    }
}