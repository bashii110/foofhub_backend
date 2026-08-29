<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('password_reset_otps', function (Blueprint $table) {
            $table->string('reset_token')->nullable()->unique()->after('used');
            $table->timestamp('verified_at')->nullable()->after('reset_token');
        });
    }

    public function down(): void
    {
        Schema::table('password_reset_otps', function (Blueprint $table) {
            $table->dropUnique(['reset_token']);
            $table->dropColumn([
                'reset_token',
                'verified_at',
            ]);
        });
    }
};