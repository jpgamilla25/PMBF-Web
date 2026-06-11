<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ScheduleRun;
use App\Traits\ApiResponse;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Throwable;

class AdminScheduleController extends Controller
{
    use ApiResponse;

    /**
     * Defined scheduled tasks + their last run + next run.
     */
    public function index(): JsonResponse
    {
        $schedule = app(Schedule::class);
        $tasks = collect($schedule->events())->map(function (Event $event): array {
            $label = $this->commandLabel($event);
            $lastRun = ScheduleRun::where('command', $label)->latest('started_at')->first();

            return [
                'command' => $label,
                'description' => $event->description,
                'expression' => $event->expression,
                'timezone' => $event->timezone,
                'next_run_at' => $this->safe(fn () => $event->nextRunDate()?->format(DATE_ATOM)),
                'without_overlapping' => $event->withoutOverlapping,
                'last_run' => $lastRun ? $this->formatRun($lastRun) : null,
            ];
        })->values();

        $recent = ScheduleRun::latest('started_at')
            ->limit(50)
            ->get()
            ->map(fn (ScheduleRun $row) => $this->formatRun($row));

        return $this->success([
            'tasks' => $tasks,
            'recent_runs' => $recent,
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /**
     * Run one of the scheduled commands now, synchronously.
     */
    public function run(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'command' => 'required|string|max:200',
        ]);

        @set_time_limit(0);
        @ignore_user_abort(true);

        $label = $validated['command'];

        if (!$this->isKnownScheduledCommand($label)) {
            return $this->error('That command is not registered in the scheduler.', 422);
        }

        $row = ScheduleRun::create([
            'command' => $label,
            'expression' => null,
            'status' => 'running',
            'started_at' => now(),
            'manual' => true,
        ]);

        $started = microtime(true);
        try {
            $exitCode = Artisan::call($this->artisanName($label));
            $output = Artisan::output();
            $durationMs = (int) round((microtime(true) - $started) * 1000);

            $row->update([
                'status' => $exitCode === 0 ? 'success' : 'failed',
                'exit_code' => $exitCode,
                'finished_at' => now(),
                'duration_ms' => $durationMs,
                'output_excerpt' => mb_substr($output, -4000),
            ]);
        } catch (Throwable $e) {
            $row->update([
                'status' => 'failed',
                'exit_code' => 1,
                'finished_at' => now(),
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                'output_excerpt' => mb_substr($e->getMessage(), -4000),
            ]);

            return $this->error('Command failed: ' . $e->getMessage(), 500);
        }

        return $this->success(
            $this->formatRun($row->fresh()),
            $row->status === 'success' ? "Ran {$label}." : "{$label} exited with code {$row->exit_code}."
        );
    }

    private function isKnownScheduledCommand(string $label): bool
    {
        return collect(app(Schedule::class)->events())
            ->contains(fn (Event $event) => $this->commandLabel($event) === $label);
    }

    private function artisanName(string $label): string
    {
        // The schedule label is the trimmed artisan command (incl. arguments).
        // Artisan::call accepts "name with args" as a single string.
        return $label;
    }

    private function commandLabel(Event $event): string
    {
        if (!empty($event->description)) {
            return mb_substr((string) $event->description, 0, 200);
        }

        $cmd = (string) $event->command;
        if (preg_match('/[\'"]?artisan[\'"]?\s+(?<name>.+)$/', $cmd, $m)) {
            return mb_substr(trim($m['name']), 0, 200);
        }

        return mb_substr($cmd, 0, 200);
    }

    private function formatRun(ScheduleRun $row): array
    {
        return [
            'id' => $row->id,
            'command' => $row->command,
            'status' => $row->status,
            'exit_code' => $row->exit_code,
            'started_at' => $row->started_at?->toIso8601String(),
            'finished_at' => $row->finished_at?->toIso8601String(),
            'duration_ms' => $row->duration_ms,
            'manual' => (bool) $row->manual,
            'output_excerpt' => $row->output_excerpt,
        ];
    }

    private function safe(callable $fn): mixed
    {
        try {
            return $fn();
        } catch (Throwable) {
            return null;
        }
    }
}
