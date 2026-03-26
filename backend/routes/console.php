<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

Schedule::command('attendance:sync --device=all')
    ->everyFifteenMinutes()
    ->withoutOverlapping(10)
    ->runInBackground();

Schedule::command('attendance:sync --device=all')
    ->dailyAt('23:55')
    ->description('Kunlik yakuniy sinxronizatsiya');
