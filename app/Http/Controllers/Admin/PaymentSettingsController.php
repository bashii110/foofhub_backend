<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentSettingsController extends Controller
{
    // PUBLIC — Flutter fetches this on checkout screen (no auth needed)
    public function index(): JsonResponse
    {
        $settings = PaymentSetting::where('is_active', true)
            ->get(['id', 'method', 'account_number', 'account_name', 'instructions']);

        return response()->json(['data' => $settings]);
    }

    // ADMIN — get all including inactive
    public function adminIndex(): JsonResponse
    {
        return response()->json([
            'data' => PaymentSetting::all()
        ]);
    }

    // ADMIN — update a single method's details
    public function update(Request $request, PaymentSetting $paymentSetting): JsonResponse
    {
        $v = $request->validate([
            'account_number' => 'sometimes|string|max:100',
            'account_name'   => 'sometimes|string|max:100',
            'instructions'   => 'sometimes|nullable|string|max:300',
            'is_active'      => 'sometimes|boolean',
        ]);

        $paymentSetting->update($v);

        return response()->json([
            'message' => 'Payment setting updated',
            'data'    => $paymentSetting->fresh(),
        ]);
    }
}