<?php

namespace App\Services\Ai\Assistant\Tools;

use App\Models\Release;
use App\Models\ReleaseStatusUpdate;
use App\Models\User;
use App\Services\Ai\Assistant\ReadTool;
use App\Services\Ai\Assistant\ToolOutput;
use App\Services\Ai\Insights\ReleaseRiskGenerator;

/** The computed facts behind the release status draft and risk brief. */
class ReleaseFacts extends ReadTool
{
    public function __construct(private readonly ReleaseRiskGenerator $risk) {}

    public function name(): string
    {
        return 'release_facts';
    }

    public function description(): string
    {
        return 'Computed facts about a release: progress, phases, overdue and stalled work, the finish forecast, team clashes and the last status update. Use it before drafting a status with post_release_status. A forecast is a likelihood, never a promise.';
    }

    public function status(): string
    {
        return 'Working out where the release stands';
    }

    public function availableTo(User $user): bool
    {
        return $user->canManageReleases();
    }

    public function properties(): array
    {
        return ['release_id' => ['type' => 'integer']];
    }

    public function required(): array
    {
        return ['release_id'];
    }

    public function run(array $input, User $user): ToolOutput
    {
        $release = Release::visibleTo($user)->find((int) ($input['release_id'] ?? 0));
        if (! $release || ! $user->canManageReleases()) {
            $this->fail('No release with that id that this person can manage.');
        }

        $last = ReleaseStatusUpdate::where('release_id', $release->id)->latest()->latest('id')->first();
        $facts = $this->risk->signals($release) + ['last_update' => $last ? [
            'status' => $last->statusLabel(), 'note' => $last->note, 'date' => $last->created_at->toDateString(),
        ] : null];

        return new ToolOutput(
            "[R{$release->id}] ".json_encode($facts, JSON_UNESCAPED_UNICODE),
            ["R{$release->id}" => self::releaseRef($release)],
        );
    }
}
