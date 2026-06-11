<?php

namespace App\Listeners;

use App\Models\ScheduleRun;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Event;
use Throwable;

class RecordScheduledRun
{
    /**
     * Tracks running rows so finish/fail/skip events can update the same record.
     *
     * @var array<int, int>  Task object hash → schedule_runs.id
     */
    private static array $pendingRowIds = [];

    public function starting(ScheduledTaskStarting $event): void
    {
        try {
            $row = ScheduleRun::create([
                'command' => $this->commandLabel($event->task),
                'expression' => $event->task->expression,
                'status' => 'running',
                'started_at' => now(),
                'manual' => false,
            ]);

            self::$pendingRowIds[spl_object_id($event->task)] = $row->id;
        } catch (Throwable) {
            // Don't disturb the actual scheduled run if logging fails.
        }
    }

    public function finished(ScheduledTaskFinished $event): void
    {
        $this->updateRow($event->task, [
            'status' => 'success',
            'finished_at' => now(),
            'duration_ms' => (int) round($event->runtime * 1000),
            'exit_code' => 0,
            'output_excerpt' => $this->tail($event->task),
        ]);
    }

    public function failed(ScheduledTaskFailed $event): void
    {
        $this->updateRow($event->task, [
            'status' => 'failed',
            'finished_at' => now(),
            'exit_code' => $event->task->exitCode ?? 1,
            'output_excerpt' => mb_substr((string) ($event->exception?->getMessage() ?? 'Unknown failure'), -4000),
        ]);
    }

    public function skipped(ScheduledTaskSkipped $event): void
    {
        $this->updateRow($event->task, [
            'status' => 'skipped',
            'finished_at' => now(),
            'output_excerpt' => 'Skipped — previous run still in progress (withoutOverlapping).',
        ]);
    }

    private function updateRow(Event $task, array $attributes): void
    {
        $key = spl_object_id($task);
        $id = self::$pendingRowIds[$key] ?? null;

        if ($id === null) {
            return;
        }

        unset(self::$pendingRowIds[$key]);

        try {
            ScheduleRun::where('id', $id)->update($attributes);
        } catch (Throwable) {
            // Same intent as starting() — never block scheduler output.
        }
    }

    private function commandLabel(Event $task): string
    {
        if (!empty($task->description)) {
            return mb_substr((string) $task->description, 0, 200);
        }

        $cmd = (string) $task->command;

        // Strip the PHP binary path and "artisan" prefix Laravel adds.
        if (preg_match('/[\'"]?artisan[\'"]?\s+(?<name>.+)$/', $cmd, $m)) {
            return mb_substr(trim($m['name']), 0, 200);
        }

        return mb_substr($cmd, 0, 200);
    }

    private function tail(Event $task): ?string
    {
        $path = $task->output ?? null;
        if (!$path || !is_string($path) || !is_file($path)) {
            return null;
        }

        $contents = @file_get_contents($path);
        if ($contents === false) {
            return null;
        }

        return mb_substr($contents, -4000);
    }
}
