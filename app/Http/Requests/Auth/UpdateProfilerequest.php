<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'             => 'sometimes|string|min:2|max:100',
            'phone'            => 'sometimes|nullable|string|regex:/^\+?[0-9]{10,15}$/',
            'address'          => 'sometimes|nullable|string|max:500',
            'profile_image'    => 'sometimes|image|mimes:jpeg,png,jpg,webp|max:2048',
            'current_password' => 'required_with:new_password|string',
            'new_password'     => 'required_with:current_password|string|min:8|confirmed',
        ];
    }
}