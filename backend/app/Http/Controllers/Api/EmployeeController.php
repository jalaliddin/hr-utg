<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DailyAttendance;
use App\Models\Department;
use App\Models\Employee;
use App\Models\HikvisionDevice;
use App\Models\Position;
use App\Services\HikvisionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class EmployeeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $forcedOrgId = (! $user->isSuperAdmin())
            ? $user->organization_id
            : $request->organization_id;

        $query = Employee::query()
            ->with('organization:id,name,code')
            ->when($forcedOrgId, fn ($q) => $q->where('organization_id', $forcedOrgId))
            ->when($request->department, fn ($q) => $q->where('department', $request->department))
            ->when($request->is_active !== null, fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->when($request->search, fn ($q) => $q->where(function ($q2) use ($request) {
                $search = '%'.$request->search.'%';
                $q2->where('first_name', 'like', $search)
                    ->orWhere('last_name', 'like', $search)
                    ->orWhere('employee_id', 'like', $search)
                    ->orWhere('position', 'like', $search);
            }))
            ->orderBy('last_name')
            ->orderBy('first_name');

        return response()->json(
            $query->paginate($request->get('per_page', 50))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'organization_id' => ['required', 'exists:organizations,id'],
            'employee_id' => ['required', 'string', 'unique:employees'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'position_id' => ['nullable', 'exists:positions,id'],
            'phone' => ['nullable', 'string', 'max:50'],
            'hikvision_card_no' => ['nullable', 'string'],
            'hikvision_person_id' => ['nullable', 'integer'],
            'hired_at' => ['nullable', 'date'],
            'photo' => ['nullable', 'image', 'max:4096'],
        ]);

        $data = $this->fillDepartmentPosition($data);

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store('employees/photos', 'public');
        }

        unset($data['photo']);

        $employee = Employee::create($data);

        // Web app global unique ID: employee.id (primary key, hech qachon konflikt yo'q)
        if (empty($employee->hikvision_person_id)) {
            Employee::where('id', $employee->id)->update(['hikvision_person_id' => $employee->id]);
            $employee->hikvision_person_id = $employee->id;
        }

        $employee->refresh();

        $synced = $this->pushToDevices($employee);
        Employee::where('id', $employee->id)->update(['is_device_synced' => $synced]);
        $employee->is_device_synced = $synced;

        return response()->json($employee->load(['organization', 'departmentRel', 'positionRel']), 201);
    }

    public function show(Employee $employee): JsonResponse
    {
        return response()->json($employee->load(['organization', 'departmentRel', 'positionRel']));
    }

    public function update(Request $request, Employee $employee): JsonResponse
    {
        $data = $request->validate([
            'organization_id' => ['sometimes', 'exists:organizations,id'],
            'employee_id' => ['sometimes', 'string', "unique:employees,employee_id,{$employee->id}"],
            'first_name' => ['sometimes', 'string', 'max:100'],
            'last_name' => ['sometimes', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'position_id' => ['nullable', 'exists:positions,id'],
            'phone' => ['nullable', 'string', 'max:50'],
            'hikvision_card_no' => ['nullable', 'string'],
            'hikvision_person_id' => ['nullable', 'integer'],
            'hired_at' => ['nullable', 'date'],
            'fired_at' => ['nullable', 'date'],
            'is_active' => ['sometimes', 'boolean'],
            'photo' => ['nullable', 'image', 'max:4096'],
        ]);

        $data = $this->fillDepartmentPosition($data);

        if ($request->hasFile('photo')) {
            // Eski rasmni o'chirish
            if ($employee->photo_path) {
                Storage::disk('public')->delete($employee->photo_path);
            }
            $data['photo_path'] = $request->file('photo')->store('employees/photos', 'public');
        }

        unset($data['photo']);

        $employee->update($data);
        $employee->refresh();

        $synced = $this->pushToDevices($employee);
        if ($synced) {
            Employee::where('id', $employee->id)->update(['is_device_synced' => true]);
            $employee->is_device_synced = true;
        }

        return response()->json($employee->load(['organization', 'departmentRel', 'positionRel']));
    }

    public function destroy(Employee $employee): JsonResponse
    {
        $hikId = $employee->hikvision_person_id
            ? (string) $employee->hikvision_person_id
            : (string) $employee->employee_id;
        $organizationId = $employee->organization_id;

        $employee->delete();

        $this->deleteFromDevices($hikId, $organizationId);

        return response()->json(null, 204);
    }

    /**
     * department_id / position_id dan department / position string maydonlarini to'ldirish.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function fillDepartmentPosition(array $data): array
    {
        if (! empty($data['department_id'])) {
            $data['department'] = Department::find($data['department_id'])?->name ?? '';
        }

        if (! empty($data['position_id'])) {
            $data['position'] = Position::find($data['position_id'])?->name ?? '';
        }

        return $data;
    }

    /**
     * Xodimni tashkilot qurilmalariga yuklash (ma'lumot + rasm).
     * Kamida bitta qurilmaga muvaffaqiyatli yuklansa true qaytaradi.
     */
    private function pushToDevices(Employee $employee): bool
    {
        $devices = HikvisionDevice::query()
            ->where('organization_id', $employee->organization_id)
            ->get();

        if ($devices->isEmpty()) {
            return false;
        }

        $anySuccess = false;

        foreach ($devices as $device) {
            try {
                $service = new HikvisionService($device);

                $result = $service->pushEmployee($employee);
                if ($result['success']) {
                    $anySuccess = true;
                } else {
                    Log::warning("Push employee [{$employee->id}] to device [{$device->id}] failed: ".($result['error'] ?? ''));
                }

                if ($employee->photo_path) {
                    $photoResult = $service->pushEmployeePhoto($employee);
                    if (! $photoResult['success']) {
                        Log::warning("Push photo employee [{$employee->id}] to device [{$device->id}] failed: ".($photoResult['error'] ?? ''));
                    }
                }
            } catch (\Exception $e) {
                Log::error("Push employee [{$employee->id}] to device [{$device->id}] exception: {$e->getMessage()}");
            }
        }

        return $anySuccess;
    }

    /**
     * Xodimni tashkilot qurilmalaridan o'chirish.
     */
    private function deleteFromDevices(string $employeeNo, int $organizationId): void
    {
        $devices = HikvisionDevice::query()
            ->where('organization_id', $organizationId)
            ->get();

        foreach ($devices as $device) {
            try {
                $result = (new HikvisionService($device))->deleteEmployee($employeeNo);

                if (! ($result['success'] ?? false)) {
                    \App\Models\PendingDeviceDeletion::updateOrCreate(
                        ['device_id' => $device->id, 'hikvision_person_id' => $employeeNo]
                    );
                }
            } catch (\Exception $e) {
                Log::error("Delete employee [{$employeeNo}] from device [{$device->id}]: {$e->getMessage()}");
                \App\Models\PendingDeviceDeletion::updateOrCreate(
                    ['device_id' => $device->id, 'hikvision_person_id' => $employeeNo]
                );
            }
        }
    }

    /**
     * To'liq reconciliation: qurilmadagi va DBdagi xodimlarni moslashtiradi.
     * - DBda aktiv, qurilmada yo'q → qurilmaga qo'shadi
     * - Qurilmada bor, DBda yo'q yoki inactive → qurilmadan o'chiradi
     */
    public function syncToDevices(): JsonResponse
    {
        $devices = HikvisionDevice::all();

        $pushed = 0;
        $removed = 0;
        $pushFailed = 0;

        // DBdagi barcha aktiv xodimlar (hikvision_person_id bo'lishi shart)
        $activeEmployees = Employee::query()
            ->with('organization')
            ->where('is_active', true)
            ->whereNotNull('hikvision_person_id')
            ->get()
            ->keyBy(fn ($e) => (string) $e->hikvision_person_id);

        foreach ($devices as $device) {
            try {
                $service = new HikvisionService($device);
                $deviceIds = $service->getDeviceEmployeeIds();

                // null = qurilma offline — o'tkazib yuboramiz
                if ($deviceIds === null) {
                    continue;
                }

                $deviceIdSet = array_flip($deviceIds);
                $activeIdSet = $activeEmployees->keys()->flip()->toArray();

                // 1. Qurilmada yo'q aktiv xodimlarni qo'shamiz
                foreach ($activeEmployees as $hikId => $employee) {
                    if (! isset($deviceIdSet[$hikId])) {
                        $result = $service->pushEmployee($employee);
                        if ($result['success']) {
                            Employee::where('id', $employee->id)->update(['is_device_synced' => true]);
                            if ($employee->photo_path) {
                                $service->pushEmployeePhoto($employee);
                            }
                            $pushed++;
                        } else {
                            $pushFailed++;
                        }
                    } else {
                        // Qurilmada bor — synced deb belgilaymiz
                        if (! $employee->is_device_synced) {
                            Employee::where('id', $employee->id)->update(['is_device_synced' => true]);
                        }
                    }
                }

                // 2. Qurilmada bor, lekin DBda aktiv emas → o'chiramiz
                foreach ($deviceIds as $deviceId) {
                    if (! isset($activeIdSet[$deviceId])) {
                        $service->deleteEmployee($deviceId);
                        $removed++;
                    }
                }
            } catch (\Exception $e) {
                Log::error("Sync to device [{$device->id}]: {$e->getMessage()}");
            }
        }

        return response()->json([
            'pushed'      => $pushed,
            'removed'     => $removed,
            'push_failed' => $pushFailed,
        ]);
    }

    public function attendance(Employee $employee, Request $request): JsonResponse
    {
        $from = $request->get('from', now()->startOfMonth()->toDateString());
        $to = $request->get('to', now()->toDateString());

        $records = DailyAttendance::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('work_date', [$from, $to])
            ->orderBy('work_date')
            ->get();

        return response()->json($records);
    }

    public function monthlyTable(Employee $employee, Request $request): JsonResponse
    {
        $year = (int) $request->get('year', now()->year);
        $month = (int) $request->get('month', now()->month);
        $daysInMonth = Carbon::createFromDate($year, $month, 1)->daysInMonth;

        $records = DailyAttendance::query()
            ->where('employee_id', $employee->id)
            ->whereYear('work_date', $year)
            ->whereMonth('work_date', $month)
            ->get()
            ->keyBy(fn ($r) => Carbon::parse($r->work_date)->day);

        $days = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $days[$d] = $records->get($d);
        }

        $stats = [
            'present' => $records->whereIn('status', ['present', 'late'])->count(),
            'absent' => $records->where('status', 'absent')->count(),
            'late' => $records->where('status', 'late')->count(),
            'business_trip' => $records->where('status', 'business_trip')->count(),
            'total_minutes' => $records->sum('work_minutes'),
        ];

        return response()->json(compact('days', 'stats', 'daysInMonth'));
    }
}
