<?php
namespace App\Http\Controllers;

use App\Models\{Order, Payment};
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class PaymentController extends Controller
{
    public function uploadProof(Request $request, Order $order)
    {
        $user = JWTAuth::user();

        if ($order->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($order->status !== 'pending_payment') {
            return response()->json(['message' => 'Order is not pending payment'], 400);
        }

        $v = $request->validate([
            'screenshot' => 'required|image|mimes:jpeg,png,jpg,webp|max:5120',
            'reference'  => 'nullable|string|max:100',
        ]);

        // Store image
        $path = $request->file('screenshot')->store('payment_proofs', 'public');

        // Update payment
        $payment = Payment::where('order_id', $order->id)->firstOrFail();
        $payment->update([
            'screenshot_url' => '/storage/' . $path,
            'reference'      => $v['reference'] ?? null,
        ]);

        // Move order to pending_verification
        $order->update(['status' => 'pending_verification']);

        return response()->json([
            'message' => 'Payment proof uploaded. Your order is pending verification.',
            'order'   => $order->load('payment'),
        ]);
    }
}