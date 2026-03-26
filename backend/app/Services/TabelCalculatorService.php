<?php

namespace App\Services;

use App\Models\AttendanceEntry;
use App\Models\AttendanceLog;
use App\Models\DailyAttendance;
use App\Models\Employee;
use App\Models\MonthlyTabel;
use App\Models\PublicHoliday;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class TabelCalculatorService
{
    /**
     * Bir oy uchun barcha xodimlar tabelini hisoblash va monthly_tabel ga saqlash.
     *
     * @return array{updated: int, employees: int}
     */
    public function calculate(int $organizationId, int $year, int $month): array
    {
        $employees = Employee::query()
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->get();

        $daysInMonth = Carbon::createFromDate($year, $month, 1)->daysInMonth;
        $holidays = $this->getHolidayDates($year, $month);

        // Oy uchun barcha attendance_entries
        $entries = AttendanceEntry::query()
            ->where('organization_id', $organizationId)
            ->whereYear('work_date', $year)
            ->whereMonth('work_date', $month)
            ->whereNull('deleted_at')
            ->get()
            ->groupBy('employee_id');

        // Oy uchun barcha daily_attendance (qurilmadan)
        $dailyAttendance = DailyAttendance::query()
            ->where('organization_id', $organizationId)
            ->whereYear('work_date', $year)
            ->whereMonth('work_date', $month)
            ->get()
            ->groupBy('employee_id');

        $updated = 0;

        foreach ($employees as $employee) {
            $empEntries = $entries->get($employee->id, collect());
            $empAttendance = $dailyAttendance->get($employee->id, collect());

            $totals = $this->computeMonthlyTotals(
                $employee,
                $year,
                $month,
                $daysInMonth,
                $empEntries,
                $empAttendance,
                $holidays
            );

            MonthlyTabel::updateOrCreate(
                [
                    'employee_id' => $employee->id,
                    'year' => $year,
                    'month' => $month,
                ],
                array_merge($totals, [
                    'organization_id' => $organizationId,
                    'calendar_days' => $daysInMonth,
                ])
            );

            $updated++;
        }

        return ['updated' => $updated, 'employees' => $employees->count()];
    }

    /**
     * Oylik tabel uchun kunlik ma'lumotlarni olish (qurilma + qo'lda kiritilganlar birlashtirilgan).
     *
     * @return array<int, array{entry: array|null, device: array|null, is_holiday: bool, is_weekend: bool}>
     */
    public function getDailyCells(int $employeeId, int $year, int $month): array
    {
        $daysInMonth = Carbon::createFromDate($year, $month, 1)->daysInMonth;
        $holidays = $this->getHolidayDates($year, $month);

        $entries = AttendanceEntry::query()
            ->where('employee_id', $employeeId)
            ->whereYear('work_date', $year)
            ->whereMonth('work_date', $month)
            ->whereNull('deleted_at')
            ->get()
            ->keyBy(fn ($e) => Carbon::parse($e->work_date)->day);

        $deviceData = DailyAttendance::query()
            ->where('employee_id', $employeeId)
            ->whereYear('work_date', $year)
            ->whereMonth('work_date', $month)
            ->get()
            ->keyBy(fn ($d) => Carbon::parse($d->work_date)->day);

        $cells = [];

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $date = Carbon::createFromDate($year, $month, $d);
            $entry = $entries->get($d);
            $device = $deviceData->get($d);

            $cells[$d] = [
                'entry' => $entry ? $this->formatEntry($entry) : null,
                'device' => $device ? $this->formatDevice($device) : null,
                'is_holiday' => in_array($d, $holidays),
                'is_weekend' => $date->isWeekend(),
            ];
        }

        return $cells;
    }

    /**
     * O'sha oy uchun bayram kunlarini (faqat kun raqamini) qaytaradi.
     *
     * @return list<int>
     */
    public function getHolidayDates(int $year, int $month): array
    {
        return PublicHoliday::query()
            ->whereYear('holiday_date', $year)
            ->whereMonth('holiday_date', $month)
            ->pluck('holiday_date')
            ->map(fn ($d) => Carbon::parse($d)->day)
            ->toArray();
    }

    /**
     * attendance_logs dan bitta xodimning bitta kunlik soatini hisoblash.
     */
    public function calculateHoursFromDevice(DailyAttendance $attendance): float
    {
        if (! $attendance->first_entry || ! $attendance->last_exit) {
            return 0.0;
        }

        $start = Carbon::parse($attendance->first_entry);
        $end = Carbon::parse($attendance->last_exit);
        $minutes = $end->diffInMinutes($start);

        // Tushlik vaqtini olib tashlash (standart 60 daqiqa)
        $minutes = max(0, $minutes - 60);

        // Maksimum 12 soat
        return min(12.0, round($minutes / 60, 2));
    }

    /**
     * @param Collection<int, AttendanceEntry> $entries
     * @param Collection<int, DailyAttendance> $attendance
     * @return array<string, mixed>
     */
    private function computeMonthlyTotals(
        Employee $employee,
        int $year,
        int $month,
        int $daysInMonth,
        Collection $entries,
        Collection $attendance,
        array $holidays
    ): array {
        $totals = [
            'sick_days' => 0.0,
            'trip_days' => 0.0,
            'vacation_days' => 0.0,
            'maternity_days' => 0.0,
            'childcare_days' => 0.0,
            'study_days' => 0.0,
            'admin_leave_days' => 0.0,
            'actual_hours' => 0.0,
            'overtime_hours' => 0.0,
            'evening_hours' => 0.0,
            'night_hours' => 0.0,
            'holiday_hours' => 0.0,
            'work_days_count' => 0,
            'total_hours' => 0.0,
        ];

        $entriesByDay = $entries->keyBy(fn ($e) => Carbon::parse($e->work_date)->day);
        $attendanceByDay = $attendance->keyBy(fn ($d) => Carbon::parse($d->work_date)->day);

        $codeMap = [
            'Б' => 'sick_days',
            'К' => 'trip_days',
            'О' => 'vacation_days',
            'Р' => 'maternity_days',
            'ЧБ' => 'childcare_days',
            'У' => 'study_days',
            'А' => 'admin_leave_days',
            'С' => 'overtime_hours',
            'В' => 'evening_hours',
            'Н' => 'night_hours',
            'П' => 'holiday_hours',
        ];

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $date = Carbon::createFromDate($year, $month, $d);
            $isWeekend = $date->isWeekend();
            $isHoliday = in_array($d, $holidays);

            $entry = $entriesByDay->get($d);
            $deviceDay = $attendanceByDay->get($d);

            if ($entry) {
                $code = $entry->code;
                $days = (float) ($entry->days ?? 1.0);
                $hours = (float) ($entry->hours ?? 8.0);

                if (isset($codeMap[$code])) {
                    $field = $codeMap[$code];
                    if (str_ends_with($field, '_days')) {
                        $totals[$field] += $days;
                    } else {
                        $totals[$field] += $hours;
                    }
                } elseif ($code === 'Я') {
                    $totals['actual_hours'] += $hours;
                    if (! $isWeekend && ! $isHoliday) {
                        $totals['work_days_count']++;
                    }
                }

                $totals['total_hours'] += $hours;
            } elseif ($deviceDay && $deviceDay->work_minutes > 0) {
                $hours = $this->calculateHoursFromDevice($deviceDay);
                $totals['actual_hours'] += $hours;
                $totals['total_hours'] += $hours;
                if (! $isWeekend && ! $isHoliday) {
                    $totals['work_days_count']++;
                }
            }
        }

        return $totals;
    }

    /** @return array<string, mixed> */
    private function formatEntry(AttendanceEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'code' => $entry->code,
            'hours' => $entry->hours,
            'days' => $entry->days,
            'note' => $entry->note,
            'document_number' => $entry->document_number,
            'document_type' => $entry->document_type,
            'source' => $entry->source,
            'is_approved' => $entry->is_approved,
        ];
    }

    /** @return array<string, mixed> */
    private function formatDevice(DailyAttendance $d): array
    {
        return [
            'status' => $d->status,
            'first_entry' => $d->first_entry,
            'last_exit' => $d->last_exit,
            'work_minutes' => $d->work_minutes,
            'hours' => round($d->work_minutes / 60, 2),
        ];
    }
}
