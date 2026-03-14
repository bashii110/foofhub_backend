<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'items'                            => 'required|array|min:1|max:50',
            'items.*.product_id'               => 'required|integer|exists:products,id',
            'items.*.quantity'                 => 'required|integer|min:1|max:99',
            'items.*.special_instructions'     => 'nullable|string|max:255',
            'delivery_address'                 => 'required|string|min:10|max:500',
            'delivery_lat'                     => 'nullable|numeric|between:-90,90',
            'delivery_lng'                     => 'nullable|numeric|between:-180,180',
            'payment_method'                   => 'required|in:jazzcash,easypaisa,bank_transfer,cod',
            'customer_name'                    => 'nullable|string|min:2|max:100',
            'phone'                            => 'nullable|string|regex:/^\+?[0-9]{10,15}$/',
            'notes'                            => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'items.required'            => 'Your order must have at least one item.',
            'delivery_address.required' => 'Please provide a delivery address.',
            'payment_method.in'         => 'Invalid payment method selected.',
        ];
    }
}