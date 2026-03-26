<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PublicHolidaySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $year = now()->year;
        $years = [$year - 1, $year, $year + 1];

        // O'zbekiston doimiy milliy bayramlari (oy-kun)
        $recurring = [
            ['01-01', "Yangi yil"],
            ['01-14', "Vatan himoyachilari kuni"],
            ['03-08', "Xalqaro xotin-qizlar kuni"],
            ['03-21', "Navro'z bayrami"],
            ['03-22', "Navro'z bayrami"],
            ['03-23', "Navro'z bayrami"],
            ['05-09', "Xotira va qadrlash kuni"],
            ['09-01', "Mustaqillik kuni"],
            ['09-02', "Mustaqillik kuni (dam olish)"],
            ['10-01', "Ustoz va murabbiylar kuni"],
            ['12-08', "O'zbekiston Konstitutsiyasi kuni"],
        ];

        foreach ($years as $y) {
            foreach ($recurring as [$md, $name]) {
                \App\Models\PublicHoliday::updateOrCreate(
                    ['holiday_date' => "{$y}-{$md}"],
                    ['name' => $name, 'year' => $y, 'is_recurring' => true]
                );
            }
        }
    }
}
