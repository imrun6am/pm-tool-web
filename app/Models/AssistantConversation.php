<?php

namespace App\Models;

use App\Support\LogRetention;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A person's chat with the assistant. Nobody else reads it, and it holds what
 * that person could see at the time, so it goes with the logs: `model:prune`
 * removes conversations untouched past the retention period, and "New chat"
 * deletes it straight away.
 */
class AssistantConversation extends Model
{
    use MassPrunable;

    protected $fillable = ['user_id', 'messages', 'refs'];

    protected $attributes = ['messages' => '[]', 'refs' => '{}'];

    protected function casts(): array
    {
        return ['messages' => 'array', 'refs' => 'array'];
    }

    public function prunable(): Builder
    {
        return static::where('updated_at', '<', LogRetention::cutoff());
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actions(): HasMany
    {
        return $this->hasMany(AssistantAction::class, 'conversation_id');
    }
}
