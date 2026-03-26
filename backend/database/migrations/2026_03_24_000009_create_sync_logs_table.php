<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('hikvision_devices')->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->timestamp('sync_started_at');
            $table->timestamp('sync_finished_at')->nullable();
            $table->integer('records_fetched')->default(0);
            $table->integer('records_new')->default(0);
            $table->integer('records_duplicate')->default(0);
            $table->enum('status', ['running', 'success', 'failed', 'partial'])->default('running');
            $table->text('error_message')->nullable();
            $table->json('params')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_logs');
    }
};
