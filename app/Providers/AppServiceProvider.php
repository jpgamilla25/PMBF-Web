<?php

namespace App\Providers;

use App\Listeners\RecordScheduledRun;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
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

        $this->configureRateLimiters();
    }

    /**
     * Rate limits for the passwordless auth endpoints. Employee IDs follow a
     * guessable pattern (YY-NNNN), so both OTP sends and code/PIN guesses are
     * capped per employee ID and per IP.
     */
    private function configureRateLimiters(): void
    {
        // Off by default outside production so development and QA aren't
        // interrupted by "Too Many Attempts". Set AUTH_THROTTLE=true to
        // exercise the limits locally, or AUTH_THROTTLE=false to disable them
        // in production (not recommended — see the note below).
        $enabled = (bool) env('AUTH_THROTTLE', $this->app->isProduction());

        if (!$enabled) {
            foreach (['otp-send', 'otp-verify', 'pin-attempt', 'pin-status'] as $name) {
                RateLimiter::for($name, fn () => Limit::none());
            }

            return;
        }

        // Emailing an OTP — keeps someone from spamming a member's inbox.
        RateLimiter::for('otp-send', fn (Request $request) => [
            Limit::perMinutes(5, 10)->by('id:' . $request->input('employee_id', $request->input('email', 'none'))),
            Limit::perMinutes(5, 30)->by('ip:' . $request->ip()),
        ]);

        // Submitting an OTP code — 6 digits, so guessing must be expensive.
        RateLimiter::for('otp-verify', fn (Request $request) => [
            Limit::perMinutes(10, 20)->by('id:' . $request->input('employee_id', 'none')),
            Limit::perMinutes(10, 60)->by('ip:' . $request->ip()),
        ]);

        // Submitting a PIN — the per-account lockout in AuthService is the real
        // defence; this stops distributed guessing across many accounts.
        RateLimiter::for('pin-attempt', fn (Request $request) => [
            Limit::perMinute(15)->by('id:' . $request->input('employee_id', 'none')),
            Limit::perMinute(60)->by('ip:' . $request->ip()),
        ]);

        // Cheap probe the login screen makes on load.
        RateLimiter::for('pin-status', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
    }
}
