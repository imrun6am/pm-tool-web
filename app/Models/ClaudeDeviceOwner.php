<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Links a monitored Claude Code device to the PM Tool person who uses it. */
class ClaudeDeviceOwner extends Model
{
    protected $fillable = ['device_id', 'user_id', 'assigned_by'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
