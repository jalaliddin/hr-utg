<?php

namespace App\Services;

use App\Models\CertificateCounter;
use Illuminate\Support\Facades\DB;

class CertificateNumberService
{
    /**
     * Yangi sertifikat raqamini generatsiya qiladi.
     * Race condition-safe: lockForUpdate() ishlatiladi.
     *
     * @return array{number: int, serial: string, year: int}
     */
    public function generate(): array
    {
        $year = (int) now()->format('Y');

        $counter = DB::transaction(function () use ($year) {
            $counter = CertificateCounter::lockForUpdate()
                ->firstOrCreate(
                    ['year' => $year],
                    ['last_number' => 0]
                );

            $counter->increment('last_number');
            $counter->refresh();

            return $counter;
        });

        $number = $counter->last_number;
        $serial = str_pad((string) $number, 3, '0', STR_PAD_LEFT).'/'.substr((string) $year, -2);

        return [
            'number' => $number,
            'serial' => $serial,
            'year' => $year,
        ];
    }
}
