<?php

namespace App\Services;

use App\Models\MeetingNote;
use App\Models\Release;
use App\Models\User;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Minutes of a meeting — release-linked or general.
 *
 * Unlike personal notes these are shared team records, so a lead may delete any
 * of them while editing stays with the author (see MeetingNotePolicy).
 * Attendees-only notes are filtered in the query by the model's `visibleTo`
 * scope, which leads bypass entirely.
 */
class MeetingNoteService
{
    /**
     * The LIKE escape character for search terms. Deliberately not a backslash:
     * MySQL treats `\` as the default LIKE escape while SQLite has none at all,
     * so a backslash-escaped term would quietly mean different things in
     * production and in the test suite. `!` is literal in both, and the
     * ESCAPE clause below makes the behaviour explicit rather than inherited.
     */
    private const LIKE_ESCAPE = '!';

    /** The attributes a meeting-note write accepts. */
    private const WRITABLE = ['title', 'type', 'meeting_date', 'release_id', 'event_id', 'body', 'visibility'];

    /**
     * Notes the viewer may see, narrowed by any combination of filters.
     *
     * `release` accepts null (all), the string 'general' (notes linked to no
     * release), or a release id. `author` and `attendee` are user ids and are
     * deliberately separate: "notes X wrote" is a different question from
     * "meetings X sat in". `search` matches the title or the body text.
     *
     * Every filter is applied *after* the `visibleTo` scope, so narrowing by a
     * colleague can never surface an attendees-only note the viewer may not
     * read — the person filters are not a disclosure channel.
     *
     * @param  array{release?: mixed, type?: ?string, author?: mixed, attendee?: mixed, search?: ?string, from?: ?string, to?: ?string}  $filters
     * @return Builder<MeetingNote>
     */
    public function visibleTo(User $viewer, array $filters = []): Builder
    {
        $release = $filters['release'] ?? null;

        return MeetingNote::query()
            ->with(['author', 'release'])
            ->withCount('attendees')
            ->visibleTo($viewer)
            ->when($release === 'general', fn ($q) => $q->general())
            ->when($release && $release !== 'general', fn ($q) => $q->forRelease((int) $release))
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->ofType($type))
            ->when($filters['author'] ?? null, fn ($q, $id) => $q->where('created_by', (int) $id))
            ->when($filters['attendee'] ?? null, fn ($q, $id) => $q->whereHas(
                'attendees', fn (Builder $a) => $a->whereKey((int) $id)
            ))
            ->when($this->searchTerm($filters['search'] ?? null), fn ($q, $term) => $q->where(
                fn (Builder $s) => $s
                    ->whereRaw('title LIKE ? ESCAPE \''.self::LIKE_ESCAPE.'\'', [$term])
                    ->orWhereRaw('body LIKE ? ESCAPE \''.self::LIKE_ESCAPE.'\'', [$term])
            ))
            ->when($filters['from'] ?? null, fn ($q, $date) => $q->whereDate('meeting_date', '>=', $date))
            ->when($filters['to'] ?? null, fn ($q, $date) => $q->whereDate('meeting_date', '<=', $date))
            ->orderByDesc('meeting_date')
            ->orderByDesc('id');
    }

    /**
     * Build the LIKE pattern for a search term, escaping the wildcards so a
     * term containing `%` or `_` matches those characters literally instead of
     * quietly widening the search. Returns null for a blank term.
     */
    private function searchTerm(?string $search): ?string
    {
        $search = trim((string) $search);

        if ($search === '') {
            return null;
        }

        $e = self::LIKE_ESCAPE;

        return '%'.str_replace([$e, '%', '_'], [$e.$e, $e.'%', $e.'_'], $search).'%';
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, int|string>  $attendeeIds
     */
    public function create(array $attributes, array $attendeeIds, User $author): MeetingNote
    {
        $note = MeetingNote::create($this->writable($attributes) + ['created_by' => $author->id]);
        $note->attendees()->sync($attendeeIds);

        return $note;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, int|string>  $attendeeIds
     */
    public function update(MeetingNote $note, array $attributes, array $attendeeIds): MeetingNote
    {
        $note->update($this->writable($attributes));
        $note->attendees()->sync($attendeeIds);

        return $note;
    }

    /**
     * Releases that may be linked: the ongoing ones, plus the note's own even if
     * it has since completed — so editing never silently drops a live link.
     *
     * @return Collection<int, Release>
     */
    public function linkableReleases(?MeetingNote $note = null): Collection
    {
        return Release::query()
            ->where(fn ($q) => $q->whereNull('completed_at')->orWhere('id', $note?->release_id))
            ->orderBy('year', 'desc')
            ->orderBy('name')
            ->get();
    }

    /**
     * Normalize the meeting-date range, swapping a reversed span rather than
     * rejecting it.
     *
     * @param  array{from?: ?string, to?: ?string}  $input
     * @return array{from: ?string, to: ?string}
     */
    public function normalizeRange(array $input): array
    {
        $from = filled($input['from'] ?? null) ? Carbon::parse($input['from'])->toDateString() : null;
        $to = filled($input['to'] ?? null) ? Carbon::parse($input['to'])->toDateString() : null;

        if ($from && $to && $from > $to) {
            [$from, $to] = [$to, $from];
        }

        return ['from' => $from, 'to' => $to];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function writable(array $attributes): array
    {
        return array_intersect_key($attributes, array_flip(self::WRITABLE));
    }
}
