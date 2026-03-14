<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use App\Jobs\SendOrderNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tymon\JWTAuth\Facades\JWTAuth;

class PaymentController extends Controller
{
    public function uploadProof(Request $request, Order $order): JsonResponse
    {
        $user = JWTAuth::user();

        // Authorization
        if (! $order->isOwnedBy($user)) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        // Only allowed in pending_payment state
        if ($order->status !== Order::STATUS_PENDING_PAYMENT) {
            return response()->json([
                'message' => 'Payment proof cannot be uploaded at this stage.',
                'current_status' => $order->status,
            ], 409);
        }

    

        $request->validate([
            'screenshot'     => 'required|image|mimes:jpeg,png,jpg,webp|max:5120',
            'reference'      => 'nullable|string|max:100',
            'payment_method' => 'nullable|in:jazzcash,easypaisa,bank_transfer,cod', // ← renamed
        ]);

        // Delete previous screenshot if exists
        $payment = Payment::where('order_id', $order->id)->firstOrFail();
        if ($payment->screenshot_url) {
            Storage::disk('public')->delete($payment->getRawOriginal('screenshot_url') ?? '');
        }

        // Store new screenshot
        $path = $request->file('screenshot')
            ->store('payment_proofs/' . date('Y/m'), 'public');

        $payment->update([
            'screenshot_url' => $path,
            'reference'      => $request->input('reference'),
            'method'         => $request->input('payment_method') ?? $payment->method,
        ]);

        $order->update(['status' => Order::STATUS_PENDING_VERIFICATION]);

        dispatch(new SendOrderNotification($order->id, 'proof_uploaded'));

        Log::info('Payment proof uploaded', ['order_id' => $order->id, 'user_id' => $user->id]);

        return response()->json([
            'message' => 'Payment proof uploaded successfully. Your order is pending admin verification.',
            'order'   => [
                'id'             => $order->id,
                'status'         => Order::STATUS_PENDING_VERIFICATION,
                'screenshot_url' => Storage::url($path),
            ],
        ]);
    }
}