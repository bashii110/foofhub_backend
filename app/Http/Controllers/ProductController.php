<?php
namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $q = Product::with('category')->where('is_available', true);

        if ($request->filled('category_id'))  $q->where('category_id', $request->category_id);
        if ($request->filled('search'))       $q->where('name', 'LIKE', '%'.$request->search.'%');
        if ($request->filled('popular') && $request->popular === 'true')
                                              $q->where('is_popular', true);

        return response()->json($q->orderByDesc('created_at')->paginate(20));
    }

    public function show(Product $product)
    {
        $product->load('category');
        return response()->json(['product' => $product]);
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

        $imageUrl = null;
        if ($request->hasFile('image')) {
            $imageUrl = '/storage/' . $request->file('image')->store('products', 'public');
        }

        $product = Product::create([
            'category_id'      => $v['category_id'],
            'name'             => $v['name'],
            'description'      => $v['description']      ?? null,
            'price'            => $v['price'],
            'preparation_time' => $v['preparation_time'] ?? 20,
            'calories'         => $v['calories']         ?? 0,
            'ingredients'      => $v['ingredients']      ?? [],
            'is_popular'       => $v['is_popular']       ?? false,
            'image_url'        => $imageUrl,
        ]);

        $product->load('category');
        return response()->json(['message' => 'Product created', 'product' => $product], 201);
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

        if ($request->hasFile('image')) {
            $v['image_url'] = '/storage/' . $request->file('image')->store('products', 'public');
        }

        $product->update($v);
        $product->load('category');
        return response()->json(['message' => 'Product updated', 'product' => $product]);
    }

    public function destroy(Product $product)
    {
        if (!JWTAuth::user()->isStaff()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $product->delete();
        return response()->json(['message' => 'Product deleted']);
    }
}