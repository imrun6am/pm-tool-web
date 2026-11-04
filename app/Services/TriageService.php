<?php

namespace App\Services;

use App\Models\TriageItem;
use App\Models\User;
use App\Notifications\TriageNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Incoming bugs and requests. Accepting one creates a normal task, filed by the
 * person who reported it; declining records why. The reporter hears either way.
 */
class TriageService
{
    public function __construct(private readonly TaskService $tasks) {}

    /**
     * What's waiting for this lead, oldest first.
     *
     * @return Collection<int, TriageItem>
     */
    public function queue(User $user): Collection
    {
        return TriageItem::decidableBy($user)
            ->where('status', TriageItem::PENDING)
            ->with(['reporter', 'team', 'label'])
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
            ->with(['team', 'label', 'task', 'decider'])
            ->latest()
            ->latest('id')
            ->limit(50)
            ->get();
    }

    /** @param  array<string, mixed>  $data */
    public function submit(User $reporter, array $data): TriageItem
    {
        $item = TriageItem::create($data + ['reporter_id' => $reporter->id]);

        $this->notifyDeciders($item, $reporter);

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

        $this->notifyReporter($item, 'request_accepted', $lead);

        return $item;
    }

    public function decline(TriageItem $item, User $lead, string $reason): TriageItem
    {
        $this->decide($item, $lead, TriageItem::DECLINED, ['decline_reason' => $reason]);

        $this->notifyReporter($item, 'request_declined', $lead);

        return $item;
    }

    /** @param  array<string, mixed>  $extra */
    private function decide(TriageItem $item, User $lead, string $status, array $extra): void
    {
        $item->forceFill($extra + ['status' => $status, 'decided_by' => $lead->id, 'decided_at' => now()])->save();
    }

    /** The team's lead, or the org-level leads when it has none. Never the reporter. */
    private function notifyDeciders(TriageItem $item, User $reporter): void
    {
        $leadId = $item->team?->team_lead_id;

        $recipients = $leadId
            ? User::whereKey($leadId)->get()
            : User::active()->whereIn('role', [User::ROLE_ADMIN, User::ROLE_CTO, User::ROLE_TECH_LEAD])->get();

        $recipients
            ->reject(fn (User $user) => $user->id === $reporter->id || ! $user->isActive())
            ->each(fn (User $user) => $user->notify(new TriageNotification($item, 'request_new', $reporter)));
    }

    private function notifyReporter(TriageItem $item, string $kind, User $lead): void
    {
        $reporter = $item->reporter;

        if ($reporter && $reporter->id !== $lead->id && $reporter->isActive() && ! $reporter->trashed()) {
            $reporter->notify(new TriageNotification($item, $kind, $lead));
        }
    }
}
