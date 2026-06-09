<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Check overdue payments daily at 8:00 AM
Schedule::command('payments:check-due')->dailyAt('08:00');

// Sync share-capital contributions from the FMIS api-center nightly.
Schedule::command('shares:sync-from-fmis')->dailyAt('02:30')->withoutOverlapping();
