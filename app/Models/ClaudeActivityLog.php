<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * One Claude Code event reported by a monitored device. Once written a row
 * can't be changed; whoever may read the log may delete rows from it.
 */
class ClaudeActivityLog extends Model
{
    public const UPDATED_AT = null;

    /** The prompt_excerpt column's size; a longer setting is cut to this. */
    public const PROMPT_EXCERPT_MAX = 1000;

    public const EVENTS = [
        'SessionStart' => 'Session started',
        'UserPromptSubmit' => 'Prompt sent',
        'SessionEnd' => 'Session ended',
        'PostModelSwitch' => 'Model switched',
        'PreToolUse' => 'Browser action',
        'MonitorInstalled' => 'Monitor installed',
    ];

    protected $fillable = [
        'event', 'session_id', 'device_id', 'hostname', 'mac_address', 'os_user', 'platform',
        'entrypoint', 'model', 'ip', 'cwd', 'url', 'detail', 'prompt_excerpt', 'transcript_path',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Claude activity logs can\'t be changed.'));
    }

    public function eventLabel(): string
    {
        return self::EVENTS[$this->event] ?? $this->event;
    }
}
