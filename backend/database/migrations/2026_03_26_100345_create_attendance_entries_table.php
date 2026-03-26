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
        Schema::create('attendance_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');

            $table->date('work_date');
            $table->string('code', 10); // Б, К, О, Р, ЧБ, У, А, Я, С, В, Н, П
            $table->decimal('hours', 5, 2)->nullable();
            $table->decimal('days', 4, 2)->nullable();

            $table->text('note')->nullable();
            $table->string('document_number', 100)->nullable();
            $table->date('document_date')->nullable();
            $table->string('document_type', 50)->nullable();

            $table->enum('source', ['manual', 'auto_trip', 'auto_leave', 'auto_holiday'])->default('manual');

            $table->boolean('is_approved')->default(false);
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'work_date']);
            $table->index(['organization_id', 'work_date']);
            $table->index(['work_date', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_entries');
    }
};
