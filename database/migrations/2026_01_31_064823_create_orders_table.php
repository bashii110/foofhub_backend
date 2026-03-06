<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $t) {
            $t->id();

            $t->foreignId('user_id')
                ->constrained()
                ->onDelete('cascade');

            // ✅ added
            $t->string('customer_name');

            $t->string('phone', 20);
            $t->text('delivery_address');

            $t->decimal('subtotal', 10, 2);
            $t->decimal('delivery_fee', 10, 2)->default(0);
            $t->decimal('total_amount', 10, 2);

            // ✅ added
            $t->enum('payment_method', ['cod', 'card', 'wallet'])
                ->default('cod');

            // ✅ added
            $t->enum('payment_status', ['pending', 'paid', 'failed'])
                ->default('pending');

            $t->enum('status', [
                'pending_payment',
                'pending_verification',
                'verified',
                'preparing',
                'out_for_delivery',
                'delivered',
                'cancelled',
            ])->default('pending_payment');

            // ✅ added
            $t->text('notes')->nullable();

            $t->text('admin_notes')->nullable();

            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
