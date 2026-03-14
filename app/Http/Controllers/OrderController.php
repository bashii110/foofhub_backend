<?php

namespace App\Http\Controllers;

use App\Http\Requests\Order\StoreOrderRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\OrderStatusLog;
use App\Jobs\SendOrderNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;

class OrderController extends Controller
{
    // ── Place Order ────────────────────────────────────────────────────
    public function store(StoreOrderRequest $request): JsonResponse
    {
        $user = JWTAuth::user();

        DB::beginTransaction();

        try {
            // 1. Verify all products exist and are available
            $productIds = collect($request->items)->pluck('product_id');
            $products   = Product::available()->whereIn('id', $productIds)->get()->keyBy('id');

            foreach ($request->items as $item) {
                if (! $products->has($item['product_id'])) {
                    DB::rollBack();
                    return response()->json([
                        'message' => "Product ID {$item['product_id']} is unavailable or does not exist.",
                    ], 422);
                }
            }

            Log::info('Creating order for user', ['user_id' => $user->id]);

            // Calculate totals
            $subtotal = 0;
            $lineItems = [];
            foreach ($request->items as $item) {
                $product  = $products[$item['product_id']];
                $lineTotal = $product->price * $item['quantity'];
                $subtotal += $lineTotal;

                $lineItems[] = [
                    'product_id'            => $product->id,
                    'product_name'          => $product->name,   // snapshot name at time of order
                    'quantity'              => $item['quantity'],
                    'price'                 => $product->price,  // snapshot price
                    'special_instructions'  => $item['special_instructions'] ?? null,
                ];
            }

            $deliveryFee = $this->calculateDeliveryFee($subtotal);
            $total       = round($subtotal + $deliveryFee, 2);

            // Create the order
            $order = Order::create([
                'user_id'          => $user->id,
                'customer_name'    => $request->customer_name ?? $user->name,
                'phone'            => $request->phone ?? $user->phone,
                'delivery_address' => $request->delivery_address,
                'delivery_lat'     => $request->delivery_lat,
                'delivery_lng'     => $request->delivery_lng,
                'subtotal'         => $subtotal,
                'delivery_fee'     => $deliveryFee,
                'discount'         => 0,
                'total_amount'     => $total,
                'payment_method'   => $request->payment_method,
                'payment_status'   => Order::PAYMENT_STATUS_PENDING,
                'status'           => Order::STATUS_PENDING_PAYMENT,
                'notes'            => $request->notes,
                'estimated_delivery_at' => now()->addMinutes(45),
            ]);

            // 4. Create order items
            foreach ($lineItems as $lineItem) {
                OrderItem::create(array_merge(['order_id' => $order->id], $lineItem));
            }

            // 5. Create payment record
            Payment::create([
                'order_id' => $order->id,
                'method'   => $request->payment_method,
                'amount'   => $total,
                'verified' => false,
            ]);

            // 6. Log status change
            OrderStatusLog::create([
                'order_id'   => $order->id,
                'changed_by' => $user->id,
                'from_status'=> null,
                'to_status'  => Order::STATUS_PENDING_PAYMENT,
                'note'       => 'Order placed',
                'created_at' => now(),
            ]);

            DB::commit();

            Log::info('Order completed successfully', ['order_id' => $order->id]);

            return response()->json([
                'message' => 'Order placed successfully',
                'order'   => $this->orderResource($order->load('items', 'payment')),
            ], 201);

        } catch (\Exception $e) {

            DB::rollBack();
            
            // Log the full error
            Log::error('Order creation failed', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Order could not be placed. Please try again.'], 500);
        }
    }

    // ── List User Orders ───────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $user = JWTAuth::user();

        $orders = Order::forUser($user->id)
            ->with(['items.product', 'payment'])
            ->when($request->status, fn ($q) => $q->byStatus($request->status))
            ->orderByDesc('created_at')
            ->paginate(15);

        return response()->json($orders);
    }

    // ── Get Single Order ───────────────────────────────────────────────
    public function show(Order $order): JsonResponse
    {
        $user = JWTAuth::user();

        if (! $order->isOwnedBy($user)) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        $order->load(['items.product', 'payment', 'statusLogs.changedBy']);

        return response()->json(['order' => $this->orderResource($order)]);
    }

    // ── Cancel Order ───────────────────────────────────────────────────
    public function cancel(Request $request, Order $order): JsonResponse
    {
        $user = JWTAuth::user();

        if (! $order->isOwnedBy($user)) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        if (! $order->isCancellable()) {
            return response()->json([
                'message' => 'This order cannot be cancelled. It is already being processed.',
            ], 409);
        }

        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $prevStatus = $order->status;

        
        $order->update([
            'status'              => Order::STATUS_CANCELLED,
            'cancelled_at'        => now(),
            'cancellation_reason' => $request->reason,
        ]);

        OrderStatusLog::create([
            'order_id'    => $order->id,
            'changed_by'  => $user->id,
            'from_status' => $prevStatus,
            'to_status'   => Order::STATUS_CANCELLED,
            'note'        => $request->reason ?? 'Cancelled by customer',
            'created_at'  => now(),
        ]);

        dispatch(new SendOrderNotification($order->id, 'order_cancelled'));

        return response()->json(['message' => 'Order cancelled successfully', 'order' => $order]);
    }

    // ── Helpers ────────────────────────────────────────────────────────
    private function calculateDeliveryFee(float $subtotal): float
    {
        $free = (float) config('app.free_delivery_above', 500);
        $fee  = (float) config('app.delivery_fee', 50);
        return $subtotal >= $free ? 0 : $fee;
    }

    private function orderResource(Order $order): array
    {
        return [
            'id'                    => $order->id,
            'status'                => $order->status,
            'payment_method'        => $order->payment_method,
            'payment_status'        => $order->payment_status,
            'customer_name'         => $order->customer_name,
            'phone'                 => $order->phone,
            'delivery_address'      => $order->delivery_address,
            'subtotal'              => (float) $order->subtotal,
            'delivery_fee'          => (float) $order->delivery_fee,
            'discount'              => (float) $order->discount,
            'total_amount'          => (float) $order->total_amount,
            'notes'                 => $order->notes,
            'estimated_delivery_at' => $order->estimated_delivery_at?->toISOString(),
            'delivered_at'          => $order->delivered_at?->toISOString(),
            'created_at'            => $order->created_at->toISOString(),
            'items'                 => $order->items->map(fn ($i) => [
                'id'                   => $i->id,
                'product_id'           => $i->product_id,
                'product_name'         => $i->product_name,
                'quantity'             => $i->quantity,
                'price'                => (float) $i->price,
                'subtotal'             => (float) $i->subtotal,
                'special_instructions' => $i->special_instructions,
                'product'              => $i->product ? [
                    'id'        => $i->product->id,
                    'name'      => $i->product->name,
                    'image_url' => $i->product->image_url,
                ] : null,
            ]),
            'payment' => $order->payment ? [
                'method'         => $order->payment->method,
                'verified'       => $order->payment->verified,
                'screenshot_url' => $order->payment->screenshot_url,
                'reference'      => $order->payment->reference,
                'verified_at'    => $order->payment->verified_at?->toISOString(),
            ] : null,
        ];
    }
}