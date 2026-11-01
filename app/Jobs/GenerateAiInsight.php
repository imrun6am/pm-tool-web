<?php

namespace App\Jobs;

use App\Models\AiInsight;
use App\Services\Ai\AiException;
use App\Services\Ai\Insights\AiInsightService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/** Generates one background Claude result (risk brief, standup digest, document insights). */
class GenerateAiInsight implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    /** Seconds; a long document can take several minutes. Keep DB_QUEUE_RETRY_AFTER above it. */
    public int $timeout = 900;

    /** @var list<int> */
    public array $backoff = [60];

    public function __construct(public int $insightId) {}

    public function handle(AiInsightService $insights): void
    {
        $insight = AiInsight::with('subject')->find($this->insightId);
        if (! $insight || ! $insight->isRunning()) {
            return;
        }
        if (! $insight->subject) {
            $insight->delete(); // the record it described is gone

            return;
        }

        try {
            $insights->process($insight);
        } catch (AiException $e) {
            if (! $e->retryable) {
                $this->fail($e); // switched off, unconfigured or declined: retrying cannot succeed
            } else {
                throw $e;
            }
        }
    }

    public function failed(?Throwable $e): void
    {
        $message = $e instanceof AiException ? $e->getMessage() : 'Claude couldn’t finish this. Please try again.';

        AiInsight::whereKey($this->insightId)
            ->whereIn('status', [AiInsight::STATUS_PENDING, AiInsight::STATUS_PROCESSING])
            ->update(['status' => AiInsight::STATUS_FAILED, 'error' => mb_strimwidth($message, 0, 500, '…')]);
    }
}
