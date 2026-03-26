<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BusinessTrip;
use App\Models\DailyAttendance;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function monthlyTable(Request $request): JsonResponse
    {
        $year = (int) $request->get('year', now()->year);
        $month = (int) $request->get('month', now()->month);
        $user = $request->user();
        $organizationId = $user->isSuperAdmin()
            ? $request->get('organization_id')
            : $user->organization_id;

        $department = $request->get('department');

        $employees = Employee::query()
            ->where('is_active', true)
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->when($department, fn ($q) => $q->where('department', $department))
            ->with('organization:id,name,code')
            ->orderBy('last_name')
            ->get();

        $daysInMonth = Carbon::createFromDate($year, $month, 1)->daysInMonth;

        $attendance = DailyAttendance::query()
            ->whereYear('work_date', $year)
            ->whereMonth('work_date', $month)
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->get()
            ->groupBy('employee_id');

        $table = $employees->map(function (Employee $employee) use ($attendance, $daysInMonth) {
            $records = $attendance->get($employee->id, collect());
            $byDay = $records->keyBy(fn ($r) => Carbon::parse($r->work_date)->day);

            $days = [];
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $rec = $byDay->get($d);
                $days[$d] = $rec ? [
                    'status' => $rec->status,
                    'first_entry' => $rec->first_entry,
                    'last_exit' => $rec->last_exit,
                    'work_minutes' => $rec->work_minutes,
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
                'total_work_minutes' => $records->sum('work_minutes'),
                'work_days' => $records->whereIn('status', ['present', 'late'])->count(),
                'absent_days' => $records->where('status', 'absent')->count(),
                'late_days' => $records->where('status', 'late')->count(),
                'business_trip_days' => $records->where('status', 'business_trip')->count(),
            ];
        });

        return response()->json([
            'table' => $table,
            'days_in_month' => $daysInMonth,
            'year' => $year,
            'month' => $month,
        ]);
    }

    public function businessTrips(Request $request): JsonResponse
    {
        $user = $request->user();
        $forcedOrgId = $user->isSuperAdmin()
            ? $request->organization_id
            : $user->organization_id;

        $trips = BusinessTrip::query()
            ->with(['employee:id,first_name,last_name,position', 'organization:id,name,code'])
            ->when($forcedOrgId, fn ($q) => $q->where('organization_id', $forcedOrgId))
            ->when($request->from, fn ($q) => $q->where('start_date', '>=', $request->from))
            ->when($request->to, fn ($q) => $q->where('end_date', '<=', $request->to))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->orderByDesc('start_date')
            ->get();

        return response()->json($trips);
    }

    public function summary(Request $request): JsonResponse
    {
        $year = (int) $request->get('year', now()->year);
        $month = (int) $request->get('month', now()->month);
        $user = $request->user();
        $organizationId = $user->isSuperAdmin()
            ? $request->get('organization_id')
            : $user->organization_id;

        $attendance = DailyAttendance::query()
            ->whereYear('work_date', $year)
            ->whereMonth('work_date', $month)
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->selectRaw('organization_id, status, COUNT(*) as count')
            ->groupBy('organization_id', 'status')
            ->with('organization:id,name,code')
            ->get()
            ->groupBy('organization_id');

        return response()->json($attendance);
    }
}
