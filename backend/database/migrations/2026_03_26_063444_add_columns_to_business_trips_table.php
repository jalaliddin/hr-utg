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
            $table->string('certificate_number')->unique()->nullable()->after('id');
            $table->integer('certificate_serial')->nullable()->after('certificate_number');
            $table->string('certificate_year', 4)->nullable()->after('certificate_serial');

            $table->integer('extension_days')->default(0)->after('end_date');
            $table->date('extended_end_date')->nullable()->after('extension_days');
            $table->string('extension_order_number')->nullable()->after('extended_end_date');
            $table->date('extension_order_date')->nullable()->after('extension_order_number');
            $table->text('extension_reason')->nullable()->after('extension_order_date');

            $table->string('passport_series')->nullable()->after('extension_reason');
            $table->string('service_id_number')->nullable()->after('passport_series');

            $table->enum('device_push_status', ['pending', 'pushed', 'failed', 'partial', 'offline'])
                ->default('pending')->after('service_id_number');
            $table->timestamp('device_pushed_at')->nullable()->after('device_push_status');
            $table->json('device_push_log')->nullable()->after('device_pushed_at');

            $table->string('pdf_path')->nullable()->after('device_push_log');
            $table->timestamp('pdf_generated_at')->nullable()->after('pdf_path');
        });
    }

    public function down(): void
    {
        Schema::table('business_trips', function (Blueprint $table) {
            $table->dropColumn([
                'certificate_number', 'certificate_serial', 'certificate_year',
                'extension_days', 'extended_end_date', 'extension_order_number',
                'extension_order_date', 'extension_reason',
                'passport_series', 'service_id_number',
                'device_push_status', 'device_pushed_at', 'device_push_log',
                'pdf_path', 'pdf_generated_at',
            ]);
        });
    }
};
