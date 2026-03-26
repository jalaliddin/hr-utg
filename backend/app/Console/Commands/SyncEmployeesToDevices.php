<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\HikvisionDevice;
use App\Services\HikvisionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncEmployeesToDevices extends Command
{
    protected $signature = 'employee:sync-devices
                            {--device=all : Device ID yoki "all"}
                            {--org=all    : Organization ID yoki "all"}
                            {--photo      : Rasmlarni ham yuklash}';

    protected $description = 'Barcha xodimlarni qurilmalarga department (belongGroup) bilan yuklash';

    public function handle(): int
    {
        $deviceOption = $this->option('device');
        $orgOption    = $this->option('org');
        $withPhoto    = $this->option('photo');

        $deviceQuery = HikvisionDevice::query();
        if ($deviceOption !== 'all') {
            $deviceQuery->where('id', (int) $deviceOption);
        }
        $devices = $deviceQuery->get();

        if ($devices->isEmpty()) {
            $this->error('Qurilma topilmadi.');

            return self::FAILURE;
        }

        $employeeQuery = Employee::query()
            ->with('organization')
            ->where('is_active', true)
            ->whereNotNull('hikvision_person_id');

        if ($orgOption !== 'all') {
            $employeeQuery->where('organization_id', (int) $orgOption);
        }

        $employees = $employeeQuery->get();

        if ($employees->isEmpty()) {
            $this->warn("Yuklash uchun xodim topilmadi (is_active=true va hikvision_person_id bo'lishi shart).");

            return self::SUCCESS;
        }

        $this->info("Qurilmalar: {$devices->count()} ta | Xodimlar: {$employees->count()} ta");

        foreach ($devices as $device) {
            $this->line("\n  → {$device->name} ({$device->ip_address})");

            $service = new HikvisionService($device);
            $ok      = 0;
            $fail    = 0;

            foreach ($employees as $employee) {
                try {
                    $result = $service->pushEmployee($employee);

                    if ($result['success']) {
                        $ok++;

                        if ($withPhoto && $employee->photo_path) {
                            $service->pushEmployeePhoto($employee);
                        }
                    } else {
                        $fail++;
                        $msg = $result['error'] ?? $result['body'] ?? '';
                        $this->warn("    ✗ [{$employee->hikvision_person_id}] {$employee->last_name}: {$msg}");
                        Log::warning("employee:sync-devices device[{$device->id}] employee[{$employee->id}]: {$msg}");
                    }
                } catch (\Exception $e) {
                    $fail++;
                    $this->warn("    ✗ [{$employee->hikvision_person_id}] {$employee->last_name}: {$e->getMessage()}");
                    Log::error("employee:sync-devices device[{$device->id}] employee[{$employee->id}]: {$e->getMessage()}");
                }
            }

            $device->update(['status' => 'online', 'last_seen_at' => now()]);
            $this->line("    ✓ {$ok} ta muvaffaqiyatli, {$fail} ta xato");
        }

        $this->info("\nSync yakunlandi.");

        return self::SUCCESS;
    }
}
