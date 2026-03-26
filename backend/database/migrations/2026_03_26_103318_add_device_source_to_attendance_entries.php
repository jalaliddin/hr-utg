<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add 'device' to source enum
        DB::statement("ALTER TABLE attendance_entries MODIFY COLUMN source ENUM('manual','device','auto_trip','auto_leave','auto_holiday') NOT NULL DEFAULT 'manual'");

        // Make created_by nullable for system-generated entries
        Schema::table('attendance_entries', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE attendance_entries MODIFY COLUMN source ENUM('manual','auto_trip','auto_leave','auto_holiday') NOT NULL DEFAULT 'manual'");

        Schema::table('attendance_entries', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')->nullable(false)->change();
        });
    }
};
