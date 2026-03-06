<?php
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void {
        Schema::create('categories', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->string('icon')->nullable();
            $t->softDeletes();
            $t->timestamps();
        });
    }
    public function down(): void { 
        Schema::dropIfExists('categories'); 
    }
};
