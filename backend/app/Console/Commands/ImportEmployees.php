<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\HikvisionDevice;
use App\Services\HikvisionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ImportEmployees extends Command
{
    protected $signature = 'employees:import
                            {--device=all : Device ID yoki "all"}
                            {--update : Mavjud xodimlarni ham yangilash}';

    protected $description = 'Hikvision qurilmalaridan xodimlarni avtomatik import qilish';

    public function handle(): int
    {
        $deviceOption = $this->option('device');
        $shouldUpdate = $this->option('update');

        $query = HikvisionDevice::query()->with('organization');

        if ($deviceOption !== 'all') {
            $query->where('id', (int) $deviceOption);
        }

        $devices = $query->get();

        if ($devices->isEmpty()) {
            $this->error('Qurilma topilmadi.');
            return self::FAILURE;
        }

        $totalImported = 0;
        $totalUpdated = 0;
        $totalSkipped = 0;

        foreach ($devices as $device) {
            $this->line("  → {$device->name} ({$device->ip_address}) — {$device->organization->name}");

            try {
                [$imported, $updated, $skipped] = $this->importFromDevice($device, $shouldUpdate);
                $totalImported += $imported;
                $totalUpdated += $updated;
                $totalSkipped += $skipped;
                $this->line("    ✓ {$imported} yangi, {$updated} yangilandi, {$skipped} o'tkazib yuborildi");
            } catch (\Exception $e) {
                $this->error("    ✗ Xato: {$e->getMessage()}");
                Log::error("Employee import failed for device {$device->id}: {$e->getMessage()}");
            }
        }

        $this->info("Jami: {$totalImported} yangi, {$totalUpdated} yangilandi, {$totalSkipped} o'tkazib yuborildi");

        return self::SUCCESS;
    }

    /**
     * @return array{int, int, int}  [imported, updated, skipped]
     */
    private function importFromDevice(HikvisionDevice $device, bool $shouldUpdate): array
    {
        $service = new HikvisionService($device);
        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $offset = 0;
        $limit = 50;

        do {
            $result = $service->getPersonList($offset, $limit);

            if (! $result['success']) {
                throw new \RuntimeException($result['error'] ?? 'Unknown error');
            }

            $searchResult = $result['data']['UserInfoSearch'] ?? [];
            $users = $searchResult['UserInfo'] ?? [];

            if (empty($users)) {
                break;
            }

            // Bitta foydalanuvchi ham massiv sifatida kelishi kerak
            if (isset($users['employeeNo'])) {
                $users = [$users];
            }

            $fetched = count($users);

            foreach ($users as $user) {
                [$wasImported, $wasUpdated] = $this->processUser($user, $device, $shouldUpdate);
                if ($wasImported) {
                    $imported++;
                } elseif ($wasUpdated) {
                    $updated++;
                } else {
                    $skipped++;
                }
            }

            $responseStatus = strtoupper((string) ($searchResult['responseStatusStrg'] ?? 'OK'));
            $hasMore = $responseStatus === 'MORE' && $fetched >= $limit;
            $offset += $fetched;
        } while ($hasMore);

        return [$imported, $updated, $skipped];
    }

    /**
     * @return array{bool, bool}  [wasImported, wasUpdated]
     */
    private function processUser(array $user, HikvisionDevice $device, bool $shouldUpdate): array
    {
        $employeeNo = (string) ($user['employeeNo'] ?? '');

        if (empty($employeeNo)) {
            return [false, false];
        }

        $name = (string) ($user['name'] ?? '');
        [$lastName, $firstName, $middleName] = $this->parseName($name);

        $existing = Employee::withTrashed()
            ->where('organization_id', $device->organization_id)
            ->where(function ($q) use ($employeeNo) {
                $q->where('hikvision_person_id', (int) $employeeNo)
                    ->orWhere('employee_id', $employeeNo);
            })
            ->first();

        if ($existing) {
            if (! $shouldUpdate) {
                return [false, false];
            }

            $existing->restore();
            $existing->update([
                'first_name' => $firstName ?: $existing->first_name,
                'last_name' => $lastName ?: $existing->last_name,
                'middle_name' => $middleName ?: $existing->middle_name,
                'hikvision_person_id' => (int) $employeeNo,
                'is_active' => (bool) ($user['Valid']['enable'] ?? true),
            ]);

            return [false, true];
        }

        Employee::create([
            'organization_id' => $device->organization_id,
            'employee_id' => $employeeNo,
            'first_name' => $firstName ?: 'Noma\'lum',
            'last_name' => $lastName ?: $employeeNo,
            'middle_name' => $middleName,
            'position' => 'Xodim',
            'hikvision_person_id' => (int) $employeeNo,
            'is_active' => (bool) ($user['Valid']['enable'] ?? true),
        ]);

        return [true, false];
    }

    /**
     * Ism-sharif ajratish: "FAMILIYA Ism Otasining ismi"
     *
     * @return array{string, string, string}
     */
    private function parseName(string $fullName): array
    {
        $parts = array_values(array_filter(explode(' ', trim($fullName))));

        $lastName = ucfirst(strtolower($parts[0] ?? ''));
        $firstName = isset($parts[1]) ? ucfirst(strtolower($parts[1])) : '';
        $middleName = ucfirst(strtolower($parts[2] ?? ''));

        return [$lastName, $firstName, $middleName];
    }
}
