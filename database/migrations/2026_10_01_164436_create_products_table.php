<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // الاسم
            $table->decimal('price', 12, 2); // السعر
            $table->unsignedInteger('stock')->nullable(); // الكمية
            $table->string('subtitle')->nullable(); // عنوان فرعي
            $table->string('category')->index(); // منتج
            $table->text('description')->nullable(); // وصف
            $table->text('image_url')->nullable(); // الصورة الاساسية للعرض
            $table->json('images')->nullable(); // الصورر الفرعية للمنتج
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
