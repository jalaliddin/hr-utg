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
        Schema::table('daily_attendance', function (Blueprint $table) {
            // Ishda yo'q soatlar
            $table->decimal('sick_hours', 5, 2)->default(0);
            $table->decimal('trip_hours', 5, 2)->default(0);
            $table->decimal('vacation_hours', 5, 2)->default(0);
            $table->decimal('maternity_hours', 5, 2)->default(0);
            $table->decimal('childcare_hours', 5, 2)->default(0);
            $table->decimal('study_hours', 5, 2)->default(0);
            $table->decimal('admin_leave_hours', 5, 2)->default(0);

            // Ishlangan soat turlari
            $table->decimal('actual_hours', 5, 2)->default(0);
            $table->decimal('overtime_hours', 5, 2)->default(0);
            $table->decimal('evening_hours', 5, 2)->default(0);
            $table->decimal('night_hours', 5, 2)->default(0);
            $table->decimal('holiday_hours', 5, 2)->default(0);

            // Asosiy tabel kodi
            $table->string('primary_code', 10)->nullable();

            // Yig'indi
            $table->decimal('total_work_hours', 5, 2)->default(0);
            $table->decimal('total_absent_hours', 5, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('daily_attendance', function (Blueprint $table) {
            $table->dropColumn([
                'sick_hours', 'trip_hours', 'vacation_hours', 'maternity_hours',
                'childcare_hours', 'study_hours', 'admin_leave_hours',
                'actual_hours', 'overtime_hours', 'evening_hours', 'night_hours', 'holiday_hours',
                'primary_code', 'total_work_hours', 'total_absent_hours',
            ]);
        });
    }
};
