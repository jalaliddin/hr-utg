<?php

namespace App\Services;

use App\Models\BusinessTrip;
use App\Models\DailyAttendance;
use Carbon\Carbon;

class BusinessTripAttendanceService
{
    /**
     * Tasdiqlangan safari kunlarini tabelda "business_trip" sifatida belgilaydi.
     * Har kuni uchun updateOrCreate — mavjud yozuv bo'lsa yangilaydi.
     */
    public function markTripDays(BusinessTrip $trip): void
    {
        $trip->loadMissing('employee');
        $employee = $trip->employee;

        if (! $employee) {
            return;
        }

        $start = Carbon::parse($trip->start_date)->startOfDay();
        $end   = Carbon::parse($trip->extended_end_date ?? $trip->end_date)->startOfDay();

        $current = $start->copy();

        while ($current->lte($end)) {
            DailyAttendance::updateOrCreate(
                [
                    'employee_id' => $employee->id,
                    'work_date'   => $current->toDateString(),
                ],
                [
                    'organization_id' => $employee->organization_id,
                    'status'          => 'business_trip',
                    'work_minutes'    => 0,
                    'first_entry'     => null,
                    'last_exit'       => null,
                ]
            );

            $current->addDay();
        }
    }

    /**
     * Safari rad etilganda yoki bekor qilinganda tabel yozuvlarini o'chiradi
     * (keyingi sync qayta hisoblaydi).
     */
    public function removeTripDays(BusinessTrip $trip): void
    {
        $trip->loadMissing('employee');
        $employee = $trip->employee;

        if (! $employee) {
            return;
        }

        $start = Carbon::parse($trip->start_date)->toDateString();
        $end   = Carbon::parse($trip->extended_end_date ?? $trip->end_date)->toDateString();

        DailyAttendance::query()
            ->where('employee_id', $employee->id)
            ->where('status', 'business_trip')
            ->whereBetween('work_date', [$start, $end])
            ->delete();
    }

    /**
     * Safari yakunlanganda: qaytgan kundan keyingi kunlarni o'chiradi
     * (safari muddatidan oldin qaytgan holat).
     */
    public function handleTripCompletion(BusinessTrip $trip): void
    {
        $trip->loadMissing('employee');
        $employee = $trip->employee;

        if (! $employee) {
            return;
        }

        $returnedAt = $trip->returned_at
            ? Carbon::parse($trip->returned_at)->toDateString()
            : Carbon::parse($trip->extended_end_date ?? $trip->end_date)->toDateString();

        $end = Carbon::parse($trip->extended_end_date ?? $trip->end_date)->toDateString();

        // Qaytgan kundan keyingi kunlarni o'chiramiz
        if ($returnedAt < $end) {
            $afterReturn = Carbon::parse($returnedAt)->addDay()->toDateString();

            DailyAttendance::query()
                ->where('employee_id', $employee->id)
                ->where('status', 'business_trip')
                ->whereBetween('work_date', [$afterReturn, $end])
                ->delete();
        }
    }
}
