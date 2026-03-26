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
        Schema::create('monthly_tabel', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained();
            $table->foreignId('organization_id')->constrained();
            $table->year('year');
            $table->tinyInteger('month');

            // Ishda yo'q (kun)
            $table->decimal('sick_days', 5, 2)->default(0);
            $table->decimal('trip_days', 5, 2)->default(0);
            $table->decimal('vacation_days', 5, 2)->default(0);
            $table->decimal('maternity_days', 5, 2)->default(0);
            $table->decimal('childcare_days', 5, 2)->default(0);
            $table->decimal('study_days', 5, 2)->default(0);
            $table->decimal('admin_leave_days', 5, 2)->default(0);

            // Ishlangan (soat)
            $table->decimal('actual_hours', 6, 2)->default(0);
            $table->decimal('overtime_hours', 5, 2)->default(0);
            $table->decimal('evening_hours', 5, 2)->default(0);
            $table->decimal('night_hours', 5, 2)->default(0);
            $table->decimal('holiday_hours', 5, 2)->default(0);

            // Yig'indi
            $table->integer('work_days_count')->default(0);
            $table->integer('calendar_days')->default(0);
            $table->decimal('total_hours', 6, 2)->default(0);

            $table->enum('status', ['draft', 'submitted', 'approved'])->default('draft');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();
            $table->unique(['employee_id', 'year', 'month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monthly_tabel');
    }
};
