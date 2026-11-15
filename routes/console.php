<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Daily in-app reminders for inquiries that have become overdue (needs `php artisan schedule:run` every minute via cron).
Schedule::command('sla:remind')->dailyAt('08:00');
