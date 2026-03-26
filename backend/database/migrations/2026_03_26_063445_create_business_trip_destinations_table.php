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
        Schema::create('business_trip_destinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained();
            $table->integer('order_index')->default(0);
            $table->date('arrival_date')->nullable();
            $table->date('departure_date')->nullable();
            $table->string('arrival_signed_by')->nullable();
            $table->string('departure_signed_by')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('device_id')->nullable()->constrained('hikvision_devices')->nullOnDelete();
            $table->enum('push_status', ['pending', 'pushed', 'failed', 'offline', 'skipped'])->default('pending');
            $table->timestamp('pushed_at')->nullable();
            $table->text('push_error')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamp('retry_after')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_trip_destinations');
    }
};
