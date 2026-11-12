<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Run with: php artisan schedule:work   (and php artisan queue:listen for the notifications)

// Keep the next 7 days of doses ready.
Schedule::command('doses:generate')->dailyAt('00:05')->withoutOverlapping();

// Re-score everyone each night. A patient is also re-scored right after a missed dose.
Schedule::command('risk:assess')->dailyAt('00:30')->withoutOverlapping();

// Every minute: close out overdue doses first, then remind for the ones that are due.
Schedule::command('doses:mark-missed')->everyMinute()->withoutOverlapping();
Schedule::command('reminders:dispatch')->everyMinute()->withoutOverlapping();
