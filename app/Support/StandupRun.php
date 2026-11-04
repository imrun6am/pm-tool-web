<?php

namespace App\Support;

use App\Models\MeetingNote;

/** What happened when a team's standup was generated, and the note if there is one. */
final readonly class StandupRun
{
    public const CREATED = 'created';

    public const UPDATED = 'updated';

    public const UNCHANGED = 'unchanged';

    public const NOTHING_TO_REPORT = 'nothing_to_report';

    public const DAY_OFF = 'day_off';

    public const DELETED = 'deleted';

    public function __construct(public string $status, public ?MeetingNote $note = null) {}

    public function message(): string
    {
        return match ($this->status) {
            self::CREATED => 'Standup created from the tasksheet.',
            self::UPDATED => 'Standup updated from the tasksheet.',
            self::UNCHANGED => 'The standup is already up to date.',
            self::DAY_OFF => 'That day isn’t a working day, so it has no standup.',
            self::DELETED => 'This standup was deleted.',
            default => 'There’s nothing to report for that day yet.',
        };
    }
}
