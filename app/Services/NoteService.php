<?php

namespace App\Services;

use App\Models\Note;
use App\Models\User;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Personal daily notes.
 *
 * Scoping happens in the query, not after it: a note the viewer may not read is
 * never loaded, so there is nothing to accidentally serialize. Recipients are
 * meaningful only on a "specific people" note — switching a note to private or
 * shared clears the list rather than leaving a stale share behind.
 */
class NoteService
{
    /** The attributes a note write accepts. */
    private const WRITABLE = ['date', 'body', 'visibility'];

    /**
     * Notes the viewer may see: everyone's shared notes, their own, and
     * "specific" notes they are a recipient of.
     *
     * The `author` filter is applied *after* the visibility scope, so it can
     * only ever narrow that set — filtering by a colleague returns their shared
     * notes and the ones they addressed to the viewer, never their private ones.
     *
     * @param  array{date?: ?string, from?: ?string, to?: ?string, author?: ?int}  $filters
     * @return Builder<Note>
     */
    public function visibleTo(User $viewer, array $filters = []): Builder
    {
        $date = $filters['date'] ?? null;

        return Note::query()
            ->with(['author', 'recipients'])
            ->visibleTo($viewer)
            ->when($filters['author'] ?? null, fn ($q, $id) => $q->where('user_id', (int) $id))
            ->when($date, fn ($q) => $q->whereDate('date', $date))
            // A single-date filter wins outright; a range only applies without one.
            ->when(! $date && ($filters['from'] ?? null), fn ($q) => $q->whereDate('date', '>=', $filters['from']))
            ->when(! $date && ($filters['to'] ?? null), fn ($q) => $q->whereDate('date', '<=', $filters['to']))
            ->orderByDesc('date')
            ->orderByDesc('id');
    }

    /**
     * The people whose notes this viewer can actually see, for the author
     * filter's options.
     *
     * Deliberately not "all active users": every option here returns something,
     * and the control never hints that someone has notes the viewer may not
     * read. Built from *all* visible notes rather than the currently filtered
     * ones, so changing the date range can never strand the selected author
     * outside the list. `withTrashed()` because a departed colleague's shared
     * notes stay visible, and an option without a name would be useless.
     *
     * @return Collection<int, User>
     */
    public function authorsVisibleTo(User $viewer): Collection
    {
        return User::withTrashed()
            ->whereIn('id', Note::query()->visibleTo($viewer)->select('user_id'))
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, int|string>  $recipientIds
     */
    public function create(array $attributes, array $recipientIds, User $author): Note
    {
        $note = $author->notes()->create($this->writable($attributes));
        $this->syncRecipients($note, $recipientIds);

        return $note;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, int|string>  $recipientIds
     */
    public function update(Note $note, array $attributes, array $recipientIds): Note
    {
        $note->update($this->writable($attributes));
        $this->syncRecipients($note, $recipientIds);

        return $note;
    }

    /**
     * Normalize an optional date filter to a plain Y-m-d, swapping a reversed
     * range rather than rejecting it.
     *
     * @param  array{date?: ?string, from?: ?string, to?: ?string}  $input
     * @return array{date: ?string, from: ?string, to: ?string}
     */
    public function normalizeFilters(array $input): array
    {
        $date = filled($input['date'] ?? null) ? Carbon::parse($input['date'])->toDateString() : null;
        $from = filled($input['from'] ?? null) ? Carbon::parse($input['from'])->toDateString() : null;
        $to = filled($input['to'] ?? null) ? Carbon::parse($input['to'])->toDateString() : null;

        if ($from && $to && $from > $to) {
            [$from, $to] = [$to, $from];
        }

        return ['date' => $date, 'from' => $from, 'to' => $to];
    }

    /**
     * Recipients apply only to a specific-visibility note; any other visibility
     * clears them.
     *
     * @param  array<int, int|string>  $recipientIds
     */
    private function syncRecipients(Note $note, array $recipientIds): void
    {
        $note->recipients()->sync(
            $note->visibility === Note::VISIBILITY_SPECIFIC ? $recipientIds : []
        );

        $note->load('recipients');
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
