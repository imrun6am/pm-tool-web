<?php

namespace App\Models\Concerns;

use App\Models\Activity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Lightweight, in-app activity logging. Any model using this trait records a
 * `created` / `updated` / `deleted` activity entry — with the acting user and,
 * for updates, the changed fields' old → new values.
 *
 * Implemented directly (no external package) so it is guaranteed to work on the
 * app's Laravel version and stays fully under our control.
 */
trait RecordsActivity
{
    /**
     * Global switch so bulk operations (e.g. seeding) can skip logging.
     */
    protected static bool $activityLoggingEnabled = true;

    public static function disableActivityLogging(): void
    {
        static::$activityLoggingEnabled = false;
    }

    public static function enableActivityLogging(): void
    {
        static::$activityLoggingEnabled = true;
    }

    public static function bootRecordsActivity(): void
    {
        static::created(fn (Model $model) => $model->recordActivity('created'));
        static::updated(fn (Model $model) => $model->recordActivity('updated'));
        static::deleted(fn (Model $model) => $model->recordActivity('deleted'));
    }

    public function recordActivity(string $event): void
    {
        if (! static::$activityLoggingEnabled) {
            return;
        }

        $properties = $this->activityProperties($event);

        // Nothing meaningful changed on an update (only ignored fields) → skip.
        if ($event === 'updated' && empty($properties['attributes'])) {
            return;
        }

        Activity::create([
            'log_name' => $this->activityLogName(),
            'description' => $this->activityDescription($event),
            'event' => $event,
            'subject_type' => $this->getMorphClass(),
            'subject_id' => $this->getKey(),
            'causer_id' => Auth::id(),
            'release_id' => $this->activityReleaseId(),
            'properties' => $properties,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function activityProperties(string $event): array
    {
        $ignore = $this->activityIgnored();

        if ($event === 'updated') {
            $new = collect($this->getChanges())->except($ignore);
            $old = collect($this->getOriginal())->only($new->keys());

            return [
                'old' => $old->map(fn ($v) => $this->stringifyValue($v))->all(),
                'attributes' => $new->map(fn ($v) => $this->stringifyValue($v))->all(),
            ];
        }

        // created / deleted → a single snapshot of the relevant attributes.
        $attributes = collect($this->getAttributes())->except($ignore)
            ->map(fn ($v) => $this->stringifyValue($v))->all();

        return ['attributes' => $attributes];
    }

    protected function stringifyValue(mixed $value): mixed
    {
        return $value instanceof \DateTimeInterface
            ? $value->format('Y-m-d H:i:s')
            : $value;
    }

    /**
     * Fields never worth logging. Models may extend via activityExtraIgnored().
     *
     * @return array<int, string>
     */
    protected function activityIgnored(): array
    {
        return array_merge(['id', 'created_at', 'updated_at', 'password', 'remember_token'], $this->activityExtraIgnored());
    }

    /**
     * @return array<int, string>
     */
    protected function activityExtraIgnored(): array
    {
        return [];
    }

    protected function activityLogName(): string
    {
        return class_basename($this);
    }

    protected function activityDescription(string $event): string
    {
        return sprintf('%s %s “%s”', ucfirst($event), strtolower(class_basename($this)), $this->activityTitle());
    }

    /**
     * A short, human label for this record. Models should override.
     */
    public function activityTitle(): string
    {
        return (string) ($this->getAttribute('name') ?? $this->getAttribute('title') ?? '#'.$this->getKey());
    }

    /**
     * The Release this activity ultimately belongs to (for per-release history),
     * or null if it is not release-scoped. Models override when applicable.
     */
    public function activityReleaseId(): ?int
    {
        return null;
    }
}
