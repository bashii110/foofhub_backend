<?php
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void {
        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->foreignId('category_id')->constrained()->onDelete('cascade');
            $t->string('name');
            $t->text('description')->nullable();
            $t->decimal('price', 10, 2);
            $t->string('image_url')->nullable();
            $t->integer('preparation_time')->default(20);
            $t->integer('calories')->default(0);
            $t->json('ingredients')->default('[]');
            $t->boolean('is_popular')->default(false);
            $t->boolean('is_available')->default(true);
            $t->softDeletes();
            $t->timestamps();
        });
    }
    public function down(): void { 
        Schema::dropIfExists('products'); 
    }
};
