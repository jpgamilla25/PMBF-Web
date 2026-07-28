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

// Sync payroll-deduction loan payments from the FMIS api-center nightly.
Schedule::command('loan-payments:sync-from-fmis')->dailyAt('03:00')->withoutOverlapping();

// Refresh employment type, pay and contract dates on the users table. List
// views read that snapshot instead of calling HRIS per row, so this is what
// keeps a pay adjustment visible to admins.
Schedule::command('hris:sync-employees')->dailyAt('02:00')->withoutOverlapping();

// Mirror HRIS employment-stint history into employment_stints. The FMIS
// shares sync consults this so a historical re-sync of a promoted member
// tags months by the stint that was active THEN, not the current snapshot.
// 15-minute offset from hris:sync-employees so we don't hammer api-center.
Schedule::command('hris:sync-employment-stints')->dailyAt('02:15')->withoutOverlapping();
