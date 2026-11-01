<?php

namespace App\Services\Ai;

use App\Models\AiFeatureSetting;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

/**
 * The catalog of Claude features (config/ai.php) with their effective
 * on/off state, model and effort. An admin's choices on the AI usage page
 * override the config defaults; a model no longer offered falls back to the
 * default rather than being sent to the API.
 */
class AiFeatures
{
    private const CACHE_KEY = 'ai.feature-overrides';

    /** @return array<string, array{label: string, description: string, model: string, effort: string, enabled: bool, tracked: bool, customized: bool}> */
    public function all(): array
    {
        $overrides = $this->overrides();
        $models = array_keys(config('ai.models'));
        $efforts = array_keys(config('ai.efforts'));

        return collect(config('ai.features'))
            ->map(function (array $f, string $key) use ($overrides, $models, $efforts) {
                $o = $overrides[$key] ?? [];
                $model = $o['model'] ?? $f['model'];
                $effort = $o['effort'] ?? $f['effort'];

                return [
                    'enabled' => $o['enabled'] ?? (bool) $f['enabled'],
                    'model' => in_array($model, $models, true) ? $model : config('ai.default_model'),
                    'effort' => in_array($effort, $efforts, true) ? $effort : 'medium',
                    'tracked' => $f['tracked'] ?? true,
                    'customized' => isset($o['model']) || isset($o['effort']),
                ] + $f;
            })
            ->all();
    }

    /** @return array{label: string, description: string, model: string, effort: string, enabled: bool, tracked: bool, customized: bool} */
    public function get(string $feature): array
    {
        return $this->all()[$feature] ?? throw new InvalidArgumentException("Unknown AI feature [{$feature}].");
    }

    public function enabled(string $feature): bool
    {
        return $this->get($feature)['enabled'];
    }

    /** Whether the API key is configured at all. */
    public function configured(): bool
    {
        return filled(config('services.anthropic.key'));
    }

    /** Whether a feature can run right now: switched on and a key is set. */
    public function available(string $feature): bool
    {
        return $this->configured() && $this->enabled($feature);
    }

    public function set(string $feature, bool $enabled, User $by): void
    {
        $this->save($feature, ['enabled' => $enabled], $by);
    }

    /**
     * Choose a feature's model and effort. Unknown values are rejected; pass
     * null to go back to the config default.
     */
    public function configure(string $feature, ?string $model, ?string $effort, User $by): void
    {
        if ($model !== null && ! array_key_exists($model, $this->modelsFor($feature))) {
            throw new InvalidArgumentException("Model [{$model}] is not offered for this feature.");
        }
        if ($effort !== null && ! array_key_exists($effort, config('ai.efforts'))) {
            throw new InvalidArgumentException("Effort [{$effort}] is not offered.");
        }

        $this->save($feature, ['model' => $model, 'effort' => $effort], $by);
    }

    /**
     * The models an admin may pick for a feature. Untracked features call the
     * SDK themselves with the refusal fallback on, so models without it are left out.
     *
     * @return array<string, string> model id => label
     */
    public function modelsFor(string $feature): array
    {
        $models = config('ai.models');

        return $this->get($feature)['tracked']
            ? $models
            : array_diff_key($models, array_flip(config('ai.no_fallback', [])));
    }

    private function save(string $feature, array $values, User $by): void
    {
        $this->get($feature); // unknown keys are rejected

        AiFeatureSetting::updateOrCreate(['feature' => $feature], $values + ['updated_by' => $by->id]);
        Cache::forget(self::CACHE_KEY);
    }

    /** @return array<string, array{enabled?: bool, model?: string, effort?: string}> */
    private function overrides(): array
    {
        try {
            return Cache::remember(self::CACHE_KEY, 300, fn () => AiFeatureSetting::all()
                ->mapWithKeys(fn (AiFeatureSetting $s) => [$s->feature => array_filter([
                    'enabled' => $s->enabled,
                    'model' => $s->model,
                    'effort' => $s->effort,
                ], fn ($v) => $v !== null)])
                ->all());
        } catch (QueryException) {
            return []; // no database (or not migrated yet): the config defaults apply
        }
    }
}
