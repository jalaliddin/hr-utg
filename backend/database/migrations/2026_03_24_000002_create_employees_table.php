<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('employee_id')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('middle_name')->nullable();
            $table->string('position');
            $table->string('department')->nullable();
            $table->string('phone')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('hikvision_card_no')->nullable();
            $table->integer('hikvision_person_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->date('hired_at')->nullable();
            $table->date('fired_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
