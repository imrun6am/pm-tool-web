<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A named set of filters for a task page, for one person or shared with everyone. */
class SavedView extends Model
{
    /** The pages a view can belong to, with the filters each accepts. */
    public const PAGES = [
        'tasks' => ['q', 'release_id', 'team_id', 'assignee_id', 'status', 'priority', 'label_id', 'blocked', 'sort', 'direction'],
        'board' => ['release_id', 'team_id', 'assignee_id', 'priority', 'label_id', 'blocked'],
    ];

    protected $fillable = ['user_id', 'page', 'name', 'filters', 'is_shared'];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'is_shared' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /** The user's own views, and anything shared. */
    public function scopeAvailableTo(Builder $query, User $user): Builder
    {
        return $query->where(fn (Builder $q) => $q->where('user_id', $user->id)->orWhere('is_shared', true));
    }

    /** Where this view opens. */
    public function url(): string
    {
        return route($this->page === 'board' ? 'board.index' : 'tasks.index', $this->filters);
    }
}
