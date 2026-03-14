<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendOrderNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int    $tries   = 3;
    public int    $timeout = 30;

    private array $messages = [
        'order_placed'      => ['title' => 'Order Placed! 🎉',      'body' => 'Your order #{id} has been placed successfully.'],
        'payment_verified'  => ['title' => 'Payment Verified ✅',    'body' => 'Your payment for order #{id} has been verified. We\'re preparing your food!'],
        'payment_rejected'  => ['title' => 'Payment Issue ⚠️',      'body' => 'Your payment for order #{id} was rejected. Please re-upload your proof.'],
        'proof_uploaded'    => ['title' => 'Proof Received 📸',      'body' => 'Payment proof received for order #{id}. Admin is reviewing.'],
        'status_updated'    => ['title' => 'Order Update 🚴',        'body' => 'Your order #{id} status has been updated.'],
        'order_cancelled'   => ['title' => 'Order Cancelled ❌',     'body' => 'Order #{id} has been cancelled.'],
    ];

    public function __construct(
        private int    $orderId,
        private string $event
    ) {}

    public function handle(): void
    {
        $order = Order::with('user')->find($this->orderId);

        if (! $order) {
            Log::warning('SendOrderNotification: Order not found', ['order_id' => $this->orderId]);
            return;
        }

        $user = $order->user;

        if (! $user?->fcm_token) {
            return; // User has no push token registered
        }

        $template = $this->messages[$this->event] ?? null;
        if (! $template) {
            return;
        }

        $title = $template['title'];
        $body  = str_replace('{id}', $order->id, $template['body']);

        // TODO: Integrate FCM HTTP v1 API here
        // Example using Firebase Admin SDK or HTTP request:
        //
        // Http::withHeaders(['Authorization' => 'Bearer ' . $this->getAccessToken()])
        //     ->post('https://fcm.googleapis.com/v1/projects/{project_id}/messages:send', [
        //         'message' => [
        //             'token' => $user->fcm_token,
        //             'notification' => ['title' => $title, 'body' => $body],
        //             'data' => ['order_id' => (string) $order->id, 'event' => $this->event],
        //         ],
        //     ]);

        Log::info('Push notification dispatched', [
            'user_id'  => $user->id,
            'order_id' => $this->orderId,
            'event'    => $this->event,
            'title'    => $title,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SendOrderNotification failed', [
            'order_id' => $this->orderId,
            'event'    => $this->event,
            'error'    => $exception->getMessage(),
        ]);
    }
}