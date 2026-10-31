<?php

namespace App\Services;

use App\Models\PerformanceCompetency;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * The competency catalog.
 *
 * The rule that matters: a competency with recorded scores is **deactivated,
 * never deleted**. Its `key` anchors historical ratings, so removing the row
 * would orphan every score taken against it — the reason `key` is also
 * immutable once assigned.
 */
class PerformanceCompetencyService
{
    /**
     * @param  array{cadence?: ?string, active_only?: bool}  $filters
     * @return Builder<PerformanceCompetency>
     */
    public function catalog(array $filters = []): Builder
    {
        return PerformanceCompetency::ordered()
            ->withCount('scores')
            ->when($filters['cadence'] ?? null, fn ($q, $cadence) => $q->forCadence($cadence))
            ->when($filters['active_only'] ?? false, fn ($q) => $q->active());
    }

    /**
     * Create a competency, deriving its immutable key and appending it to the
     * end of the catalog when no position is given.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): PerformanceCompetency
    {
        $attributes['key'] = $this->uniqueKey($attributes['name']);
        $attributes['position'] ??= (int) PerformanceCompetency::max('position') + 1;

        return PerformanceCompetency::create($attributes);
    }

    /**
     * Update a competency. `key` is never touched — historical scores are
     * anchored to it.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(PerformanceCompetency $competency, array $attributes): PerformanceCompetency
    {
        unset($attributes['key']);

        $competency->update($attributes);

        return $competency;
    }

    /** Flip active/inactive — the safe way to retire a scored competency. */
    public function toggle(PerformanceCompetency $competency): PerformanceCompetency
    {
        $competency->update(['active' => ! $competency->active]);

        return $competency;
    }

    /** A competency may only be deleted while nothing has been scored against it. */
    public function isDeletable(PerformanceCompetency $competency): bool
    {
        return ! $competency->scores()->exists();
    }

    /** A URL-safe key derived from the name, unique across the catalog. */
    public function uniqueKey(string $name): string
    {
        $base = Str::slug($name) ?: 'competency';
        $key = $base;
        $i = 2;

        while (PerformanceCompetency::where('key', $key)->exists()) {
            $key = $base.'-'.$i++;
        }

        return $key;
    }
}
