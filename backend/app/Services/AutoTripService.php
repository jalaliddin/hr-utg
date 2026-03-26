<?php

namespace App\Services;

use App\Models\AttendanceEntry;
use App\Models\BusinessTrip;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class AutoTripService
{
    /**
     * Safari tasdiqlanganida xodim uchun К kodli yozuvlar yaratadi.
     * Agar o'sha kunga manual yozuv mavjud bo'lsa — o'tkazib yuboradi.
     */
    public function createTripEntries(BusinessTrip $trip): void
    {
        $trip->loadMissing('employee');
        $employee = $trip->employee;

        if (! $employee) {
            return;
        }

        $start = Carbon::parse($trip->start_date)->toDateString();
        $end = Carbon::parse($trip->extended_end_date ?? $trip->end_date)->toDateString();

        foreach (CarbonPeriod::create($start, $end) as $date) {
            $dateStr = $date->toDateString();

            // Manual yozuv bo'lsa — o'tkazib yuboramiz
            $hasManual = AttendanceEntry::query()
                ->where('employee_id', $employee->id)
                ->whereDate('work_date', $dateStr)
                ->where('source', 'manual')
                ->whereNull('deleted_at')
                ->exists();

            if ($hasManual) {
                continue;
            }

            // Device yozuvini o'chiramiz (К ustunlik qiladi)
            AttendanceEntry::withTrashed()
                ->where('employee_id', $employee->id)
                ->whereDate('work_date', $dateStr)
                ->where('source', 'device')
                ->forceDelete();

            // К yozuvini yaratamiz (mavjud auto_trip yozuv bo'lsa yangilaymiz)
            AttendanceEntry::withTrashed()
                ->where('employee_id', $employee->id)
                ->whereDate('work_date', $dateStr)
                ->where('source', 'auto_trip')
                ->forceDelete();

            AttendanceEntry::create([
                'employee_id' => $employee->id,
                'organization_id' => $employee->organization_id,
                'work_date' => $dateStr,
                'code' => 'К',
                'hours' => 8,
                'days' => 1,
                'source' => 'auto_trip',
                'created_by' => null,
                'note' => "Safari #{$trip->id}",
            ]);
        }
    }

    /**
     * Safari rad etilganda yoki bekor qilinganda К yozuvlarini o'chiradi.
     */
    public function removeTripEntries(BusinessTrip $trip): void
    {
        $trip->loadMissing('employee');
        $employee = $trip->employee;

        if (! $employee) {
            return;
        }

        $start = Carbon::parse($trip->start_date)->toDateString();
        $end = Carbon::parse($trip->extended_end_date ?? $trip->end_date)->toDateString();

        AttendanceEntry::query()
            ->where('employee_id', $employee->id)
            ->where('source', 'auto_trip')
            ->whereBetween('work_date', [$start, $end])
            ->delete();
    }

    /**
     * Safari uzaytirilganda yangi kunlarga К yozuvlar qo'shadi.
     */
    public function extendTripEntries(BusinessTrip $trip, string $oldEndDate): void
    {
        $trip->loadMissing('employee');
        $employee = $trip->employee;

        if (! $employee) {
            return;
        }

        $newEnd = Carbon::parse($trip->extended_end_date ?? $trip->end_date)->toDateString();
        $afterOldEnd = Carbon::parse($oldEndDate)->addDay()->toDateString();

        if ($afterOldEnd > $newEnd) {
            return;
        }

        foreach (CarbonPeriod::create($afterOldEnd, $newEnd) as $date) {
            $dateStr = $date->toDateString();

            $hasManual = AttendanceEntry::query()
                ->where('employee_id', $employee->id)
                ->whereDate('work_date', $dateStr)
                ->where('source', 'manual')
                ->whereNull('deleted_at')
                ->exists();

            if ($hasManual) {
                continue;
            }

            AttendanceEntry::withTrashed()
                ->where('employee_id', $employee->id)
                ->whereDate('work_date', $dateStr)
                ->where('source', 'device')
                ->forceDelete();

            AttendanceEntry::withTrashed()
                ->where('employee_id', $employee->id)
                ->whereDate('work_date', $dateStr)
                ->where('source', 'auto_trip')
                ->forceDelete();

            AttendanceEntry::create([
                'employee_id' => $employee->id,
                'organization_id' => $employee->organization_id,
                'work_date' => $dateStr,
                'code' => 'К',
                'hours' => 8,
                'days' => 1,
                'source' => 'auto_trip',
                'created_by' => null,
                'note' => "Safari #{$trip->id} (uzaytirilgan)",
            ]);
        }
    }
}
