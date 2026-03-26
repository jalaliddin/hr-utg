<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('destination');
            $table->string('purpose');
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('days_count');
            $table->enum('transport', ['car', 'train', 'plane', 'bus', 'other'])->nullable();
            $table->decimal('daily_allowance', 10, 2)->nullable();
            $table->decimal('total_amount', 10, 2)->nullable();
            $table->text('description')->nullable();
            $table->string('order_number')->nullable();
            $table->date('order_date')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'completed'])->default('pending');
            $table->text('reject_reason')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->json('attachments')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_trips');
    }
};
