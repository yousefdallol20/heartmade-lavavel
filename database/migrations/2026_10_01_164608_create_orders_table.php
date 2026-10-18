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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('customer_name');
            $table->string('customer_phone', 40)->nullable();
            $table->string('customer_email')->nullable();
            $table->text('shipping_address')->nullable();
            $table->string('payment_method', 100)
                ->default('Cash on Delivery');

            $table->enum('status', [
                'pending',
                'confirmed',
                'shipped',
                'delivered',
                'cancelled',
            ])->default('pending');

            $table->decimal('subtotal', 12, 2)->default(0); // سعر كلمنج لحاله
            $table->decimal('total', 12, 2)->default(0); // السعر الاجمالي للطلب
            $table->timestamp('delivered_at')->nullable(); // تاريخ تسلم الطلب
            $table->timestamps();

            $table->index(['status', 'delivered_at']); // بحث وفلترة
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
