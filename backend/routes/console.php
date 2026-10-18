<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('backup:database')->dailyAt('02:00');

// Safety net: keep whatsapp_sessions in sync with the node service so OTP
// messages are never routed to a device that is no longer connected.
Schedule::command('whatsapp:sync-sessions')->everyMinute()->withoutOverlapping();
