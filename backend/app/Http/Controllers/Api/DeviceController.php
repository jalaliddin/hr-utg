<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\HikvisionDevice;
use App\Models\SyncLog;
use App\Services\HikvisionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class DeviceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $forcedOrgId = (! $user->isSuperAdmin())
            ? $user->organization_id
            : $request->input('organization_id');

        $devices = HikvisionDevice::query()
            ->with('organization:id,name,code')
            ->when($forcedOrgId, fn ($q) => $q->where('organization_id', $forcedOrgId))
            ->orderBy('organization_id')
            ->get();

        return response()->json($devices);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'organization_id' => ['required', 'exists:organizations,id'],
            'name' => ['required', 'string', 'max:200'],
            'ip_address' => ['required', 'ip'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'username' => ['nullable', 'string'],
            'password' => ['required', 'string'],
            'serial_number' => ['nullable', 'string'],
        ]);

        $data['password'] = encrypt($data['password']);
        $device = HikvisionDevice::create($data);

        return response()->json($device->load('organization'), 201);
    }

    public function show(HikvisionDevice $device): JsonResponse
    {
        return response()->json($device->load('organization'));
    }

    public function update(Request $request, HikvisionDevice $device): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:200'],
            'ip_address' => ['sometimes', 'ip'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'username' => ['nullable', 'string'],
            'password' => ['nullable', 'string'],
            'serial_number' => ['nullable', 'string'],
        ]);

        if (isset($data['password'])) {
            $data['password'] = encrypt($data['password']);
        }

        $device->update($data);

        return response()->json($device->load('organization'));
    }

    public function destroy(HikvisionDevice $device): JsonResponse
    {
        $device->delete();

        return response()->json(null, 204);
    }

    public function testConnection(HikvisionDevice $device): JsonResponse
    {
        $service = new HikvisionService($device);
        $result = $service->getDeviceInfo();

        if ($result['success']) {
            $info = $result['data'];
            $serialNumber = $info['serialNumber'] ?? $device->serial_number;
            $deviceName = $info['model'] ?? $info['deviceName'] ?? null;

            $device->update([
                'status' => 'online',
                'last_seen_at' => now(),
                'device_info' => $info,
                'serial_number' => $serialNumber ?: $device->serial_number,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Qurilmaga muvaffaqiyatli ulanildi.',
                'model' => $deviceName,
                'serial' => $serialNumber,
                'firmware' => $info['firmwareVersion'] ?? null,
                'device_info' => $info,
            ]);
        }

        $device->update(['status' => 'offline']);

        return response()->json([
            'success' => false,
            'message' => 'Qurilmaga ulanib bo\'lmadi: '.$result['error'],
        ], 422);
    }

    public function manualSync(HikvisionDevice $device, Request $request): JsonResponse
    {
        $params = ["--device={$device->id}"];

        if ($request->from) {
            $params[] = "--from={$request->from}";
        }

        if ($request->to) {
            $params[] = "--to={$request->to}";
        }

        Artisan::call('attendance:sync', array_reduce($params, function (array $carry, string $p) {
            [$key, $val] = array_pad(explode('=', ltrim($p, '-'), 2), 2, true);
            $carry["--{$key}"] = $val ?? true;

            return $carry;
        }, []));

        $output = trim(Artisan::output());

        // Natijani parse qilish
        preg_match('/(\d+) ta yangi.*?(\d+) ta takror/', $output, $m);

        return response()->json([
            'message' => 'Sinxronizatsiya yakunlandi.',
            'records_new' => isset($m[1]) ? (int) $m[1] : 0,
            'records_duplicate' => isset($m[2]) ? (int) $m[2] : 0,
            'output' => $output,
        ]);
    }

    public function importEmployees(HikvisionDevice $device, Request $request): JsonResponse
    {
        $update = $request->boolean('update', false);
        $flag = $update ? ' --update' : '';

        Artisan::call("employees:import --device={$device->id}{$flag}");

        $output = Artisan::output();

        return response()->json([
            'message' => 'Xodimlar import qilindi.',
            'output' => trim($output),
        ]);
    }

    /**
     * Qurilma xodimlarini web app bilan moslashtiradi:
     * - Web appda bor, qurilmada yo'q → qurilmaga qo'shadi
     * - Qurilmada bor, web appda yo'q yoki inactive → qurilmadan o'chiradi
     */
    public function reconcileEmployees(HikvisionDevice $device): JsonResponse
    {
        $service = new HikvisionService($device);
        $deviceIds = $service->getDeviceEmployeeIds();

        if ($deviceIds === null) {
            return response()->json(['message' => 'Qurilmaga ulanib bo\'lmadi.'], 503);
        }

        $appEmployees = Employee::query()
            ->with('organization')
            ->where('is_active', true)
            ->whereNotNull('hikvision_person_id')
            ->get()
            ->keyBy(fn ($e) => (string) $e->hikvision_person_id);

        $deviceIdSet = array_flip($deviceIds);
        $activeIdSet = $appEmployees->keys()->flip()->toArray();
        $pushed = 0;
        $removed = 0;

        // Web appda bor, qurilmada yo'q → qo'shamiz
        foreach ($appEmployees as $hikId => $emp) {
            if (! isset($deviceIdSet[$hikId])) {
                $result = $service->pushEmployee($emp);
                if ($result['success']) {
                    Employee::where('id', $emp->id)->update(['is_device_synced' => true]);
                    if ($emp->photo_path) {
                        $service->pushEmployeePhoto($emp);
                    }
                    $pushed++;
                }
            } elseif (! $emp->is_device_synced) {
                Employee::where('id', $emp->id)->update(['is_device_synced' => true]);
            }
        }

        // Qurilmada bor, web appda yo'q → o'chiramiz
        foreach ($deviceIds as $deviceId) {
            if (! isset($activeIdSet[$deviceId])) {
                $service->deleteEmployee($deviceId);
                $removed++;
            }
        }

        $parts = [];
        if ($pushed) {
            $parts[] = "{$pushed} ta qo'shildi";
        }
        if ($removed) {
            $parts[] = "{$removed} ta o'chirildi";
        }

        return response()->json([
            'pushed'  => $pushed,
            'removed' => $removed,
            'message' => $parts ? implode(', ', $parts) : 'Qurilma sinxronlashtirildi',
        ]);
    }

    public function syncLogs(HikvisionDevice $device): JsonResponse
    {
        $logs = SyncLog::query()
            ->where('device_id', $device->id)
            ->orderByDesc('sync_started_at')
            ->limit(20)
            ->get();

        return response()->json($logs);
    }
}
