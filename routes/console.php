<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('pos:heartbeat')->hourly();

// Nightly Automated Database Backup at 11:00 PM IST (23:00)
Schedule::command('pos:backup --type=nightly')
    ->dailyAt('23:00')
    ->timezone('Asia/Kolkata');

Schedule::command('db:backup --clean')
    ->dailyAt('23:00')
    ->timezone('Asia/Kolkata');
