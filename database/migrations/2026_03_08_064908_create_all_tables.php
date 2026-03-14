<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email')->unique();
            $t->string('password');
            $t->enum('role', ['user', 'admin', 'staff', 'rider'])->default('user')->index();
            $t->string('phone', 20)->nullable();
            $t->string('address')->nullable();
            $t->boolean('is_active')->default(true)->index();
            $t->string('profile_image')->nullable();
            $t->string('fcm_token')->nullable();
            $t->timestamp('email_verified_at')->nullable();
            $t->timestamp('last_login_at')->nullable();
            $t->rememberToken();
            $t->timestamps();
        });

        Schema::create('categories', function (Blueprint $t) {
            $t->id();
            $t->string('name', 100)->unique();
            $t->string('icon', 50)->nullable();
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
        });

        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->foreignId('category_id')->constrained()->onDelete('cascade');
            $t->string('name', 200);
            $t->text('description')->nullable();
            $t->decimal('price', 10, 2);
            $t->string('image_path')->nullable();
            $t->unsignedSmallInteger('preparation_time')->default(20);
            $t->unsignedSmallInteger('calories')->default(0);
            $t->json('ingredients');        // nullable by default
            $t->boolean('is_popular')->default(false)->index();
            $t->boolean('is_available')->default(true)->index();
            $t->unsignedInteger('stock')->nullable();
            $t->softDeletes();
            $t->timestamps();

            $t->index(['category_id', 'is_available']);
        });

        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->onDelete('cascade');
            $t->foreignId('rider_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('customer_name');
            $t->string('phone', 20);
            $t->text('delivery_address');
            $t->decimal('delivery_lat', 10, 7)->nullable();
            $t->decimal('delivery_lng', 10, 7)->nullable();
            $t->decimal('subtotal', 10, 2);
            $t->decimal('delivery_fee', 10, 2)->default(0);
            $t->decimal('discount', 10, 2)->default(0);
            $t->decimal('total_amount', 10, 2);
            $t->enum('payment_method', ['jazzcash', 'easypaisa', 'bank_transfer', 'cod'])->index();
            $t->enum('payment_status', ['pending', 'paid', 'failed', 'refunded'])->default('pending')->index();
            $t->enum('status', [
                'pending_payment',
                'pending_verification',
                'verified',
                'preparing',
                'out_for_delivery',
                'delivered',
                'cancelled',
            ])->default('pending_payment')->index();
            $t->text('notes')->nullable();
            $t->text('admin_notes')->nullable();
            $t->string('cancellation_reason')->nullable();
            $t->timestamp('estimated_delivery_at')->nullable();
            $t->timestamp('delivered_at')->nullable();
            $t->timestamp('cancelled_at')->nullable();
            $t->softDeletes();
            $t->timestamps();

            $t->index(['user_id', 'status']);
            $t->index(['created_at', 'status']);
        });

        Schema::create('order_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->onDelete('cascade');
            $t->foreignId('product_id')->constrained()->onDelete('cascade');
            $t->string('product_name');      // snapshot at time of order
            $t->unsignedSmallInteger('quantity');
            $t->decimal('price', 10, 2);    // snapshot price at time of order
            $t->text('special_instructions')->nullable();
            $t->timestamps();

            $t->index('order_id');
        });

        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->onDelete('cascade');
            $t->enum('method', ['jazzcash', 'easypaisa', 'bank_transfer', 'cod'])->nullable();
            $t->decimal('amount', 10, 2)->nullable();
            $t->string('screenshot_url')->nullable();
            $t->string('reference', 100)->nullable();
            $t->boolean('verified')->default(false)->index();
            $t->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('verified_at')->nullable();
            $t->text('rejection_reason')->nullable();
            $t->timestamps();
        });

        Schema::create('order_status_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->onDelete('cascade');
            $t->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('from_status', 50)->nullable();
            $t->string('to_status', 50);
            $t->text('note')->nullable();
            $t->timestamp('created_at')->useCurrent();

            $t->index('order_id');
        });

        // Cache & Jobs (required by Laravel)
        Schema::create('cache', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->mediumText('value');
            $t->integer('expiration')->index();
        });

        Schema::create('cache_locks', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->string('owner');
            $t->integer('expiration');
        });

        Schema::create('jobs', function (Blueprint $t) {
            $t->id();
            $t->string('queue')->index();
            $t->longText('payload');
            $t->unsignedTinyInteger('attempts');
            $t->unsignedInteger('reserved_at')->nullable();
            $t->unsignedInteger('available_at');
            $t->unsignedInteger('created_at');
        });

        Schema::create('failed_jobs', function (Blueprint $t) {
            $t->id();
            $t->string('uuid')->unique();
            $t->text('connection');
            $t->text('queue');
            $t->longText('payload');
            $t->longText('exception');
            $t->timestamp('failed_at')->useCurrent();
        });

        Schema::create('password_reset_tokens', function (Blueprint $t) {
            $t->string('email')->primary();
            $t->string('token');
            $t->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_logs');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('users');
        Schema::dropIfExists('cache');
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('password_reset_tokens');
    }
};