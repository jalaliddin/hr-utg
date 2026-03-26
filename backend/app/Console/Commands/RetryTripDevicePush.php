<?php

namespace App\Console\Commands;

use App\Models\BusinessTripDestination;
use App\Services\BusinessTripHikvisionService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('trips:retry-push')]
#[Description('Muvaffaqiyatsiz xizmat safari qurilma yuklashlarini qayta sinab ko\'radi')]
class RetryTripDevicePush extends Command
{
    public function handle(BusinessTripHikvisionService $service): int
    {
        $destinations = BusinessTripDestination::query()
            ->where('push_status', 'failed')
            ->where(function ($q) {
                $q->whereNull('retry_after')
                    ->orWhere('retry_after', '<=', now());
            })
            ->with(['businessTrip.employee', 'organization'])
            ->get();

        if ($destinations->isEmpty()) {
            $this->info('Qayta sinab ko\'riladigan yuklash yo\'q.');

            return Command::SUCCESS;
        }

        $this->info("Topildi: {$destinations->count()} ta destinatsiya");

        $successCount = 0;

        foreach ($destinations as $destination) {
            $result = $service->retryDestination($destination);

            if ($result) {
                $successCount++;
                $this->line("  + Destination #{$destination->id} muvaffaqiyatli");
            } else {
                $this->line("  - Destination #{$destination->id} muvaffaqiyatsiz");
            }
        }

        $this->info("Yakunlandi: {$successCount}/{$destinations->count()} muvaffaqiyatli.");

        return Command::SUCCESS;
    }
}
