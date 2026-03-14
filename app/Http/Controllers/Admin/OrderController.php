<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderStatusLog;
use App\Jobs\SendOrderNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;

class OrderController extends Controller
{
    private static array $validTransitions = [
        Order::STATUS_PENDING_VERIFICATION => [Order::STATUS_VERIFIED,         Order::STATUS_CANCELLED],
        Order::STATUS_VERIFIED             => [Order::STATUS_PREPARING,        Order::STATUS_CANCELLED],
        Order::STATUS_PREPARING            => [Order::STATUS_OUT_FOR_DELIVERY, Order::STATUS_CANCELLED],
        Order::STATUS_OUT_FOR_DELIVERY     => [Order::STATUS_DELIVERED,        Order::STATUS_CANCELLED],
    ];

    // ── List All Orders ────────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $query = Order::with(['user:id,name,email', 'payment', 'rider:id,name,phone'])
            ->when($request->status,     fn ($q) => $q->where('status', $request->status))
            ->when($request->date_from,  fn ($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->date_to,    fn ($q) => $q->whereDate('created_at', '<=', $request->date_to))
            ->when($request->search, function ($q) use ($request) {
                $term = $request->search;
                $q->where(function ($q) use ($term) {
                    $q->where('id', $term)
                      ->orWhereHas('user', fn ($u) =>
                          $u->where('name', 'LIKE', "%$term%")
                            ->orWhere('email', 'LIKE', "%$term%")
                      );
                });
            })
            ->orderByDesc('created_at');

        return response()->json($query->paginate((int) $request->get('per_page', 20)));
    }

    // ── Get Single Order ───────────────────────────────────────────────
    public function show(Order $order): JsonResponse
    {
        $order->load(['items.product', 'payment.verifiedBy', 'user', 'rider', 'statusLogs.changedBy']);
        return response()->json(['order' => $order]);
    }

    // ── Verify Payment ─────────────────────────────────────────────────
    public function verifyPayment(Order $order): JsonResponse
    {
        $admin = JWTAuth::user();

        if ($order->status !== Order::STATUS_PENDING_VERIFICATION) {
            return response()->json([
                'message' => 'Order is not in pending_verification state',
            ], 409);
        }

        $prevStatus = $order->status;

        $order->payment->update([
            'verified'    => true,
            'verified_by' => $admin->id,
            'verified_at' => now(),
        ]);

        $order->update([
            'status'         => Order::STATUS_VERIFIED,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
        ]);

        $this->logStatusChange($order, $prevStatus, Order::STATUS_VERIFIED, $admin->id, 'Payment verified by admin');
        dispatch(new SendOrderNotification($order->id, 'payment_verified'));

        Log::info('Payment verified', ['order_id' => $order->id, 'admin_id' => $admin->id]);

        return response()->json([
            'message' => 'Payment verified. Order is now confirmed.',
            'order'   => $order->load('payment.verifiedBy'),
        ]);
    }

    // ── Reject Payment ─────────────────────────────────────────────────
    public function rejectPayment(Request $request, Order $order): JsonResponse
    {
        $admin = JWTAuth::user();

        $request->validate(['reason' => 'required|string|min:10|max:500']);

        if ($order->status !== Order::STATUS_PENDING_VERIFICATION) {
            return response()->json(['message' => 'Order is not pending verification'], 409);
        }

        $prevStatus = $order->status;

        $order->payment->update([
            'rejection_reason' => $request->reason,
        ]);

        $order->update([
            'status'         => Order::STATUS_PENDING_PAYMENT,
            'payment_status' => Order::PAYMENT_STATUS_PENDING,
        ]);

        $this->logStatusChange($order, $prevStatus, Order::STATUS_PENDING_PAYMENT, $admin->id, 'Payment rejected: ' . $request->reason);
        dispatch(new SendOrderNotification($order->id, 'payment_rejected'));

        return response()->json(['message' => 'Payment rejected. Customer notified.', 'order' => $order]);
    }

    // ── Update Status ──────────────────────────────────────────────────
    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $admin = JWTAuth::user();

        $request->validate([
            'status'     => 'required|string',
            'note'       => 'nullable|string|max:500',
            'rider_id'   => 'nullable|exists:users,id',
        ]);

        $newStatus = $request->status;

        // Validate transition
        $allowed = self::$validTransitions[$order->status] ?? [];
        if (! in_array($newStatus, $allowed)) {
            return response()->json([
                'message' => "Cannot transition from [{$order->status}] to [$newStatus].",
                'allowed' => $allowed,
            ], 422);
        }

        $prevStatus = $order->status;
        $updateData = ['status' => $newStatus];

        if ($newStatus === Order::STATUS_DELIVERED) {
            $updateData['delivered_at']    = now();
            $updateData['payment_status']  = Order::PAYMENT_STATUS_PAID;
        }

        if ($newStatus === Order::STATUS_OUT_FOR_DELIVERY && $request->rider_id) {
            $updateData['rider_id'] = $request->rider_id;
        }

        if ($newStatus === Order::STATUS_CANCELLED) {
            $updateData['cancelled_at'] = now();
        }

        $order->update($updateData);

        $this->logStatusChange($order, $prevStatus, $newStatus, $admin->id, $request->note ?? "Status updated to $newStatus");
        dispatch(new SendOrderNotification($order->id, 'status_updated'));

        return response()->json(['message' => 'Order status updated', 'order' => $order->fresh()]);
    }

    // ── Add Admin Note ─────────────────────────────────────────────────
    public function addNote(Request $request, Order $order): JsonResponse
    {
        $request->validate(['note' => 'required|string|max:1000']);
        $order->update(['admin_notes' => $request->note]);
        return response()->json(['message' => 'Note saved']);
    }

    // ── Private: Log Status Change ─────────────────────────────────────
    private function logStatusChange(Order $order, ?string $from, string $to, int $adminId, string $note = ''): void
    {
        OrderStatusLog::create([
            'order_id'    => $order->id,
            'changed_by'  => $adminId,
            'from_status' => $from,
            'to_status'   => $to,
            'note'        => $note,
            'created_at'  => now(),
        ]);
    }
}