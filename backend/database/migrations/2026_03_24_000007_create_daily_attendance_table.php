<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->time('first_entry')->nullable();
            $table->time('last_exit')->nullable();
            $table->integer('work_minutes')->default(0);
            $table->enum('status', [
                'present',
                'absent',
                'late',
                'half_day',
                'business_trip',
                'leave',
                'holiday',
            ])->default('absent');
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_attendance');
    }
};
