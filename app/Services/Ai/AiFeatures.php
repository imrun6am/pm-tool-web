<?php

namespace App\Services\Ai;

use App\Models\AiFeatureSetting;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

/**
 * The catalog of Claude features (config/ai.php) and their on/off state.
 * An admin's switch on the AI usage page overrides the config default.
 */
class AiFeatures
{
    private const CACHE_KEY = 'ai.feature-overrides';

    /** @return array<string, array{label: string, description: string, model: string, effort: string, enabled: bool}> */
    public function all(): array
    {
        $overrides = $this->overrides();

        return collect(config('ai.features'))
            ->map(fn (array $f, string $key) => ['enabled' => $overrides[$key] ?? (bool) $f['enabled']] + $f)
            ->all();
    }

    /** @return array{label: string, description: string, model: string, effort: string, enabled: bool} */
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
        $this->get($feature); // unknown keys are rejected

        AiFeatureSetting::updateOrCreate(['feature' => $feature], ['enabled' => $enabled, 'updated_by' => $by->id]);
        Cache::forget(self::CACHE_KEY);
    }

    /** @return array<string, bool> */
    private function overrides(): array
    {
        try {
            return Cache::remember(self::CACHE_KEY, 300, fn () => AiFeatureSetting::pluck('enabled', 'feature')
                ->map(fn ($v) => (bool) $v)->all());
        } catch (QueryException) {
            return []; // no database (or not migrated yet): the config defaults apply
        }
    }
}
