<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('pos:heartbeat')->hourly();
Schedule::command('pos:backup')->dailyAt('02:00');
Schedule::command('db:backup --clean')->dailyAt('01:00');
