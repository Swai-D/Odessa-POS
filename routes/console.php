<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Subscription reminders go out every morning, Tanzania time. Needs `schedule:work` (see docker/entrypoint.sh).
Schedule::command('subscriptions:remind')->dailyAt('08:00')->timezone('Africa/Dar_es_Salaam')->withoutOverlapping();
Schedule::command('stock:low-alerts')->dailyAt('08:30')->timezone('Africa/Dar_es_Salaam')->withoutOverlapping();
Schedule::command('domains:check')->hourly()->withoutOverlapping();
Schedule::command('stock:expiry-alerts')->dailyAt('08:40')->timezone('Africa/Dar_es_Salaam')->withoutOverlapping();
