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
        Schema::create('pending_device_deletions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('hikvision_devices')->cascadeOnDelete();
            $table->string('hikvision_person_id');
            $table->timestamps();

            $table->unique(['device_id', 'hikvision_person_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pending_device_deletions');
    }
};
