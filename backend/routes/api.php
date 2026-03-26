<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AttendanceEntryController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BusinessTripController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\OrganizationController;
use App\Http\Controllers\Api\PublicHolidayController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WorkScheduleController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Auth
    Route::post('/login', [AuthController::class, 'login']);

    // Qurilma rasmlari — auth shart emas (log ID tasodifiy ULID)
    Route::get('/attendance/logs/{log}/picture', [AttendanceController::class, 'picture']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);

        // Dashboard
        Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
        Route::get('/dashboard/today', [DashboardController::class, 'today']);
        Route::get('/dashboard/trend', [DashboardController::class, 'trend']);

        // Organizations
        Route::apiResource('organizations', OrganizationController::class);
        Route::get('/organizations/{organization}/attendance', [OrganizationController::class, 'attendance']);

        // Departments & Positions
        Route::apiResource('departments', DepartmentController::class)->except(['show']);
        Route::get('/departments/{department}/positions', [DepartmentController::class, 'positions']);
        Route::post('/departments/{department}/positions', [DepartmentController::class, 'storePosition']);
        Route::put('/departments/{department}/positions/{position}', [DepartmentController::class, 'updatePosition']);
        Route::delete('/departments/{department}/positions/{position}', [DepartmentController::class, 'destroyPosition']);

        // Employees
        Route::apiResource('employees', EmployeeController::class);
        Route::post('/employees/sync-to-devices', [EmployeeController::class, 'syncToDevices']);
        Route::get('/employees/{employee}/attendance', [EmployeeController::class, 'attendance']);
        Route::get('/employees/{employee}/monthly-table', [EmployeeController::class, 'monthlyTable']);

        // Devices
        Route::apiResource('devices', DeviceController::class);
        Route::post('/devices/{device}/test', [DeviceController::class, 'testConnection']);
        Route::post('/devices/{device}/import-employees', [DeviceController::class, 'importEmployees']);
        Route::post('/devices/{device}/reconcile', [DeviceController::class, 'reconcileEmployees']);
        Route::post('/devices/{device}/sync', [DeviceController::class, 'manualSync']);
        Route::get('/devices/{device}/sync-logs', [DeviceController::class, 'syncLogs']);

        // Attendance
        Route::get('/attendance/daily', [AttendanceController::class, 'daily']);
        Route::get('/attendance/monthly', [AttendanceController::class, 'monthly']);
        Route::put('/attendance/{dailyAttendance}', [AttendanceController::class, 'update']);
        Route::get('/attendance/export', [AttendanceController::class, 'export']);

        // Tabel yozuvlari (qo'lda kiritish)
        Route::get('/attendance/entries', [AttendanceEntryController::class, 'index']);
        Route::post('/attendance/entries', [AttendanceEntryController::class, 'store']);
        Route::post('/attendance/entries/bulk', [AttendanceEntryController::class, 'bulkStore']);
        Route::put('/attendance/entries/{entry}', [AttendanceEntryController::class, 'update']);
        Route::delete('/attendance/entries/{entry}', [AttendanceEntryController::class, 'destroy']);

        // Oylik tabel
        Route::get('/attendance/tabel', [AttendanceEntryController::class, 'monthlyTabel']);
        Route::post('/attendance/calculate-monthly', [AttendanceEntryController::class, 'calculateMonthly']);
        Route::post('/attendance/tabel/approve', [AttendanceEntryController::class, 'approveTabel']);

        // Bayramlar
        Route::apiResource('holidays', PublicHolidayController::class)->except(['show']);

        // Business Trips
        Route::apiResource('business-trips', BusinessTripController::class);
        Route::post('/business-trips/{businessTrip}/approve', [BusinessTripController::class, 'approve']);
        Route::post('/business-trips/{businessTrip}/reject', [BusinessTripController::class, 'reject']);
        Route::post('/business-trips/{businessTrip}/complete', [BusinessTripController::class, 'complete']);
        Route::post('/business-trips/{businessTrip}/extend', [BusinessTripController::class, 'extendTrip']);
        Route::get('/business-trips/{businessTrip}/pdf', [BusinessTripController::class, 'generatePdf']);
        Route::get('/business-trips/{businessTrip}/push-status', [BusinessTripController::class, 'pushStatus']);
        Route::post('/business-trips/{businessTrip}/retry-push', [BusinessTripController::class, 'retryPush']);

        // Organization Directors
        Route::get('/organizations/{organization}/directors', [OrganizationController::class, 'directors']);
        Route::post('/organizations/{organization}/directors', [OrganizationController::class, 'storeDirector']);
        Route::put('/organizations/{organization}/directors/{director}', [OrganizationController::class, 'updateDirector']);
        Route::delete('/organizations/{organization}/directors/{director}', [OrganizationController::class, 'destroyDirector']);

        // Reports
        Route::get('/reports/monthly-table', [ReportController::class, 'monthlyTable']);
        Route::get('/reports/business-trips', [ReportController::class, 'businessTrips']);
        Route::get('/reports/summary', [ReportController::class, 'summary']);

        // Work Schedules
        Route::apiResource('work-schedules', WorkScheduleController::class)->except(['show']);

        // Users
        Route::apiResource('users', UserController::class)->except(['show']);

        // Sync
        Route::post('/sync/all', [SyncController::class, 'syncAll']);
        Route::get('/sync/status', [SyncController::class, 'status']);
    });
});
