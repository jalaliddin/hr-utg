<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hikvision_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('ip_address');
            $table->integer('port')->default(80);
            $table->string('username')->default('admin');
            $table->string('password');
            $table->string('serial_number')->nullable();
            $table->enum('status', ['online', 'offline', 'unknown'])->default('unknown');
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->json('device_info')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hikvision_devices');
    }
};
