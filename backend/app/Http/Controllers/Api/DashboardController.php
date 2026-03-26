<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BusinessTrip;
use App\Models\DailyAttendance;
use App\Models\Employee;
use App\Models\HikvisionDevice;
use App\Models\Organization;
use App\Models\SyncLog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        $orgId = $user->isSuperAdmin() ? null : $user->organization_id;
        $today = today()->toDateString();

        $totalEmployees = Employee::query()
            ->where('is_active', true)
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->count();

        $todayAttendance = DailyAttendance::query()
            ->where('work_date', $today)
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->get();

        $presentCount = $todayAttendance->whereIn('status', ['present', 'late'])->count();
        $absentCount = $todayAttendance->where('status', 'absent')->count();
        $lateCount = $todayAttendance->where('status', 'late')->count();

        $onBusinessTrip = BusinessTrip::query()
            ->where('status', 'approved')
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->count();

        $deviceStats = HikvisionDevice::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $pendingTrips = BusinessTrip::query()
            ->where('status', 'pending')
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->count();

        $lastSyncs = SyncLog::query()
            ->with('device:id,name,ip_address')
            ->when($orgId, fn ($q) => $q->whereHas('device', fn ($d) => $d->where('organization_id', $orgId)))
            ->orderByDesc('sync_started_at')
            ->limit(5)
            ->get();

        return response()->json([
            'total_employees' => $totalEmployees,
            'today' => [
                'present' => $presentCount,
                'absent' => $absentCount,
                'late' => $lateCount,
                'business_trip' => $onBusinessTrip,
                'attendance_rate' => $totalEmployees > 0
                    ? round($presentCount / $totalEmployees * 100, 1)
                    : 0,
            ],
            'devices' => [
                'online' => $deviceStats->get('online', 0),
                'offline' => $deviceStats->get('offline', 0),
                'unknown' => $deviceStats->get('unknown', 0),
            ],
            'pending_trips' => $pendingTrips,
            'last_syncs' => $lastSyncs,
        ]);
    }

    public function today(Request $request): JsonResponse
    {
        $user = $request->user();
        $orgId = $user->isSuperAdmin() ? null : $user->organization_id;
        $today = today()->toDateString();

        $organizations = Organization::query()
            ->when($orgId, fn ($q) => $q->where('id', $orgId))
            ->withCount(['employees' => fn ($q) => $q->where('is_active', true)])
            ->get();

        $attendanceByOrg = DailyAttendance::query()
            ->where('work_date', $today)
            ->selectRaw('organization_id, status, COUNT(*) as count')
            ->groupBy('organization_id', 'status')
            ->get()
            ->groupBy('organization_id');

        $result = $organizations->map(function ($org) use ($attendanceByOrg) {
            $records = $attendanceByOrg->get($org->id, collect());
            $present = $records->whereIn('status', ['present', 'late'])->sum('count');

            return [
                'id' => $org->id,
                'name' => $org->name,
                'code' => $org->code,
                'total_employees' => $org->employees_count,
                'present' => $present,
                'absent' => $records->where('status', 'absent')->sum('count'),
                'late' => $records->where('status', 'late')->sum('count'),
                'business_trip' => $records->where('status', 'business_trip')->sum('count'),
                'attendance_rate' => $org->employees_count > 0
                    ? round($present / $org->employees_count * 100, 1)
                    : 0,
            ];
        });

        return response()->json($result);
    }

    public function trend(Request $request): JsonResponse
    {
        $days = (int) $request->get('days', 30);
        $organizationId = $request->get('organization_id');

        $data = DailyAttendance::query()
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->where('work_date', '>=', now()->subDays($days)->toDateString())
            ->selectRaw('work_date, status, COUNT(*) as count')
            ->groupBy('work_date', 'status')
            ->orderBy('work_date')
            ->get()
            ->groupBy('work_date');

        $result = [];
        foreach ($data as $date => $records) {
            $result[] = [
                'date' => $date,
                'present' => $records->whereIn('status', ['present', 'late'])->sum('count'),
                'absent' => $records->where('status', 'absent')->sum('count'),
                'late' => $records->where('status', 'late')->sum('count'),
            ];
        }

        return response()->json($result);
    }
}
