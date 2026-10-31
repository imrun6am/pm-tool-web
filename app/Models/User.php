<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\RecordsActivity;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role', 'deactivated_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, RecordsActivity, SoftDeletes;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_CTO = 'cto';

    public const ROLE_TECH_LEAD = 'tech_lead';

    public const ROLE_TEAM_LEAD = 'team_lead';

    public const ROLE_DEVELOPER = 'developer';

    public const ROLE_QA = 'qa';

    public const ROLE_VIEWER = 'viewer';

    /**
     * All supported roles, slug => label.
     *
     * @var array<string, string>
     */
    public const ROLES = [
        self::ROLE_ADMIN => 'Admin',
        self::ROLE_CTO => 'CTO',
        self::ROLE_TECH_LEAD => 'Tech Lead',
        self::ROLE_TEAM_LEAD => 'Team Lead',
        self::ROLE_DEVELOPER => 'Developer',
        self::ROLE_QA => 'QA',
        self::ROLE_VIEWER => 'Viewer',
    ];

    /**
     * The leadership tier. These four roles share one identical set of
     * permissions — anything one of them can do, all of them can. Every
     * capability check below funnels through this list so the tiers never
     * drift apart.
     *
     * @var list<string>
     */
    public const LEAD_ROLES = [
        self::ROLE_ADMIN,
        self::ROLE_CTO,
        self::ROLE_TECH_LEAD,
        self::ROLE_TEAM_LEAD,
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'deactivated_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected static function booted(): void
    {
        // Vacate any team this user leads on delete. Users are soft-deleted, so
        // the team_lead_id FK's nullOnDelete never fires — clear it here so it
        // never points at a departed lead, whatever triggered the delete.
        static::deleting(function (User $user) {
            Team::where('team_lead_id', $user->id)->update(['team_lead_id' => null]);
        });
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isTeamLead(): bool
    {
        return $this->role === self::ROLE_TEAM_LEAD;
    }

    public function isTechLead(): bool
    {
        return $this->role === self::ROLE_TECH_LEAD;
    }

    public function isViewer(): bool
    {
        return $this->role === self::ROLE_VIEWER;
    }

    /**
     * Whether this user belongs to the leadership tier (admin, CTO, tech lead,
     * team lead). All four share the same access, so every capability check is
     * an alias of this method.
     */
    public function isLead(): bool
    {
        return in_array($this->role, self::LEAD_ROLES, true);
    }

    /** Leads may manage user accounts. */
    public function canManageUsers(): bool
    {
        return $this->isLead();
    }

    /** Leads may plan releases (create/edit, off-days, documents). */
    public function canManageReleases(): bool
    {
        return $this->isLead();
    }

    /** Leads may add/remove people on a team. */
    public function canManageTeamMembers(): bool
    {
        return $this->isLead();
    }

    /** Leads may manage projects and teams (create/edit, archive, assign leads). */
    public function canManageWorkspace(): bool
    {
        return $this->isLead();
    }

    /**
     * Org-level leadership (admin, CTO, tech lead) oversee every team's
     * performance and own the competency catalog; a team lead is scoped to the
     * team(s) they are the assigned lead of.
     */
    public function canOverseeAllTeams(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_CTO, self::ROLE_TECH_LEAD], true);
    }

    /** True when this user is the assigned lead of the given team. */
    public function leadsTeam(Team $team): bool
    {
        return $team->team_lead_id === $this->id;
    }

    /**
     * May this user view and evaluate performance for the given team? Org-level
     * leads may act on any team; a team lead only on teams they lead.
     */
    public function canAccessTeamPerformance(Team $team): bool
    {
        return $this->canOverseeAllTeams() || $this->leadsTeam($team);
    }

    /** May this user configure the competency catalog (org-level leads only). */
    public function canManageCompetencies(): bool
    {
        return $this->canOverseeAllTeams();
    }

    /**
     * Developers and QA work from the board/calendar/notes/meetings/tasksheet
     * only — planning surfaces (releases, projects, teams, activity) are off
     * limits and their dashboard is the personal member dashboard.
     */
    public function hasLimitedAccess(): bool
    {
        return in_array($this->role, [self::ROLE_DEVELOPER, self::ROLE_QA], true);
    }

    public function isActive(): bool
    {
        return $this->deactivated_at === null;
    }

    public function roleLabel(): string
    {
        return self::ROLES[$this->role] ?? ucfirst((string) $this->role);
    }

    /**
     * Display tag shown next to this user's name on their surviving data
     * ("Deleted user" / "Deactivated"), or null for a normal active account.
     */
    public function statusTag(): ?string
    {
        if ($this->trashed()) {
            return 'Deleted user';
        }
        if (! $this->isActive()) {
            return 'Deactivated';
        }

        return null;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('deactivated_at');
    }

    public function assignedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assignee_id');
    }

    /** Teams this user currently belongs to (left teams excluded). */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class)->withTimestamps()->wherePivotNull('left_at');
    }

    /** Teams this user is the assigned lead of (the query form of leadsTeam). */
    public function ledTeams(): HasMany
    {
        return $this->hasMany(Team::class, 'team_lead_id')->orderBy('name');
    }

    /** Releases this user is assigned to. */
    public function releases(): BelongsToMany
    {
        return $this->belongsToMany(Release::class)->withTimestamps();
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    public function createdEvents(): HasMany
    {
        return $this->hasMany(Event::class, 'created_by');
    }

    public function attendedEvents(): BelongsToMany
    {
        return $this->belongsToMany(Event::class);
    }

    public function activityTitle(): string
    {
        return $this->name;
    }

    public function activityReleaseId(): ?int
    {
        return null;
    }

    protected function activityExtraIgnored(): array
    {
        return ['email_verified_at'];
    }
}
