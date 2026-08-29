<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('email');

            $table->string('otp');

            $table->timestamp('expires_at');

            $table->boolean('used')->default(false);

            $table->timestamps();

            $table->index(['email', 'otp']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_codes');
    }
};