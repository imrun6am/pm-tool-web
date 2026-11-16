<?php

namespace App\Jobs;

use App\Models\ClaudeHabitReport;
use App\Services\Ai\AiException;
use App\Services\Ai\ClaudeHabitAnalyst;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/** Writes one Claude Code habit report; the page polls until it's done. */
class GenerateClaudeHabitReport implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    /** Seconds; a long period of prompts at high effort takes a while. */
    public int $timeout = 300;

    /** @var list<int> */
    public array $backoff = [60];

    public function __construct(public int $reportId) {}

    public function handle(ClaudeHabitAnalyst $analyst): void
    {
        $report = ClaudeHabitReport::with(['user', 'requester'])->find($this->reportId);
        if (! $report || ! $report->running() || ! $report->user) {
            return;
        }

        $report->update(['status' => ClaudeHabitReport::STATUS_PROCESSING]);

        try {
            $analyst->run($report);
        } catch (AiException $e) {
            // Switched off, no key, nothing to review, refused, cut off: retrying won't help.
            if (! $e->retryable) {
                $this->failed($e);

                return;
            }

            throw $e;
        }
    }

    public function failed(?Throwable $e): void
    {
        ClaudeHabitReport::whereKey($this->reportId)
            ->whereIn('status', [ClaudeHabitReport::STATUS_QUEUED, ClaudeHabitReport::STATUS_PROCESSING])
            ->update([
                'status' => ClaudeHabitReport::STATUS_FAILED,
                'error' => mb_strimwidth($e?->getMessage() ?? 'Unknown error', 0, 500, '…'),
            ]);
    }
}
