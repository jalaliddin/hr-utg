<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\BusinessTrip;
use App\Models\DailyAttendance;
use App\Models\Employee;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function daily(Request $request): JsonResponse
    {
        $date = $request->get('date', today()->toDateString());
        $user = $request->user();
        $organizationId = $user->isSuperAdmin()
            ? $request->get('organization_id')
            : $user->organization_id;
        $isToday = $date === today()->toDateString();

        // Barcha aktiv xodimlar
        $employees = Employee::query()
            ->where('is_active', true)
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->with('organization:id,name,code')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        // Hisoblangan kunlik yozuvlar
        $dailyMap = DailyAttendance::query()
            ->where('work_date', $date)
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->get()
            ->keyBy('employee_id');

        // Tashkilot ish jadvallarini yuklash (late hisoblash uchun)
        $scheduleMap = WorkSchedule::query()
            ->where('is_default', true)
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->get()
            ->keyBy('organization_id');

        // Bugun uchun real-time loglar (hali hisoblangan yozuv yo'q bo'lsa)
        $logsMap = collect();
        $latestLogMap = collect();
        if ($isToday) {
            $todayLogs = AttendanceLog::query()
                ->whereDate('event_time', $date)
                ->whereNotNull('employee_id')
                ->when($organizationId, fn ($q) => $q->whereHas(
                    'employee', fn ($eq) => $eq->where('organization_id', $organizationId)
                ))
                ->get();

            $logsMap = $todayLogs->groupBy('employee_id');

            // Har bir xodim uchun eng so'nggi log (yuz rasmi uchun)
            $latestLogMap = $todayLogs
                ->sortByDesc('event_time')
                ->groupBy('employee_id')
                ->map(fn ($logs) => $logs->first());
        }

        // Aktiv safarlar
        $tripsMap = BusinessTrip::query()
            ->where('status', 'approved')
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->pluck('employee_id')
            ->flip();

        $records = $employees->map(function (Employee $emp) use ($dailyMap, $logsMap, $latestLogMap, $tripsMap, $isToday) {
            $latestLog = $isToday ? $latestLogMap->get($emp->id) : null;
            $faceLogId = ($latestLog && isset($latestLog->raw_data['pictureURL'])) ? $latestLog->id : null;

            $base = [
                'employee_id'  => $emp->id,
                'employee'     => $emp,
                'face_log_id'  => $faceLogId,
            ];

            // 1. Hisoblangan yozuv bor
            if ($dailyMap->has($emp->id)) {
                $d = $dailyMap->get($emp->id);

                return array_merge($base, [
                    'id'           => $d->id,
                    'status'       => $d->status,
                    'first_entry'  => $d->first_entry,
                    'last_exit'    => $d->last_exit,
                    'work_minutes' => $d->work_minutes,
                    'note'         => $d->note,
                ]);
            }

            // 2. Xizmat safarida
            if ($tripsMap->has($emp->id)) {
                return array_merge($base, [
                    'id' => null, 'status' => 'business_trip',
                    'first_entry' => null, 'last_exit' => null, 'work_minutes' => 0, 'note' => null,
                ]);
            }

            // 3. Bugun uchun real-time loglardan hisoblash
            if ($isToday && $logsMap->has($emp->id)) {
                $logs       = $logsMap->get($emp->id);
                $entryLog   = $logs->where('event_type', 'entry')->sortBy('event_time')->first()
                    ?? $logs->sortBy('event_time')->first();
                $exitLog    = $logs->where('event_type', 'exit')->sortByDesc('event_time')->first();
                $entryTime  = Carbon::parse($entryLog->event_time);
                $orgId      = $emp->organization_id;
                $schedule   = $scheduleMap->get($orgId);
                $startTime  = $schedule?->work_start ?? '08:00:00';
                $tolerance  = $schedule?->late_tolerance_minutes ?? 15;
                $workStart  = Carbon::parse($date.' '.$startTime, 'Asia/Tashkent')->addMinutes($tolerance);
                $status     = $entryTime->setTimezone('Asia/Tashkent')->gt($workStart) ? 'late' : 'present';
                $workMins   = $exitLog
                    ? max(0, $entryTime->diffInMinutes(Carbon::parse($exitLog->event_time)))
                    : 0;

                return array_merge($base, [
                    'id'           => null,
                    'status'       => $status,
                    'first_entry'  => $entryTime->format('H:i:s'),
                    'last_exit'    => $exitLog ? Carbon::parse($exitLog->event_time)->format('H:i:s') : null,
                    'work_minutes' => $workMins,
                    'note'         => null,
                ]);
            }

            // 4. Kelmadi
            return array_merge($base, [
                'id' => null, 'status' => 'absent',
                'first_entry' => null, 'last_exit' => null, 'work_minutes' => 0, 'note' => null,
            ]);
        });

        $stats = [
            'present'       => $records->where('status', 'present')->count(),
            'late'          => $records->where('status', 'late')->count(),
            'absent'        => $records->where('status', 'absent')->count(),
            'business_trip' => $records->where('status', 'business_trip')->count(),
            'half_day'      => $records->where('status', 'half_day')->count(),
            'total'         => $records->count(),
        ];

        return response()->json(compact('records', 'stats', 'date'));
    }

    public function monthly(Request $request): JsonResponse
    {
        $year = (int) $request->get('year', now()->year);
        $month = (int) $request->get('month', now()->month);
        $user = $request->user();
        $organizationId = $user->isSuperAdmin()
            ? $request->get('organization_id')
            : $user->organization_id;

        $employees = Employee::query()
            ->where('is_active', true)
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->with(['organization:id,name,code'])
            ->orderBy('last_name')
            ->get();

        $daysInMonth = Carbon::createFromDate($year, $month, 1)->daysInMonth;

        $attendance = DailyAttendance::query()
            ->whereYear('work_date', $year)
            ->whereMonth('work_date', $month)
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->get()
            ->groupBy('employee_id');

        $result = $employees->map(function (Employee $employee) use ($attendance, $daysInMonth, $year, $month) {
            $records = $attendance->get($employee->id, collect());
            $byDay = $records->keyBy(fn ($r) => Carbon::parse($r->work_date)->day);

            $days = [];
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $days[$d] = $byDay->get($d) ? [
                    'status' => $byDay->get($d)->status,
                    'first_entry' => $byDay->get($d)->first_entry,
                    'last_exit' => $byDay->get($d)->last_exit,
                    'work_minutes' => $byDay->get($d)->work_minutes,
                ] : null;
            }

            return [
                'employee' => [
                    'id' => $employee->id,
                    'full_name' => $employee->full_name,
                    'position' => $employee->position,
                    'organization' => $employee->organization,
                ],
                'days' => $days,
                'stats' => [
                    'present' => $records->whereIn('status', ['present', 'late'])->count(),
                    'absent' => $records->where('status', 'absent')->count(),
                    'late' => $records->where('status', 'late')->count(),
                    'business_trip' => $records->where('status', 'business_trip')->count(),
                    'total_minutes' => $records->sum('work_minutes'),
                ],
            ];
        });

        return response()->json([
            'employees' => $result,
            'days_in_month' => $daysInMonth,
            'year' => $year,
            'month' => $month,
        ]);
    }

    public function update(Request $request, DailyAttendance $dailyAttendance): JsonResponse
    {
        $data = $request->validate([
            'status' => ['sometimes', 'in:present,absent,late,half_day,business_trip,leave,holiday'],
            'first_entry' => ['nullable', 'date_format:H:i:s'],
            'last_exit' => ['nullable', 'date_format:H:i:s'],
            'work_minutes' => ['nullable', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $dailyAttendance->update($data);

        return response()->json($dailyAttendance);
    }

    public function picture(AttendanceLog $log): \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
    {
        $pictureUrl = $log->raw_data['pictureURL'] ?? null;

        if (! $pictureUrl) {
            return response()->json(['error' => 'No picture'], 404);
        }

        $device = $log->device;

        if (! $device) {
            return response()->json(['error' => 'Device not found'], 404);
        }

        try {
            $response = \Illuminate\Support\Facades\Http::withDigestAuth(
                $device->username,
                decrypt($device->password)
            )->timeout(10)->get($pictureUrl);

            if (! $response->successful()) {
                return response()->json(['error' => 'Device returned '.$response->status()], 502);
            }

            return response($response->body(), 200, [
                'Content-Type'  => $response->header('Content-Type') ?: 'image/jpeg',
                'Cache-Control' => 'public, max-age=86400',
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }
    }

    public function export(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $year = (int) $request->get('year', now()->year);
        $month = (int) $request->get('month', now()->month);
        $organizationId = $request->get('organization_id');

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\AttendanceExport($year, $month, $organizationId),
            "tabel_{$year}_{$month}.xlsx"
        );
    }
}
