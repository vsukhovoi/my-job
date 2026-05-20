<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('mono:check-payments')->everyThirtyMinutes();
Schedule::command('invoices:check-payments')->everyFiveMinutes();

Schedule::command('app:send-vacancy-alerts')->hourly();
Schedule::command('app:deactivate-expired-featured')->daily();
Schedule::command('app:deactivate-incomplete-profile-vacancies')->hourly();
Schedule::command('app:deactivate-expired-promos')->hourly();
Schedule::command('app:cleanup-temp-uploads --hours=24')->daily();
Schedule::command('interviews:mark-expired')->daily();

Schedule::command('vacancies:notify-expiring')
    ->hourly()
    ->withoutOverlapping(10)
    ->onOneServer()
    ->name('vacancies.notify-expiring');

Schedule::command('vacancies:refresh-anonymous')
    ->weekly()
    ->mondays()
    ->at('06:00');

Schedule::command('vacancies:expire')
    ->hourly()
    ->withoutOverlapping(10)
    ->runInBackground()
    ->onOneServer()
    ->name('vacancies.expire')
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::channel('vacancies')->error('Scheduled vacancies:expire failed');
    });
