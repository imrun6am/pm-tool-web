<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * One Claude Code event reported by a monitored device. Append-only: once
 * written a row can't be changed or removed through the app.
 */
class ClaudeActivityLog extends Model
{
    public const UPDATED_AT = null;

    public const EVENTS = [
        'SessionStart' => 'Session started',
        'UserPromptSubmit' => 'Prompt sent',
        'SessionEnd' => 'Session ended',
        'MonitorInstalled' => 'Monitor installed',
    ];

    protected $fillable = [
        'event', 'session_id', 'device_id', 'hostname', 'os_user', 'platform',
        'entrypoint', 'ip', 'cwd', 'detail', 'prompt_excerpt', 'transcript_path',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Claude activity logs are append-only.'));
        static::deleting(fn () => throw new LogicException('Claude activity logs are append-only.'));
    }

    public function eventLabel(): string
    {
        return self::EVENTS[$this->event] ?? $this->event;
    }
}
