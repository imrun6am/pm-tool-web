<?php

namespace App\Services;

use App\Models\Release;
use App\Models\ReleaseStatusUpdate;
use App\Models\User;
use Illuminate\Support\Collection;

/** Leads' written updates on a release, newest first. */
class ReleaseStatusService
{
    private const HISTORY_LIMIT = 20;

    public function __construct(private readonly NotificationService $notifications) {}

    /** @return Collection<int, ReleaseStatusUpdate> */
    public function history(Release $release): Collection
    {
        return ReleaseStatusUpdate::where('release_id', $release->id)
            ->with('author')
            ->latest()
            ->latest('id')
            ->limit(self::HISTORY_LIMIT)
            ->get();
    }

    public function post(Release $release, User $author, string $status, string $note): ReleaseStatusUpdate
    {
        $previous = ReleaseStatusUpdate::where('release_id', $release->id)->latest()->latest('id')->first();

        $update = ReleaseStatusUpdate::create([
            'release_id' => $release->id,
            'author_id' => $author->id,
            'status' => $status,
            'note' => $note,
        ]);

        // People need to hear when things get worse, not about every routine update.
        if ($status !== 'on_track' && $status !== $previous?->status) {
            $this->notifications->releaseStatusChanged($release, $update, $author);
        }

        return $update;
    }
}
