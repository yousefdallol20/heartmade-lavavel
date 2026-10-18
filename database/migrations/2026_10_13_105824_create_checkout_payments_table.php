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
        Schema::create('checkout_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')
                ->unique()
                ->constrained()
                ->restrictOnDelete();
            $table->uuid('reference')->unique();
            $table->string('checkout_token_hash', 64)->unique();
            $table->string('payload_hash', 64);
            $table->string('environment', 10);
            $table->string('status', 30)->default('creating');
            $table->string('order_currency', 3)->default('JOD');
            $table->decimal('order_amount', 12, 2);
            $table->string('payment_currency', 3)->default('USD');
            $table->decimal('payment_amount', 14, 2);
            $table->decimal('usd_to_jod', 18, 8);
            $table->uuid('create_request_id')->unique();
            $table->uuid('capture_request_id')->unique();
            $table->string('paypal_order_id', 64)
                ->nullable()
                ->unique();
            $table->string('paypal_capture_id', 64)
                ->nullable()
                ->unique();
            $table->text('approval_url')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('capture_attempted_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('stock_released_at')->nullable();
            $table->timestamp('notification_queued_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->string('last_error_code', 100)->nullable();
            $table->timestamps();
            $table->index(['status', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('checkout_payments');
    }
};
