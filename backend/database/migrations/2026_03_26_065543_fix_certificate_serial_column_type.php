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
        Schema::table('business_trips', function (Blueprint $table) {
            // certificate_serial was incorrectly defined as integer; change to string to store "001/26" format
            $table->string('certificate_serial', 20)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('business_trips', function (Blueprint $table) {
            $table->integer('certificate_serial')->nullable()->change();
        });
    }
};
