<?php

namespace App\Providers;

use App\Listeners\RecordScheduledRun;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(ScheduledTaskStarting::class, [RecordScheduledRun::class, 'starting']);
        Event::listen(ScheduledTaskFinished::class, [RecordScheduledRun::class, 'finished']);
        Event::listen(ScheduledTaskFailed::class, [RecordScheduledRun::class, 'failed']);
        Event::listen(ScheduledTaskSkipped::class, [RecordScheduledRun::class, 'skipped']);
    }
}
