<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'orders',
            function (Blueprint $table) {
                $table
                    ->timestamp(
                        'customer_confirmation_queued_at'
                    )
                    ->nullable()
                    ->after('delivered_at');
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'orders',
            function (Blueprint $table) {
                $table->dropColumn(
                    'customer_confirmation_queued_at'
                );
            }
        );
    }
};
