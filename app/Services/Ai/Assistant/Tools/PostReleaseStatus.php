<?php

namespace App\Services\Ai\Assistant\Tools;

use App\Http\Requests\ReleaseStatusRequest;
use App\Models\Release;
use App\Models\ReleaseStatusUpdate;
use App\Models\User;
use App\Services\Ai\Assistant\ActionDraft;
use App\Services\Ai\Assistant\ActionResult;
use App\Services\Ai\Assistant\ActionTool;
use App\Services\Ai\Assistant\FormRules;
use App\Services\ReleaseStatusService;

class PostReleaseStatus extends ActionTool
{
    public function __construct(private readonly ReleaseStatusService $statuses) {}

    public function name(): string
    {
        return 'post_release_status';
    }

    public function description(): string
    {
        return 'Draft a status update on a release, posted under the person\'s name once they confirm. Call release_facts first. on_track: the work left fits the time left. at_risk: it might slip without action. off_track: the end date won\'t be met as things stand. The note is two to four plain sentences on where it stands, what changed and what\'s needed next, using only the facts.';
    }

    public function availableTo(User $user): bool
    {
        return $user->canManageReleases();
    }

    public function properties(): array
    {
        return [
            'release_id' => ['type' => 'integer'],
            'status' => ['type' => 'string', 'enum' => array_keys(ReleaseStatusUpdate::STATUSES)],
            'note' => ['type' => 'string'],
        ];
    }

    public function required(): array
    {
        return ['release_id', 'status', 'note'];
    }

    public function draft(array $input, User $user): ActionDraft
    {
        $release = $this->release($input['release_id'] ?? null, $user);
        $checked = FormRules::check(ReleaseStatusRequest::class, ['status' => $input['status'] ?? null, 'note' => $input['note'] ?? null], $user, ['release' => $release])->validated();

        $last = ReleaseStatusUpdate::where('release_id', $release->id)->latest()->latest('id')->first();

        return new ActionDraft(['release_id' => $release->id] + $checked, [
            'kind' => 'change',
            'action' => 'Post update',
            'title' => $release->name,
            'eyebrow' => 'Release status',
            'rows' => [[
                'label' => 'Status',
                'from' => $last ? ReleaseStatusUpdate::STATUSES[$last->status] ?? $last->status : null,
                'to' => ReleaseStatusUpdate::STATUSES[$checked['status']],
            ]],
            'body' => $checked['note'],
        ]);
    }

    public function run(array $input, User $user): ActionResult
    {
        $release = $this->release($input['release_id'], $user);
        $this->statuses->post($release, $user, $input['status'], $input['note']);

        return new ActionResult('Update posted', route('releases.show', $release), "Posted a status update on release [R{$release->id}].", [
            'ref' => "R{$release->id}", 'kind' => 'Release', 'title' => $release->name, 'url' => route('releases.show', $release),
        ]);
    }

    private function release(mixed $id, User $user): Release
    {
        $release = is_numeric($id) ? Release::visibleTo($user)->find((int) $id) : null;

        return $release ?? $this->fail('release_id', 'No release with that id that this person can open.');
    }
}
