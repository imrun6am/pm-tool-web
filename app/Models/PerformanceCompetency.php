<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A scoring dimension in the performance framework. Each competency is tagged
 * with a category, a role scope (who it applies to), and a cadence (how often
 * it is rated), and carries a weight used in the blended headline score. The
 * catalog is admin/CTO-managed; deactivating (not deleting) retires a dimension
 * while keeping its historical scores intact.
 */
class PerformanceCompetency extends Model
{
    protected $fillable = [
        'key', 'name', 'description', 'category', 'role_scope', 'cadence', 'weight', 'active', 'position',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'integer',
            'active' => 'boolean',
            'position' => 'integer',
        ];
    }

    public const CATEGORY_BEHAVIORAL = 'behavioral';

    public const CATEGORY_TECHNICAL = 'technical';

    public const CATEGORY_DELIVERY = 'delivery';

    public const CATEGORY_GROWTH = 'growth';

    /** @var array<string, string> */
    public const CATEGORIES = [
        self::CATEGORY_BEHAVIORAL => 'Behavioral',
        self::CATEGORY_TECHNICAL => 'Technical',
        self::CATEGORY_DELIVERY => 'Delivery',
        self::CATEGORY_GROWTH => 'Growth',
    ];

    public const SCOPE_DEVELOPER = 'developer';

    public const SCOPE_QA = 'qa';

    public const SCOPE_BOTH = 'both';

    /** @var array<string, string> */
    public const ROLE_SCOPES = [
        self::SCOPE_DEVELOPER => 'Developers',
        self::SCOPE_QA => 'QA',
        self::SCOPE_BOTH => 'Developers & QA',
    ];

    public const CADENCE_DAILY = 'daily';

    public const CADENCE_WEEKLY = 'weekly';

    /** @var array<string, string> */
    public const CADENCES = [
        self::CADENCE_DAILY => 'Daily',
        self::CADENCE_WEEKLY => 'Weekly',
    ];

    public function scores(): HasMany
    {
        return $this->hasMany(PerformanceScore::class, 'competency_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('position')->orderBy('id');
    }

    public function scopeForCadence(Builder $query, string $cadence): Builder
    {
        return $query->where('cadence', $cadence);
    }

    /** Whether this competency applies to a member with the given role. */
    public function appliesToRole(string $role): bool
    {
        return $this->role_scope === self::SCOPE_BOTH || $this->role_scope === $role;
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? ucfirst((string) $this->category);
    }

    public function roleScopeLabel(): string
    {
        return self::ROLE_SCOPES[$this->role_scope] ?? ucfirst((string) $this->role_scope);
    }

    public function cadenceLabel(): string
    {
        return self::CADENCES[$this->cadence] ?? ucfirst((string) $this->cadence);
    }

    public function isDaily(): bool
    {
        return $this->cadence === self::CADENCE_DAILY;
    }
}
