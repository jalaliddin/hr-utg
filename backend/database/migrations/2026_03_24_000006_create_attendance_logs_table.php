<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->constrained('hikvision_devices')->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('hikvision_person_id')->nullable();
            $table->string('hikvision_card_no')->nullable();
            $table->enum('event_type', ['entry', 'exit', 'unknown'])->default('unknown');
            $table->timestamp('event_time');
            $table->string('door_name')->nullable();
            $table->json('raw_data')->nullable();
            $table->boolean('is_processed')->default(false);
            $table->timestamps();
            $table->index(['employee_id', 'event_time']);
            $table->index(['organization_id', 'event_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_logs');
    }
};
