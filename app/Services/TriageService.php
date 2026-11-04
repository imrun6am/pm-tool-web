<?php

namespace App\Services;

use App\Models\Team;
use App\Models\TriageItem;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Incoming bugs and requests. Accepting one creates a normal task, filed by the
 * person who reported it; declining records why. The reporter hears either way.
 */
class TriageService
{
    public function __construct(
        private readonly TaskService $tasks,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * What's waiting for this lead, oldest first.
     *
     * @return Collection<int, TriageItem>
     */
    public function queue(User $user): Collection
    {
        return TriageItem::decidableBy($user)
            ->where('status', TriageItem::PENDING)
            ->with(['reporter', 'team', 'label', 'sourceImport:id,input,original_name'])
            ->oldest()
            ->oldest('id')
            ->get();
    }

    /**
     * What this person has reported, newest first.
     *
     * @return Collection<int, TriageItem>
     */
    public function mine(User $user): Collection
    {
        return TriageItem::where('reporter_id', $user->id)
            ->with(['team', 'label', 'task', 'decider', 'sourceImport:id,input,original_name'])
            ->latest()
            ->latest('id')
            ->limit(50)
            ->get();
    }

    /**
     * The teams this person may send a request to: their own for Developers and QA, otherwise all.
     *
     * @return Collection<int, Team>
     */
    public function teamsFor(User $user): Collection
    {
        return Team::when($user->hasLimitedAccess(), fn ($q) => $q->whereIn('id', $user->currentTeamIds()))->orderBy('name')->get();
    }

    /** @param  array<string, mixed>  $data */
    public function submit(User $reporter, array $data): TriageItem
    {
        $item = TriageItem::create($data + ['reporter_id' => $reporter->id]);

        $this->notifications->requestSubmitted($item, $reporter);

        return $item;
    }

    /** @param  array<string, mixed>  $data  task fields: team, release, assignee, priority, due date */
    public function accept(TriageItem $item, User $lead, array $data): TriageItem
    {
        DB::transaction(function () use ($item, $lead, $data) {
            $task = $this->tasks->create([
                'title' => $item->title,
                'description' => $item->description,
                'team_id' => $data['team_id'] ?? $item->team_id,
                'priority' => $data['priority'] ?? $item->priority,
                'label_ids' => $item->label_id ? [$item->label_id] : [],
            ] + array_intersect_key($data, array_flip(['release_id', 'assignee_id', 'due_date'])), $item->reporter ?? $lead);

            $this->decide($item, $lead, TriageItem::ACCEPTED, ['task_id' => $task->id]);
        });

        $this->notifications->requestDecided($item, 'request_accepted', $lead);

        return $item;
    }

    public function decline(TriageItem $item, User $lead, string $reason): TriageItem
    {
        $this->decide($item, $lead, TriageItem::DECLINED, ['decline_reason' => $reason]);

        $this->notifications->requestDecided($item, 'request_declined', $lead);

        return $item;
    }

    /** @param  array<string, mixed>  $extra */
    private function decide(TriageItem $item, User $lead, string $status, array $extra): void
    {
        $item->forceFill($extra + ['status' => $status, 'decided_by' => $lead->id, 'decided_at' => now()])->save();
    }
}
