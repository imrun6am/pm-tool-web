<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\Release;
use App\Models\Task;
use App\Models\TasksheetEntry;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The dashboard "Get started" checklist. Every step is derived from what the
 * user has actually done — nothing is tracked — so people already using the
 * app find their steps ticked, and the card hides itself once all are done or
 * the user dismisses it.
 */
class OnboardingService
{
    public function __construct(private readonly PreferenceService $preferences) {}

    /**
     * The steps for this user's role, in order. `action` is a route name, or
     * `quick-capture` for the global capture dialog.
     *
     * @return Collection<int, array{key: string, label: string, description: string, done: bool, action: string}>
     */
    public function checklist(User $user): Collection
    {
        $steps = [
            [
                'key' => 'add_task',
                'label' => 'Add your first task',
                'description' => 'Press C anywhere to capture one in seconds.',
                'done' => Task::where('created_by', $user->id)->exists(),
                'action' => 'quick-capture',
            ],
            [
                'key' => 'finish_task',
                'label' => 'Finish a task',
                'description' => 'Drag a card to Done on the board.',
                'done' => Task::where('assignee_id', $user->id)->where('status', 'done')->exists(),
                'action' => 'board.index',
            ],
            [
                'key' => 'comment',
                'label' => 'Join the conversation',
                'description' => 'Leave a comment on a task to ask or update.',
                'done' => Comment::where('user_id', $user->id)->exists(),
                'action' => 'board.index',
            ],
        ];

        if ($user->hasLimitedAccess()) {
            $steps[] = [
                'key' => 'tasksheet',
                'label' => 'Log today’s tasksheet',
                'description' => 'Share your plan for the day with your team.',
                'done' => TasksheetEntry::where('user_id', $user->id)->exists(),
                'action' => 'tasksheet.index',
            ];
        } elseif ($user->isLead()) {
            $steps[] = [
                'key' => 'release',
                'label' => 'Plan a release',
                'description' => 'Put your next release on the timeline.',
                'done' => Release::exists(),
                'action' => $user->canManageReleases() ? 'releases.create' : 'releases.index',
            ];
        }

        return collect($steps);
    }

    public function isVisible(User $user, ?Collection $checklist = null): bool
    {
        if ($this->preferences->get($user, 'onboarding_dismissed_at') !== null) {
            return false;
        }

        return ($checklist ?? $this->checklist($user))->contains(fn (array $step) => ! $step['done']);
    }

    public function dismiss(User $user): User
    {
        return $this->preferences->set($user, ['onboarding_dismissed_at' => now()->toIso8601String()]);
    }
}
