<?php

namespace App\Services\Ai\Insights;

use App\Jobs\GenerateAiInsight;
use App\Models\AiInsight;
use App\Models\User;
use App\Services\Ai\AiException;
use App\Services\Ai\AiFeatures;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Requests, runs and looks up background Claude results. A request while one
 * is already running for the same subject returns that one instead of paying
 * twice; a new request after it finished replaces the old result.
 */
class AiInsightService
{
    /** @var array<string, class-string<InsightGenerator>> */
    public const GENERATORS = [
        AiInsight::KIND_RELEASE_RISK => ReleaseRiskGenerator::class,
        AiInsight::KIND_STANDUP_DIGEST => StandupDigestGenerator::class,
        AiInsight::KIND_DOCUMENT => DocumentInsightGenerator::class,
    ];

    public function __construct(private readonly AiFeatures $features) {}

    /**
     * @throws AiException when the feature is off or unconfigured
     */
    public function request(string $kind, Model $subject, ?User $by, ?Carbon $forDate = null): AiInsight
    {
        $feature = $this->featureFor($kind);
        if (! $this->features->enabled($feature)) {
            throw AiException::disabled($this->features->get($feature)['label']);
        }
        if (! $this->features->configured()) {
            throw AiException::notConfigured();
        }

        $running = $this->query($kind, $subject, $forDate)
            ->whereIn('status', [AiInsight::STATUS_PENDING, AiInsight::STATUS_PROCESSING])
            ->first();
        if ($running) {
            return $running;
        }

        $insight = AiInsight::create([
            'kind' => $kind,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'for_date' => $forDate?->toDateString(),
            'status' => AiInsight::STATUS_PENDING,
            'requested_by' => $by?->id,
        ]);

        GenerateAiInsight::dispatch($insight->id);

        return $insight;
    }

    /** The newest insight of a kind for a subject (any status), or null. */
    public function latest(string $kind, Model $subject, ?Carbon $forDate = null): ?AiInsight
    {
        return $this->query($kind, $subject, $forDate)->latest('id')->first();
    }

    /** The newest *finished* insight, so a re-run never blanks the last good one. */
    public function latestReady(string $kind, Model $subject, ?Carbon $forDate = null): ?AiInsight
    {
        return $this->query($kind, $subject, $forDate)->where('status', AiInsight::STATUS_READY)->latest('id')->first();
    }

    /** Run the generator; called by the job. */
    public function process(AiInsight $insight): void
    {
        $insight->update(['status' => AiInsight::STATUS_PROCESSING, 'error' => null]);

        $payload = app($this->generatorClass($insight->kind))->generate($insight);

        $insight->update([
            'status' => AiInsight::STATUS_READY,
            'payload' => $payload,
            'generated_at' => now(),
        ]);

        // Keep the newest few per subject; older results add nothing.
        $this->query($insight->kind, $insight->subject, $insight->for_date)
            ->where('id', '<', $insight->id)
            ->whereNotIn('status', [AiInsight::STATUS_PENDING, AiInsight::STATUS_PROCESSING])
            ->orderByDesc('id')
            ->skip(2)->take(PHP_INT_MAX)
            ->get()->each->delete();
    }

    public function featureFor(string $kind): string
    {
        // Insight kinds are named after their config/ai.php feature.
        return array_key_exists($kind, self::GENERATORS)
            ? $kind
            : throw new InvalidArgumentException("Unknown insight kind [{$kind}].");
    }

    /** @return class-string<InsightGenerator> */
    private function generatorClass(string $kind): string
    {
        return self::GENERATORS[$kind] ?? throw new InvalidArgumentException("Unknown insight kind [{$kind}].");
    }

    private function query(string $kind, Model $subject, ?Carbon $forDate)
    {
        return AiInsight::query()
            ->where('kind', $kind)
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->when($forDate, fn ($q) => $q->whereDate('for_date', $forDate->toDateString()));
    }
}
