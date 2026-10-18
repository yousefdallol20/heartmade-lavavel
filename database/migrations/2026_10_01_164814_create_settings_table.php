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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('settingName')
                ->default('Heart Made Zone');
            $table->string('settingEmail')
                ->default('hello@heartmadezone.com');
            $table->string('settingLocation')
                ->default('Amman, Jordan');
            $table->text('settingInstagram')->nullable();
            $table->text('settingFacebook')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
