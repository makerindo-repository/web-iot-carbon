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
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string('series');
            $table->string('name')->nullable();
            $table->string('image')->nullable();
            $table->date('installation_date')->nullable();
            $table->enum('tipe_koneksi', ['wifi', 'lora', 'gsm'])->default('wifi');
            $table->string('wifi_ssid')->nullable();
            $table->string('wifi_password')->nullable();
            $table->string('lora_id')->nullable();
            $table->string('lora_channel')->nullable();
            $table->string('lora_net_id')->nullable();
            $table->string('lora_key')->nullable();
            $table->string('gsm_provider')->nullable();
            $table->string('gsm_nomor_kartu')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
