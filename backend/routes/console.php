<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Daily PostgreSQL backup at 02:00 UTC
Schedule::command('hisword:backup-db --keep=30')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->onOneServer();
