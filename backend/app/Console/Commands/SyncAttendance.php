<?php

namespace App\Console\Commands;

use App\Models\AttendanceLog;
use App\Models\BusinessTrip;
use App\Models\BusinessTripDestination;
use App\Models\DailyAttendance;
use App\Models\Employee;
use App\Models\HikvisionDevice;
use App\Models\PendingDeviceDeletion;
use App\Models\SyncLog;
use App\Models\WorkSchedule;
use App\Services\AutoAttendanceService;
use App\Services\HikvisionService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncAttendance extends Command
{
    protected $signature = 'attendance:sync
                            {--device=all : Device ID yoki "all"}
                            {--from= : Boshlanish sanasi (Y-m-d)}
                            {--to= : Tugash sanasi (Y-m-d)}';

    protected $description = 'Hikvision qurilmalaridan keldi-ketdi ma\'lumotlarini pull qilish';

    private const TZ = 'Asia/Tashkent';

    public function handle(): int
    {
        $deviceOption = $this->option('device');

        $query = HikvisionDevice::query()->with('organization');

        if ($deviceOption !== 'all') {
            $query->where('id', (int) $deviceOption);
        }

        $devices = $query->get();

        if ($devices->isEmpty()) {
            $this->error('Qurilma topilmadi.');

            return self::FAILURE;
        }

        $this->info("Sinxronizatsiya boshlandi: {$devices->count()} ta qurilma");

        foreach ($devices as $device) {
            $this->syncDevice($device);
        }

        $this->info('Sinxronizatsiya yakunlandi.');

        return self::SUCCESS;
    }

    private function syncDevice(HikvisionDevice $device): void
    {
        $this->line("  → {$device->name} ({$device->ip_address})");

        $from = $this->option('from')
            ? Carbon::parse($this->option('from'), self::TZ)
            : ($device->last_sync_at
                ? $device->last_sync_at->setTimezone(self::TZ)
                : Carbon::now(self::TZ)->subHours(24));

        $to = $this->option('to')
            ? Carbon::parse($this->option('to'), self::TZ)->endOfDay()
            : Carbon::now(self::TZ);

        $startTime = $from->format('Y-m-d\TH:i:sP');
        $endTime = $to->format('Y-m-d\TH:i:sP');

        $syncLog = SyncLog::create([
            'device_id' => $device->id,
            'organization_id' => $device->organization_id,
            'sync_started_at' => now(),
            'status' => 'running',
            'params' => ['start' => $startTime, 'end' => $endTime],
        ]);

        try {
            $service = new HikvisionService($device);
            $totalFetched = 0;
            $totalNew = 0;
            $totalDuplicate = 0;
            $offset = 0;
            $maxResults = 1000;
            $hasMore = true;

            while ($hasMore) {
                $result = $service->getACSEvents($startTime, $endTime, $offset, $maxResults);

                if (! $result['success']) {
                    throw new \RuntimeException($result['error'] ?? 'Unknown error');
                }

                $events = $service->extractEvents($result['data']);
                $fetched = count($events);
                $totalFetched += $fetched;

                foreach ($events as $event) {
                    $isNew = $this->processEvent($event, $device, $service);
                    if ($isNew) {
                        $totalNew++;
                    } else {
                        $totalDuplicate++;
                    }
                }

                // Hikvision "MORE" yoki "OK" status qaytaradi
                $responseStatus = strtoupper((string) ($result['data']['responseStatusStrg'] ?? 'OK'));
                $hasMore = $responseStatus === 'MORE' && $fetched > 0;
                $offset += $fetched;
            }

            $device->update([
                'last_sync_at' => now(),
                'last_seen_at' => now(),
                'status' => 'online',
            ]);

            // Qurilma online — navbatdagi o'chirishlarni bajaramiz
            $this->processPendingDeletions($device, $service);

            $syncLog->update([
                'sync_finished_at' => now(),
                'status' => 'success',
                'records_fetched' => $totalFetched,
                'records_new' => $totalNew,
                'records_duplicate' => $totalDuplicate,
            ]);

            $this->recalculateDailyAttendance($device->organization_id, $from, $to);

            // attendance_entries ga Я kodli yozuvlar avtomatik kiritish
            $autoAttendance = new AutoAttendanceService;
            $autoAttendance->processLogs($device->organization_id, $from, $to);

            $this->line("    ✓ {$totalNew} ta yangi, {$totalDuplicate} ta takror");

        } catch (\Exception $e) {
            $device->update(['status' => 'offline']);
            $syncLog->update([
                'sync_finished_at' => now(),
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            Log::error("Sync failed for device {$device->id}: {$e->getMessage()}");
            $this->error("    ✗ Xato: {$e->getMessage()}");
        }
    }

    private function processPendingDeletions(HikvisionDevice $device, HikvisionService $service): void
    {
        $pending = PendingDeviceDeletion::where('device_id', $device->id)->get();

        if ($pending->isEmpty()) {
            return;
        }

        $deleted = 0;

        foreach ($pending as $item) {
            try {
                $result = $service->deleteEmployee($item->hikvision_person_id);

                if ($result['success'] ?? false) {
                    $item->delete();
                    $deleted++;
                }
            } catch (\Exception $e) {
                Log::warning("Pending delete [{$item->hikvision_person_id}] on device [{$device->id}]: {$e->getMessage()}");
            }
        }

        if ($deleted > 0) {
            $this->line("    🗑 {$deleted} ta kutilgan o'chirish bajarildi");
        }
    }

    private function processEvent(array $event, HikvisionDevice $device, HikvisionService $service): bool
    {
        $timeStr = (string) ($event['time'] ?? '');

        if (empty($timeStr)) {
            return false;
        }

        try {
            // Qurilma +05:00 offset bilan yuboradi → UTC ga aylantirib saqlaymiz
            $eventTime = Carbon::parse($timeStr)->utc();
        } catch (\Exception) {
            return false;
        }

        $personId = (string) ($event['employeeNoString'] ?? $event['cardNo'] ?? '');
        $cardNo = (string) ($event['cardNo'] ?? '');

        // Duplicate tekshirish
        $exists = AttendanceLog::query()
            ->where('device_id', $device->id)
            ->where('event_time', $eventTime)
            ->where(function ($q) use ($personId, $cardNo) {
                $q->where('hikvision_person_id', $personId)
                    ->orWhere('hikvision_card_no', $cardNo);
            })
            ->exists();

        if ($exists) {
            return false;
        }

        // Xodimni topish: hikvision_person_id → card_no → employee_id (tabel №)
        $employee = null;
        if (! empty($personId)) {
            $employee = Employee::query()
                ->where('hikvision_person_id', (int) $personId)
                ->first();
        }

        if (! $employee && ! empty($cardNo)) {
            $employee = Employee::query()
                ->where('hikvision_card_no', $cardNo)
                ->first();
        }

        // employeeNoString — tabel raqami bilan mos kelishi mumkin
        if (! $employee && ! empty($personId)) {
            $employee = Employee::query()
                ->where('employee_id', $personId)
                ->first();
        }

        $eventType = $service->resolveEventType($event);

        AttendanceLog::create([
            'organization_id' => $device->organization_id,
            'device_id' => $device->id,
            'employee_id' => $employee?->id,
            'hikvision_person_id' => $personId,
            'hikvision_card_no' => $cardNo,
            'event_type' => $eventType,
            'event_time' => $eventTime,
            'door_name' => (string) ($event['doorNo'] ?? ''),
            'raw_data' => $event,
            'is_processed' => false,
        ]);

        // Xizmat safari destinatsiyasini yangilash
        if ($employee) {
            $this->updateTripDestination($employee, $device, $eventTime, $eventType);
        }

        return true;
    }

    private function updateTripDestination(
        Employee $employee,
        HikvisionDevice $device,
        Carbon $eventTime,
        string $eventType
    ): void {
        $localTime = $eventTime->copy()->setTimezone(self::TZ);
        $dateStr = $localTime->toDateString();

        // Xodimning ushbu sanada faol tasdiqlangan xizmat safarini topamiz
        $trip = BusinessTrip::query()
            ->where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->where('start_date', '<=', $dateStr)
            ->whereRaw('COALESCE(extended_end_date, end_date) >= ?', [$dateStr])
            ->first();

        if (! $trip) {
            return;
        }

        // Qurilma tashkiloti mos keladigan destinatsiyani topamiz
        $destination = BusinessTripDestination::query()
            ->where('business_trip_id', $trip->id)
            ->where('organization_id', $device->organization_id)
            ->first();

        if (! $destination) {
            return;
        }

        if ($eventType === 'entry' && $destination->arrival_date === null) {
            // Birinchi kirish — arrival sana va vaqtini yozamiz
            $destination->update(['arrival_date' => $localTime]);
        } elseif ($eventType === 'exit') {
            // Oxirgi chiqish — departure sana va vaqtini yangilaymiz
            $destination->update(['departure_date' => $localTime]);
        }
    }

    private function recalculateDailyAttendance(int $organizationId, Carbon $from, Carbon $to): void
    {
        $employees = Employee::query()
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->pluck('id');

        $current = $from->copy()->startOfDay();

        while ($current->lte($to)) {
            $date = $current->toDateString();

            foreach ($employees as $employeeId) {
                $this->calculateDayForEmployee($employeeId, $organizationId, $date);
            }

            $current->addDay();
        }
    }

    private function calculateDayForEmployee(int $employeeId, int $organizationId, string $date): void
    {
        // Xizmat safarida ekan? (uzaytirilgan muddatni ham hisobga olamiz)
        $onTrip = BusinessTrip::query()
            ->where('employee_id', $employeeId)
            ->where('status', 'approved')
            ->where('start_date', '<=', $date)
            ->whereRaw('COALESCE(extended_end_date, end_date) >= ?', [$date])
            ->exists();

        if ($onTrip) {
            DailyAttendance::updateOrCreate(
                ['employee_id' => $employeeId, 'work_date' => $date],
                ['organization_id' => $organizationId, 'status' => 'business_trip', 'work_minutes' => 0]
            );

            return;
        }

        $logs = AttendanceLog::query()
            ->where('employee_id', $employeeId)
            ->whereDate('event_time', $date)
            ->orderBy('event_time')
            ->get();

        if ($logs->isEmpty()) {
            DailyAttendance::updateOrCreate(
                ['employee_id' => $employeeId, 'work_date' => $date],
                ['organization_id' => $organizationId, 'status' => 'absent', 'work_minutes' => 0]
            );

            return;
        }

        $firstEntry = $logs->where('event_type', 'entry')->sortBy('event_time')->first()
            ?? $logs->sortBy('event_time')->first();

        $lastExit = $logs->where('event_type', 'exit')->sortByDesc('event_time')->first()
            ?? $logs->sortByDesc('event_time')->first();

        $workMinutes = 0;
        $status = 'present';

        if ($firstEntry && $lastExit && $firstEntry->id !== $lastExit->id) {
            $workMinutes = max(0, (int) $firstEntry->event_time->diffInMinutes($lastExit->event_time));
        }

        // Kech keldi chegarasi: tashkilot ish jadvalidan olish
        $schedule = WorkSchedule::where('organization_id', $organizationId)->where('is_default', true)->first();
        $startTime = $schedule?->work_start ?? '08:00:00';
        $tolerance = $schedule?->late_tolerance_minutes ?? 15;
        $workStart = Carbon::parse($date.' '.$startTime, self::TZ)->addMinutes($tolerance);
        $entryTime = $firstEntry?->event_time->setTimezone(self::TZ);

        if ($entryTime && $entryTime->gt($workStart)) {
            $status = 'late';
        }

        if ($workMinutes > 0 && $workMinutes < 240) {
            $status = 'half_day';
        }

        DailyAttendance::updateOrCreate(
            ['employee_id' => $employeeId, 'work_date' => $date],
            [
                'organization_id' => $organizationId,
                'first_entry' => $firstEntry?->event_time->setTimezone(self::TZ)->format('H:i:s'),
                'last_exit' => ($lastExit && $lastExit->id !== $firstEntry?->id)
                    ? $lastExit->event_time->setTimezone(self::TZ)->format('H:i:s')
                    : null,
                'work_minutes' => $workMinutes,
                'status' => $status,
            ]
        );
    }
}
