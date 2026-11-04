<?php

use App\Models\Comment;
use App\Models\Event;
use App\Models\MeetingNote;
use App\Models\Release;
use App\Models\ReleaseOffDay;
use App\Models\ReleasePhase;
use App\Models\Task;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Older activity rows have no release_id, so release History skips them and the
 * log prune doesn't know they belong to a release. Each row takes the release
 * its subject's activityReleaseId() would give today. Only empty values are
 * filled, and a row whose subject no longer exists stays as it is.
 */
return new class extends Migration
{
    /** Subjects with their own release_id column. */
    private const TABLES = [
        ReleasePhase::class => 'release_phases',
        ReleaseOffDay::class => 'release_off_days',
        Task::class => 'tasks',
        MeetingNote::class => 'meeting_notes',
        Event::class => 'events',
    ];

    public function up(): void
    {
        DB::table('activities')
            ->whereNull('release_id')
            ->whereIn('subject_type', [Release::class, Comment::class, ...array_keys(self::TABLES)])
            ->select(['id', 'subject_type', 'subject_id'])
            ->chunkById(500, function ($activities) {
                foreach ($activities->groupBy('subject_type') as $type => $rows) {
                    $releases = $this->releasesFor($type, $rows->pluck('subject_id')->unique()->all());

                    $rows->groupBy(fn ($row) => $releases[$row->subject_id] ?? 0)
                        ->except(0)
                        ->each(fn ($linked, $releaseId) => DB::table('activities')
                            ->whereIn('id', $linked->pluck('id'))
                            ->update(['release_id' => $releaseId]));
                }
            });
    }

    /** Nothing records which rows were filled in, and the links do no harm. */
    public function down(): void {}

    /**
     * @param  list<int>  $ids
     * @return array<int, int|null> release id per subject id
     */
    private function releasesFor(string $type, array $ids): array
    {
        if ($type === Release::class) {
            return array_combine($ids, $ids);
        }

        if ($type === Comment::class) {
            return $this->commentReleases($ids);
        }

        return DB::table(self::TABLES[$type])->whereIn('id', $ids)->pluck('release_id', 'id')->all();
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, int|null>
     */
    private function commentReleases(array $ids): array
    {
        $comments = DB::table('comments')->whereIn('id', $ids)->get(['id', 'commentable_type', 'commentable_id']);
        $tasks = DB::table('tasks')
            ->whereIn('id', $comments->where('commentable_type', Task::class)->pluck('commentable_id'))
            ->pluck('release_id', 'id');

        return $comments->mapWithKeys(fn ($comment) => [$comment->id => match ($comment->commentable_type) {
            Release::class => $comment->commentable_id,
            Task::class => $tasks[$comment->commentable_id] ?? null,
            default => null,
        }])->all();
    }
};
