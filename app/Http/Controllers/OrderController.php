<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    /**
     * Create a new order
     */
    public function store(Request $request)
    {
        // Log the incoming request for debugging
        Log::info('Order creation attempt', ['data' => $request->all()]);

        try {
            $validated = $request->validate([
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|integer|exists:products,id',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.price' => 'nullable|numeric',
                'items.*.special_instructions' => 'nullable|string',
                'delivery_address' => 'required|string',
                'payment_method' => 'required|string',
                'phone' => 'nullable|string',
                'customer_name' => 'nullable|string',
                'notes' => 'nullable|string',
                'subtotal' => 'nullable|numeric',
                'delivery_fee' => 'nullable|numeric',
                'total_amount' => 'nullable|numeric',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation failed', ['errors' => $e->errors()]);
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Get authenticated user
            $user = Auth::user();
            
            if (!$user) {
                Log::warning('Unauthenticated order attempt');
                return response()->json([
                    'message' => 'Unauthenticated',
                ], 401);
            }

            Log::info('Creating order for user', ['user_id' => $user->id]);

            // Calculate totals
            $subtotal = 0;
            foreach ($validated['items'] as $item) {
                $product = Product::find($item['product_id']);
                
                if (!$product) {
                    throw new \Exception("Product not found: " . $item['product_id']);
                }
                
                $subtotal += $product->price * $item['quantity'];
            }

            $deliveryFee = $validated['delivery_fee'] ?? 50.0;
            $totalAmount = $subtotal + $deliveryFee;

            Log::info('Order totals calculated', [
                'subtotal' => $subtotal,
                'delivery_fee' => $deliveryFee,
                'total' => $totalAmount,
            ]);

            // Create the order
            $order = Order::create([
                'user_id' => $user->id,
                'delivery_address' => $validated['delivery_address'],
                'phone' => $validated['phone'] ?? $user->phone ?? null,
                'customer_name' => $validated['customer_name'] ?? $user->name,
                'notes' => $validated['notes'] ?? null,
                'payment_method' => $validated['payment_method'],
                'status' => 'pending',
                'payment_status' => $validated['payment_method'] === 'cod' ? 'pending' : 'pending',
                'subtotal' => $subtotal,
                'delivery_fee' => $deliveryFee,
                'total_amount' => $totalAmount,
            ]);

            Log::info('Order created', ['order_id' => $order->id]);

            // Create order items
            foreach ($validated['items'] as $item) {
                $product = Product::find($item['product_id']);
                
                if (!$product) {
                    throw new \Exception("Product not found during item creation: " . $item['product_id']);
                }
                
                $orderItem = OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'price' => $product->price,
                    'special_instructions' => $item['special_instructions'] ?? null,
                ]);

                Log::info('Order item created', [
                    'order_item_id' => $orderItem->id,
                    'product_id' => $item['product_id'],
                ]);
            }

            DB::commit();

            Log::info('Order completed successfully', ['order_id' => $order->id]);

            return response()->json([
                'message' => 'Order placed successfully',
                'order' => [
                    'id' => $order->id,
                    'status' => $order->status,
                    'payment_status' => $order->payment_status,
                    'total_amount' => $order->total_amount,
                ],
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            // Log the full error
            Log::error('Order creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id(),
            ]);
            
            return response()->json([
                'message' => 'Failed to create order',
                'error' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null,
            ], 500);
        }
    }

    /**
     * Get user's orders
     */
    public function index()
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $orders = Order::with(['items.product', 'user'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'status' => $order->status,
                    'payment_status' => $order->payment_status,
                    'delivery_address' => $order->delivery_address,
                    'total_amount' => $order->total_amount,
                    'subtotal' => $order->subtotal,
                    'delivery_fee' => $order->delivery_fee,
                    'created_at' => $order->created_at->format('Y-m-d H:i'),
                    'proof_image' => $order->proof_image ? asset('storage/' . $order->proof_image) : null,
                    'items' => $order->items->map(function ($item) {
                        return [
                            'product' => [
                                'id' => $item->product->id,
                                'name' => $item->product->name,
                                'image' => $item->product->image ? asset('storage/' . $item->product->image) : null,
                            ],
                            'quantity' => $item->quantity,
                            'price' => $item->price,
                        ];
                    }),
                ];
            });

        return response()->json(['data' => $orders]);
    }

    /**
     * Get single order
     */
    public function show(Order $order)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Ensure user owns this order
        if ($order->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $order->load(['items.product']);

        return response()->json([
            'order' => [
                'id' => $order->id,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'delivery_address' => $order->delivery_address,
                'total_amount' => $order->total_amount,
                'subtotal' => $order->subtotal,
                'delivery_fee' => $order->delivery_fee,
                'created_at' => $order->created_at->format('Y-m-d H:i'),
                'proof_image' => $order->proof_image ? asset('storage/' . $order->proof_image) : null,
                'items' => $order->items->map(function ($item) {
                    return [
                        'product' => [
                            'id' => $item->product->id,
                            'name' => $item->product->name,
                        ],
                        'quantity' => $item->quantity,
                        'price' => $item->price,
                    ];
                }),
            ],
        ]);
    }

    /**
     * Upload payment proof
     */
    public function uploadPaymentProof(Request $request, Order $order)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Ensure user owns this order
        if ($order->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'proof_image' => 'required|image|max:5120', // 5MB max
            'reference' => 'nullable|string',
        ]);

        // Store the image
        $path = $request->file('proof_image')->store('payment-proofs', 'public');

        // Update order
        $order->update([
            'proof_image' => $path,
            'payment_status' => 'pending_verification',
        ]);

        return response()->json([
            'message' => 'Payment proof uploaded successfully',
            'proof_image_url' => asset('storage/' . $path),
        ]);
    }

    /**
     * Cancel order
     */
    public function cancel(Order $order)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Ensure user owns this order
        if ($order->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Can only cancel if pending
        if (!in_array($order->status, ['pending', 'pending_verification'])) {
            return response()->json([
                'message' => 'Cannot cancel order at this stage',
            ], 400);
        }

        $order->update(['status' => 'cancelled']);

        return response()->json(['message' => 'Order cancelled successfully']);
    }
}