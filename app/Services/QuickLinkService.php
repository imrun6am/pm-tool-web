<?php

namespace App\Services;

use App\Models\QuickLink;
use App\Models\Release;
use App\Models\User;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Saved bookmarks for the quick-links drawer.
 *
 * Like personal notes, a quick link belongs to its author — QuickLinkPolicy lets
 * nobody else edit or delete one, leads included. Limited roles
 * (developer/QA) are private-only: they neither see others' shared links nor
 * create shared ones, which the model's `visibleTo` scope enforces on read and
 * QuickLinkRequest enforces on write.
 */
class QuickLinkService
{
    /** The attributes a quick-link write accepts. */
    private const WRITABLE = ['release_id', 'label', 'url', 'visibility'];

    /**
     * The links that belong in the viewer's drawer, ordered pinned → private →
     * shared, newest first within each band.
     *
     * This is the *drawer's* listing rule, deliberately narrower than the
     * model's `visibleTo` scope: a link attached to a finished release stops
     * being drawer material, but the release-details card must keep listing it
     * (design decision 3). The model scope answers "may this user see this
     * link at all"; this method answers "does it belong in the drawer now".
     *
     * @return Builder<QuickLink>
     */
    public function visibleTo(User $viewer, ?int $releaseId = null): Builder
    {
        return QuickLink::query()
            ->with(['author', 'release'])
            // The viewer's own pin state as one subquery column — what the
            // ordering sorts on, and what spares the drawer a query per row.
            ->withExists(['pinnedBy as is_pinned' => fn ($q) => $q->whereKey($viewer->id)])
            ->visibleTo($viewer)
            // Links attached to no release always pass; only a *completed*
            // release's links drop out. Reversible: reopening restores them.
            ->whereDoesntHave('release', fn ($q) => $q->whereNotNull('completed_at'))
            ->when($releaseId, fn ($q, $id) => $q->where('release_id', $id))
            ->orderByDesc('is_pinned')
            // Explicit rather than relying on 'private' sorting before 'shared'
            // alphabetically — that would silently break if a value is renamed.
            ->orderByRaw('(visibility = ?) desc', [QuickLink::VISIBILITY_PRIVATE])
            ->orderByDesc('id');
    }

    /**
     * The drawer's split: the viewer's own links, and everyone else's shared
     * ones. Partitioned here so two consumers cannot disagree about which
     * bucket a link belongs in.
     *
     * @return array{mine: Collection<int, QuickLink>, shared: Collection<int, QuickLink>}
     */
    public function partitionedFor(User $viewer, ?int $releaseId = null): array
    {
        [$mine, $shared] = $this->visibleTo($viewer, $releaseId)
            ->get()
            ->partition(fn (QuickLink $link) => $link->user_id === $viewer->id);

        return ['mine' => $mine->values(), 'shared' => $shared->values()];
    }

    /**
     * Flip the viewer's pin on a link. Returns the resulting state so a caller
     * never has to re-read it to know which way the toggle went.
     */
    public function togglePin(QuickLink $link, User $viewer): bool
    {
        $changed = $link->pinnedBy()->toggle($viewer->id);

        return filled($changed['attached']);
    }

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes, User $author): QuickLink
    {
        return QuickLink::create($this->writable($attributes) + [
            'user_id' => $author->id,
            'visibility' => $attributes['visibility'] ?? QuickLink::VISIBILITY_PRIVATE,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    public function update(QuickLink $link, array $attributes): QuickLink
    {
        $link->update($this->writable($attributes) + [
            'visibility' => $attributes['visibility'] ?? $link->visibility,
        ]);

        return $link;
    }

    /**
     * Ongoing releases, for the drawer's "attach to a release" picker.
     *
     * @return Collection<int, Release>
     */
    public function attachableReleases(): Collection
    {
        return Release::ongoing()->orderBy('year', 'desc')->orderBy('name')->get();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function writable(array $attributes): array
    {
        return array_filter(
            array_intersect_key($attributes, array_flip(self::WRITABLE)),
            fn ($value, $key) => $key !== 'visibility' || $value !== null,
            ARRAY_FILTER_USE_BOTH
        );
    }
}
