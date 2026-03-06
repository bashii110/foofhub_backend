<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        if (!JWTAuth::user()->isStaff()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $q = Order::with('items.product', 'payment', 'user');

        if ($request->filled('status'))
            $q->where('status', $request->status);

        if ($request->filled('search')) {
            $term = $request->search;
            $q->where(function ($q) use ($term) {
                $q->where('id', $term)
                  ->orWhereHas('user', fn ($u) =>
                      $u->where('name','LIKE',"%$term%")
                        ->orWhere('email','LIKE',"%$term%")
                  );
            });
        }

        return response()->json($q->orderByDesc('created_at')->paginate(15));
    }

    public function show(Order $order)
    {
        if (!JWTAuth::user()->isStaff()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $order->load('items.product', 'payment.verifiedBy', 'user');
        return response()->json(['order' => $order]);
    }

    public function verifyPayment(Order $order)
    {
        $user = JWTAuth::user();
        if (!$user->isStaff()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($order->status !== 'pending_verification') {
            return response()->json(['message' => 'Order is not pending verification'], 400);
        }

        $order->payment->update([
            'verified'    => true,
            'verified_by' => $user->id,
            'verified_at' => now(),
        ]);

        $order->update(['status' => 'verified']);
        return response()->json([
            'message' => 'Payment verified',
            'order'   => $order->load('payment.verifiedBy'),
        ]);
    }

    public function rejectPayment(Request $request, Order $order)
    {
        if (!JWTAuth::user()->isStaff()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $v = $request->validate(['reason' => 'required|string|min:5']);

        $order->payment->update(['rejection_reason' => $v['reason']]);
        $order->update(['status' => 'cancelled']);

        return response()->json([
            'message' => 'Payment rejected',
            'order'   => $order->load('payment'),
        ]);
    }

    public function updateStatus(Request $request, Order $order)
    {
        if (!JWTAuth::user()->isStaff()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $v = $request->validate([
            'status' => 'required|in:preparing,out_for_delivery,delivered,cancelled',
            'notes'  => 'nullable|string',
        ]);

        $order->update([
            'status'      => $v['status'],
            'admin_notes' => $v['notes'] ?? $order->admin_notes,
        ]);

        return response()->json(['message' => 'Status updated', 'order' => $order]);
    }
}