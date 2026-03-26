<?php

namespace App\Services;

use App\Models\BusinessTrip;
use App\Models\BusinessTripDestination;
use App\Models\Employee;
use App\Models\HikvisionDevice;
use Illuminate\Support\Facades\Log;

class BusinessTripHikvisionService
{
    /**
     * Tasdiqlangan safari xodimini barcha destinatsiya qurilmalariga yuklaydi.
     * Har bir destinatsiya uchun alohida qurilma ID ko'rsatilgan bo'lsa — o'sha qurilma ishlatiladi.
     * Aks holda destinatsiya tashkilotining aktiv qurilmasi tanlanadi.
     */
    public function pushTripToDevices(BusinessTrip $trip): void
    {
        $trip->loadMissing(['employee.organization', 'destinations.organization']);

        $employee = $trip->employee;

        if (! $employee || ! $employee->hikvision_person_id) {
            Log::warning("BusinessTripHikvision: employee #{$trip->employee_id} has no hikvision_person_id");
            $trip->update(['device_push_status' => 'failed']);

            return;
        }

        $allSuccess = true;
        $pushLog = [];

        foreach ($trip->destinations as $destination) {
            $device = $this->resolveDevice($destination);

            if (! $device) {
                $this->markDestinationFailed($destination, 'No device found for organization');
                $allSuccess = false;
                $pushLog[] = ['org' => $destination->organization?->name, 'error' => 'No device'];

                continue;
            }

            $result = $this->pushToDevice($device, $employee, $trip, $destination);

            if ($result['success']) {
                $destination->update([
                    'push_status' => 'success',
                    'pushed_at' => now(),
                    'push_error' => null,
                    'device_id' => $device->id,
                    'retry_count' => 0,
                ]);
                $pushLog[] = ['org' => $destination->organization?->name, 'device' => $device->name, 'status' => 'success'];
            } else {
                $this->markDestinationFailed($destination, $result['error'] ?? 'Unknown error', $device->id);
                $allSuccess = false;
                $pushLog[] = ['org' => $destination->organization?->name, 'device' => $device->name, 'error' => $result['error'] ?? 'Unknown'];
            }
        }

        $trip->update([
            'device_push_status' => $allSuccess ? 'success' : 'partial',
            'device_pushed_at' => now(),
            'device_push_log' => $pushLog,
        ]);
    }

    /**
     * Bitta destinatsiyani qayta sinab ko'radi.
     */
    public function retryDestination(BusinessTripDestination $destination): bool
    {
        $trip = $destination->businessTrip;
        $employee = $trip->employee;

        if (! $employee || ! $employee->hikvision_person_id) {
            return false;
        }

        $device = $this->resolveDevice($destination);

        if (! $device) {
            $this->markDestinationFailed($destination, 'No device found');

            return false;
        }

        $result = $this->pushToDevice($device, $employee, $trip, $destination);

        if ($result['success']) {
            $destination->update([
                'push_status' => 'success',
                'pushed_at' => now(),
                'push_error' => null,
                'device_id' => $device->id,
            ]);

            // Hammasini tekshir
            $allSuccess = $trip->destinations()->where('push_status', '!=', 'success')->doesntExist();
            $trip->update(['device_push_status' => $allSuccess ? 'success' : 'partial']);

            return true;
        }

        $destination->increment('retry_count');
        $destination->update([
            'push_error' => $result['error'] ?? 'Unknown error',
            'retry_after' => now()->addMinutes(10),
        ]);

        return false;
    }

    private function resolveDevice(BusinessTripDestination $destination): ?HikvisionDevice
    {
        if ($destination->device_id) {
            return HikvisionDevice::find($destination->device_id);
        }

        if ($destination->organization_id) {
            return HikvisionDevice::where('organization_id', $destination->organization_id)
                ->where('status', 'online')
                ->first()
                ?? HikvisionDevice::where('organization_id', $destination->organization_id)->first();
        }

        return null;
    }

    /**
     * Xodimni qurilmaga yuklaydi. Safari muddati bilan validity belgilaydi.
     *
     * @return array{success: bool, error?: string}
     */
    private function pushToDevice(
        HikvisionDevice $device,
        Employee $employee,
        BusinessTrip $trip,
        BusinessTripDestination $destination
    ): array {
        try {
            $service = new HikvisionService($device);

            $hikId = (string) $employee->hikvision_person_id;
            $groupNo = $employee->organization?->hikvision_group_no;
            $fullName = trim(implode(' ', array_filter([
                $employee->last_name,
                $employee->first_name,
                $employee->middle_name,
            ]))) ?: $hikId;

            $startDate = $destination->arrival_date ?? $trip->start_date;
            $endDate = $destination->departure_date ?? $trip->effective_end_date;

            $userInfo = [
                'employeeNo' => $hikId,
                'name' => $fullName,
                'userType' => 'normal',
                'Valid' => [
                    'enable' => true,
                    'beginTime' => $startDate->format('Y-m-d').'T00:00:00',
                    'endTime' => $endDate->format('Y-m-d').'T23:59:59',
                    'timeType' => 'local',
                ],
                'localUIRight' => false,
                'userVerifyMode' => '',
            ];

            if ($groupNo) {
                $userInfo['belongGroup'] = (string) $groupNo;
            }

            // Try POST first; if already exists, delete+recreate
            $result = $service->postJson('/ISAPI/AccessControl/UserInfo/Record?format=json', ['UserInfo' => $userInfo]);

            if (! $result['success'] && str_contains($result['body'] ?? '', 'employeeNoAlreadyExist')) {
                $service->deleteEmployee($hikId);
                $result = $service->postJson('/ISAPI/AccessControl/UserInfo/Record?format=json', ['UserInfo' => $userInfo]);
            }

            // Rasm ham yuklash (xato bo'lsa hisobga olinmaydi)
            if ($result['success'] && $employee->photo_path) {
                $service->pushEmployeePhoto($employee);
            }

            return $result;
        } catch (\Exception $e) {
            Log::error("BusinessTripHikvision push error: {$e->getMessage()}");

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Safari yakunlanganda xodimni barcha destinatsiya qurilmalaridan o'chiradi.
     */
    public function removeFromDestinationDevices(BusinessTrip $trip): void
    {
        $trip->loadMissing(['employee', 'destinations']);

        $employee = $trip->employee;

        if (! $employee || ! $employee->hikvision_person_id) {
            return;
        }

        $hikId = (string) $employee->hikvision_person_id;

        foreach ($trip->destinations as $destination) {
            $device = $this->resolveDevice($destination);

            if (! $device) {
                continue;
            }

            try {
                $service = new HikvisionService($device);
                $service->deleteEmployee($hikId);

                $destination->update(['push_status' => 'offline']);
            } catch (\Exception $e) {
                Log::warning("BusinessTripHikvision remove error (dest #{$destination->id}): {$e->getMessage()}");
            }
        }
    }

    private function markDestinationFailed(BusinessTripDestination $destination, string $error, ?int $deviceId = null): void
    {
        $destination->update([
            'push_status' => 'failed',
            'push_error' => $error,
            'retry_after' => now()->addMinutes(5),
            'retry_count' => ($destination->retry_count ?? 0) + 1,
            ...(($deviceId !== null) ? ['device_id' => $deviceId] : []),
        ]);
    }
}
