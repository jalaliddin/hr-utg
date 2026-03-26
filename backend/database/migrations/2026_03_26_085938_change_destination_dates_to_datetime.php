<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_trip_destinations', function (Blueprint $table) {
            $table->dateTime('arrival_date')->nullable()->change();
            $table->dateTime('departure_date')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('business_trip_destinations', function (Blueprint $table) {
            $table->date('arrival_date')->nullable()->change();
            $table->date('departure_date')->nullable()->change();
        });
    }
};
