<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Comment extends Model
{
    use RecordsActivity;

    protected $fillable = ['user_id', 'body'];

    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function activityTitle(): string
    {
        $subject = match ($this->commentable_type) {
            Release::class => 'a release',
            Task::class => 'a task',
            default => 'an item',
        };

        return "on {$subject}";
    }

    public function activityReleaseId(): ?int
    {
        if ($this->commentable_type === Release::class) {
            return (int) $this->commentable_id;
        }

        if ($this->commentable_type === Task::class) {
            return Task::whereKey($this->commentable_id)->value('release_id');
        }

        return null;
    }

    protected function activityExtraIgnored(): array
    {
        return ['commentable_type', 'commentable_id', 'user_id'];
    }
}
