<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            // order_id as foreign key to orders
            $table->foreignId('order_id')
                  ->constrained('orders')
                  ->cascadeOnDelete();

            // payment method
            $table->enum('method', ['jazzcash','easypaisa','bank_transfer','cod'])->nullable();

            // optional payment proof
            $table->string('screenshot_url')->nullable();
            $table->string('reference')->nullable();

            // verification
            $table->boolean('verified')->default(false);
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->timestamp('verified_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('payments');
    }
};
