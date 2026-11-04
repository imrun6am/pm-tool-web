<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * What DailyStandupService generated for one team on one day. The meeting note
 * it belongs to holds what people own (title, attendees, body), so a refresh
 * only ever writes here and can't overwrite anything written by hand.
 *
 * The row outlives its note: no note means someone deleted it, and the schedule
 * leaves it alone. Content comes from a fixed list of tasksheet fields and never
 * includes lead feedback, points or standup attendance.
 */
class DailyStandup extends Model
{
    protected $fillable = [
        'team_id', 'date', 'meeting_note_id', 'content', 'fingerprint', 'search_text', 'generated_at', 'attendees_edited_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'team_id' => 'integer',
            'meeting_note_id' => 'integer',
            'content' => 'array',
            'generated_at' => 'datetime',
            'attendees_edited_at' => 'datetime',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function meetingNote(): BelongsTo
    {
        return $this->belongsTo(MeetingNote::class);
    }

    public function scopeForTeamOn(Builder $query, Team $team, Carbon $date): Builder
    {
        return $query->where('team_id', $team->id)->whereDate('date', $date->toDateString());
    }

    /** The day "did" is read from, which isn't always yesterday. */
    public function previousDate(): ?Carbon
    {
        $date = $this->content['previous_date'] ?? null;

        return $date ? Carbon::parse($date) : null;
    }
}
