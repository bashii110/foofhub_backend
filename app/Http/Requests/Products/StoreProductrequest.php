<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;
use Tymon\JWTAuth\Facades\JWTAuth;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return JWTAuth::user()?->isStaff() ?? false;
    }

    public function rules(): array
    {
        return [
            'category_id'      => 'required|exists:categories,id',
            'name'             => 'required|string|min:2|max:200',
            'description'      => 'nullable|string|max:2000',
            'price'            => 'required|numeric|min:0|max:999999',
            'preparation_time' => 'sometimes|integer|min:1|max:180',
            'calories'         => 'sometimes|integer|min:0|max:9999',
            'ingredients'      => 'sometimes|array',
            'ingredients.*'    => 'string|max:100',
            'is_popular'       => 'sometimes|boolean',
            'is_available'     => 'sometimes|boolean',
            'stock'            => 'sometimes|nullable|integer|min:0',
            'image'            => 'sometimes|image|mimes:jpeg,png,jpg,webp|max:3072',
        ];
    }
}