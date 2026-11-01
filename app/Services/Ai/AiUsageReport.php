<?php

namespace App\Services\Ai;

use App\Models\AiUsageLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** The numbers behind the admin AI usage page. */
class AiUsageReport
{
    public function __construct(private readonly AiFeatures $features) {}

    /**
     * Per-feature totals since $since, every catalog feature present even when unused.
     *
     * @return Collection<string, array<string, mixed>>
     */
    public function byFeature(Carbon $since): Collection
    {
        $rows = AiUsageLog::query()
            ->where('created_at', '>=', $since)
            ->selectRaw('feature, model, count(*) as calls, sum(case when status = ? then 1 else 0 end) as failures,
                sum(input_tokens) as input_tokens, sum(output_tokens) as output_tokens,
                sum(cache_read_tokens) as cache_read_tokens, avg(duration_ms) as avg_ms', ['ok'])
            ->groupBy('feature', 'model')
            ->get()
            ->groupBy('feature');

        return collect($this->features->all())->map(function (array $feature, string $key) use ($rows) {
            $models = $rows->get($key, collect());
            $cost = $models->sum(fn ($r) => AiUsageLog::cost($r->model, (int) $r->input_tokens, (int) $r->output_tokens, (int) $r->cache_read_tokens) ?? 0);
            $calls = (int) $models->sum('calls');

            return $feature + [
                'key' => $key,
                'calls' => $calls,
                'failures' => (int) $models->sum('failures'),
                'input_tokens' => (int) $models->sum('input_tokens'),
                'output_tokens' => (int) $models->sum('output_tokens'),
                'cost' => round($cost, 4),
                'avg_seconds' => $calls > 0 ? round($models->avg('avg_ms') / 1000, 1) : null,
            ];
        });
    }

    /**
     * Calls per day since $since, for the trend chart.
     *
     * @return list<array{date: string, calls: int}>
     */
    public function daily(Carbon $since): array
    {
        $counts = AiUsageLog::query()
            ->where('created_at', '>=', $since->copy()->startOfDay())
            ->get(['created_at'])
            ->countBy(fn (AiUsageLog $log) => $log->created_at->toDateString());

        $days = [];
        for ($day = $since->copy()->startOfDay(); $day->lte(today()); $day->addDay()) {
            $days[] = ['date' => $day->toDateString(), 'calls' => (int) ($counts[$day->toDateString()] ?? 0)];
        }

        return $days;
    }

    /** @return Collection<int, AiUsageLog> */
    public function recent(int $limit = 25): Collection
    {
        return AiUsageLog::with('user:id,name')->latest('id')->limit($limit)->get();
    }
}
