<?php

namespace App\Services;

use App\Models\AttendanceEntry;
use App\Models\AttendanceLog;
use App\Models\Employee;
use Carbon\Carbon;

class AutoAttendanceService
{
    private const TZ = 'Asia/Tashkent';

    /**
     * Attendance_logs dan attendance_entries ga Я kodini avtomatik yozadi.
     * Faqat source='manual' bo'lmagan yoki mavjud bo'lmagan kunlar uchun.
     */
    public function processLogs(int $organizationId, Carbon $from, Carbon $to): void
    {
        $employees = Employee::query()
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->get(['id', 'organization_id']);

        $current = $from->copy()->startOfDay();

        while ($current->lte($to)) {
            $date = $current->toDateString();

            foreach ($employees as $employee) {
                $this->processEmployeeDay($employee->id, $employee->organization_id, $date);
            }

            $current->addDay();
        }
    }

    /**
     * Bitta xodim uchun bitta kun: attendance_logs dan Я kodi yaratadi.
     * Agar o'sha kun uchun manual yozuv mavjud bo'lsa, o'tkazib yuboradi.
     */
    public function processEmployeeDay(int $employeeId, int $organizationId, string $date): void
    {
        // Agar manual yozuv mavjud bo'lsa — o'zgartirilmaydi
        $hasManual = AttendanceEntry::query()
            ->where('employee_id', $employeeId)
            ->whereDate('work_date', $date)
            ->where('source', 'manual')
            ->whereNull('deleted_at')
            ->exists();

        if ($hasManual) {
            return;
        }

        $logs = AttendanceLog::query()
            ->where('employee_id', $employeeId)
            ->whereDate('event_time', $date)
            ->orderBy('event_time')
            ->get();

        if ($logs->isEmpty()) {
            // Log yo'q — Я yozuvi o'chiriladi (agar avval yaratilgan bo'lsa)
            AttendanceEntry::query()
                ->where('employee_id', $employeeId)
                ->whereDate('work_date', $date)
                ->where('source', 'device')
                ->delete();

            return;
        }

        $firstEntry = $logs->where('event_type', 'entry')->sortBy('event_time')->first()
            ?? $logs->sortBy('event_time')->first();

        $lastExit = $logs->where('event_type', 'exit')->sortByDesc('event_time')->first()
            ?? $logs->sortByDesc('event_time')->last();

        $hours = 0.0;

        if ($firstEntry && $lastExit && $firstEntry->id !== $lastExit->id) {
            $minutes = (int) $firstEntry->event_time->diffInMinutes($lastExit->event_time);
            $hours = round($minutes / 60, 2);
        }

        AttendanceEntry::withTrashed()
            ->where('employee_id', $employeeId)
            ->whereDate('work_date', $date)
            ->where('source', 'device')
            ->forceDelete();

        AttendanceEntry::create([
            'employee_id' => $employeeId,
            'organization_id' => $organizationId,
            'work_date' => $date,
            'code' => 'Я',
            'hours' => $hours > 0 ? $hours : null,
            'days' => null,
            'source' => 'device',
            'created_by' => null,
        ]);
    }
}
