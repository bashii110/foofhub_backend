<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('password_reset_otps', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('email')->index();

            // Store the hashed OTP, not the plain OTP
            $table->string('otp');

            $table->timestamp('expires_at');

            $table->boolean('used')
                ->default(false)
                ->index();

            $table->timestamps();

            $table->index([
                'user_id',
                'used',
                'expires_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_otps');
    }
};