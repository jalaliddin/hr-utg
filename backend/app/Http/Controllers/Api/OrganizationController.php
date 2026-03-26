<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DailyAttendance;
use App\Models\HikvisionDevice;
use App\Models\Organization;
use App\Models\OrganizationDirector;
use App\Services\HikvisionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OrganizationController extends Controller
{
    public function index(): JsonResponse
    {
        $organizations = Organization::query()
            ->withCount(['employees' => fn ($q) => $q->where('is_active', true)])
            ->with('devices:id,organization_id,status,last_sync_at')
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        return response()->json($organizations);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', 'unique:organizations'],
            'type' => ['required', 'in:head,branch'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:50'],
            'hikvision_group_no' => ['nullable', 'integer', 'min:1', 'max:30'],
        ]);

        $organization = Organization::create($data);

        return response()->json($organization, 201);
    }

    public function show(Organization $organization): JsonResponse
    {
        $organization->load([
            'devices',
            'employees' => fn ($q) => $q->where('is_active', true),
        ]);

        return response()->json($organization);
    }

    public function update(Request $request, Organization $organization): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:20', "unique:organizations,code,{$organization->id}"],
            'type' => ['sometimes', 'in:head,branch'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
            'hikvision_group_no' => ['nullable', 'integer', 'min:1', 'max:30'],
        ]);

        $groupNoChanged = array_key_exists('hikvision_group_no', $data)
            && $data['hikvision_group_no'] !== $organization->hikvision_group_no;

        $organization->update($data);
        $organization->refresh();

        if ($groupNoChanged) {
            $this->repushEmployeesToDevices($organization);
        }

        return response()->json($organization);
    }

    /**
     * Tashkilot group_no o'zgarganda barcha aktiv xodimlarni qurilmaga qayta yuklash.
     */
    private function repushEmployeesToDevices(Organization $organization): void
    {
        $devices = HikvisionDevice::where('organization_id', $organization->id)->get();

        if ($devices->isEmpty()) {
            return;
        }

        $employees = $organization->employees()->where('is_active', true)->get();

        foreach ($devices as $device) {
            $service = new HikvisionService($device);

            foreach ($employees as $employee) {
                try {
                    $result = $service->pushEmployee($employee);

                    if (! $result['success']) {
                        Log::warning("Repush employee [{$employee->id}] to device [{$device->id}] after group change: ".($result['error'] ?? ''));
                    }
                } catch (\Exception $e) {
                    Log::error("Repush employee [{$employee->id}] to device [{$device->id}]: {$e->getMessage()}");
                }
            }
        }
    }

    public function destroy(Organization $organization): JsonResponse
    {
        $organization->delete();

        return response()->json(null, 204);
    }

    public function directors(Organization $organization): JsonResponse
    {
        return response()->json(
            $organization->directors()->orderByDesc('appointed_at')->get()
        );
    }

    public function storeDirector(Request $request, Organization $organization): JsonResponse
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'position' => ['required', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:100'],
            'is_active' => ['boolean'],
            'appointed_at' => ['nullable', 'date'],
        ]);

        if (! empty($data['is_active'])) {
            $organization->directors()->update(['is_active' => false]);
        }

        $director = $organization->directors()->create($data);

        return response()->json($director, 201);
    }

    public function updateDirector(Request $request, Organization $organization, OrganizationDirector $director): JsonResponse
    {
        $data = $request->validate([
            'full_name' => ['sometimes', 'string', 'max:255'],
            'position' => ['sometimes', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:100'],
            'is_active' => ['boolean'],
            'appointed_at' => ['nullable', 'date'],
        ]);

        if (! empty($data['is_active'])) {
            $organization->directors()->where('id', '!=', $director->id)->update(['is_active' => false]);
        }

        $director->update($data);

        return response()->json($director);
    }

    public function destroyDirector(Organization $organization, OrganizationDirector $director): JsonResponse
    {
        $director->delete();

        return response()->json(null, 204);
    }

    public function attendance(Organization $organization, Request $request): JsonResponse
    {
        $date = $request->get('date', today()->toDateString());

        $records = DailyAttendance::query()
            ->with('employee:id,first_name,last_name,position')
            ->where('organization_id', $organization->id)
            ->where('work_date', $date)
            ->get();

        $stats = [
            'present' => $records->where('status', 'present')->count(),
            'absent' => $records->where('status', 'absent')->count(),
            'late' => $records->where('status', 'late')->count(),
            'business_trip' => $records->where('status', 'business_trip')->count(),
            'total' => $organization->employees()->where('is_active', true)->count(),
        ];

        return response()->json(compact('stats', 'records'));
    }
}
