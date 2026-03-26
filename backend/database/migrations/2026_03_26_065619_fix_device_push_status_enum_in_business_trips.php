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
        \DB::statement("ALTER TABLE business_trips MODIFY device_push_status ENUM('pending','success','failed','partial','offline') NULL DEFAULT NULL");
        \DB::statement("ALTER TABLE business_trip_destinations MODIFY push_status ENUM('pending','success','failed','offline','skipped') NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        \DB::statement("ALTER TABLE business_trips MODIFY device_push_status ENUM('pending','pushed','failed','partial','offline') NOT NULL DEFAULT 'pending'");
        \DB::statement("ALTER TABLE business_trip_destinations MODIFY push_status ENUM('pending','pushed','failed','offline','skipped') NULL DEFAULT 'pending'");
    }
};
