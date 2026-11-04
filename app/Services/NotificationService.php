<?php

namespace App\Services;

use App\Models\AiInsight;
use App\Models\Comment;
use App\Models\Event;
use App\Models\EventImport;
use App\Models\KpiReport;
use App\Models\MeetingNote;
use App\Models\Note;
use App\Models\Release;
use App\Models\ReleaseDocument;
use App\Models\ReleaseStatusUpdate;
use App\Models\Task;
use App\Models\TasksheetExport;
use App\Models\Team;
use App\Models\TriageItem;
use App\Models\User;
use App\Notifications\InboxNotification;
use App\Notifications\ReleaseStatusChanged;
use App\Notifications\TaskAssigned;
use App\Notifications\TaskBlocked;
use App\Notifications\TaskCommented;
use App\Notifications\TaskDueSoon;
use App\Notifications\TaskUnblocked;
use App\Notifications\TriageNotification;
use App\Policies\AiInsightPolicy;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

/**
 * The in-app inbox: who is told about what, and reading it back.
 *
 * Recipient rules live here and nowhere else. Nobody is told about their own
 * action, deactivated accounts are skipped, and a recipient must be able to
 * see the record, so a developer or QA never hears about another team's work.
 * Triggers are called from the model observers, or from the service doing a
 * pivot sync an observer can't see, so the web and API behave the same.
 *
 * A null actor means no signed-in user (seeders, console), which stays silent.
 * The exceptions are on purpose and take no actor: due and event reminders,
 * recurring tasks, and the results of background jobs.
 *
 * Performance scores, lead feedback and standup attendance never notify anyone,
 * and no row holds their text, or note or event text: rows outlive access.
 */
class NotificationService
{
    public const KINDS = [
        'assigned', 'commented', 'due_tomorrow', 'overdue', 'blocked', 'unblocked', 'request_new', 'request_accepted', 'request_declined', 'release_status',
        'unassigned', 'block_cleared', 'sent_back', 'due_changed', 'urgent', 'task_deleted',
        'release_created', 'release_member_added', 'release_dates_changed', 'release_completed', 'release_reopened', 'release_commented',
        'meeting_attendee', 'event_invited', 'event_changed', 'event_cancelled', 'event_tomorrow', 'note_shared',
        'import_ready', 'import_failed', 'export_ready', 'export_failed', 'kpi_ready', 'kpi_failed', 'insight_ready', 'insight_failed',
        'team_added', 'team_removed', 'team_lead_assigned', 'team_lead_removed', 'role_changed',
    ];

    /** How many notifications the top-bar dropdown shows. */
    public const DROPDOWN_LIMIT = 8;

    /**
     * Wording and target for several of a kind from one bulk write. A kind not
     * listed is sent row by row. `:scheduled` is the wording with no actor.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const GROUPED = [
        'assigned' => [':actor assigned you :count tasks', 'tasks'],
        'assigned:scheduled' => [':count recurring tasks were created for you', 'tasks'],
        'unassigned' => [':actor took you off :count tasks', 'inbox'],
        'sent_back' => [':actor sent back :count of your tasks', 'tasks'],
        'urgent' => [':actor marked :count of your tasks urgent', 'tasks'],
        'unblocked' => [':count of your tasks are ready to start', 'tasks'],
        'task_deleted' => [':actor deleted :count of your tasks', 'inbox'],
        'event_invited' => [':actor added you to :count events', 'calendar'],
        'request_new' => [':actor sent :count requests', 'request'],
    ];

    private int $batchDepth = 0;

    /** @var list<array{0: User, 1: Notification}> */
    private array $buffer = [];

    /**
     * Run bulk work, then send one row per person and kind: the original when
     * there's one, a grouped row when there are several. Wrap it around the
     * transaction, so a failure sends nothing.
     *
     * @template T
     *
     * @param  Closure(): T  $work
     * @return T
     */
    public function batch(Closure $work): mixed
    {
        $this->batchDepth++;

        try {
            $result = $work();
        } catch (Throwable $e) {
            if (--$this->batchDepth === 0) {
                $this->buffer = [];
            }

            throw $e;
        }

        if (--$this->batchDepth === 0) {
            $this->flush();
        }

        return $result;
    }

    /** Tell a task's (new) assignee, unless they assigned it themselves. */
    public function taskAssigned(Task $task, ?User $actor): void
    {
        // Ids straight from a request are strings until the model is reloaded.
        if (! $actor || ! $task->assignee_id || (int) $task->assignee_id === $actor->id) {
            return;
        }

        $this->deliver(User::whereKey($task->assignee_id)->get(), $task, new TaskAssigned($task, $actor));
    }

    /**
     * Tell everyone involved in a task about a new comment: its assignee, its
     * creator, and anyone who commented before. Never the comment's author.
     */
    public function taskCommented(Task $task, Comment $comment): void
    {
        $ids = collect([$task->assignee_id, $task->created_by])
            ->merge($task->comments()->whereKeyNot($comment->id)->pluck('user_id'))
            ->filter()
            ->unique()
            ->reject(fn ($id) => (int) $id === (int) $comment->user_id);

        if ($ids->isEmpty()) {
            return;
        }

        $this->deliver(User::whereKey($ids->all())->get(), $task, new TaskCommented($task, $comment));
    }

    /**
     * Tell the task's team lead and its assignee that it's stuck, so someone
     * can step in. With no active team lead, org-level leads hear instead.
     */
    public function taskBlocked(Task $task, ?User $actor): void
    {
        $leadId = $task->team?->team_lead_id;
        $hasLead = $leadId && User::active()->whereKey($leadId)->exists();

        $ids = collect($hasLead ? [$leadId] : $this->orgLeadIds())
            ->push($task->assignee_id)
            ->filter()
            ->unique()
            ->reject(fn ($id) => (int) $id === $actor?->id);

        if ($ids->isNotEmpty()) {
            $this->deliver(User::whereKey($ids->all())->get(), $task, new TaskBlocked($task, $actor));
        }
    }

    /** Tell whoever had a task that it's gone from them. */
    public function taskUnassigned(Task $task, int $previousId, ?User $actor): void
    {
        if (! $actor || $previousId === $actor->id) {
            return;
        }

        $next = $task->assignee_id ? User::withTrashed()->find($task->assignee_id) : null;
        $message = match (true) {
            $next === null => "{$actor->name} took you off {$this->quote($task->title)}",
            $next->id === $actor->id => "{$actor->name} took over {$this->quote($task->title)}",
            default => "{$actor->name} reassigned {$this->quote($task->title)} to {$next->name}",
        };

        $this->deliver(User::whereKey($previousId)->get(), $task, $this->taskRow('unassigned', $message, $task, $actor));
    }

    /** The block is off: the assignee and team lead can carry on. */
    public function taskBlockCleared(Task $task, ?User $actor): void
    {
        if (! $actor) {
            return;
        }

        $ids = collect([$task->team?->team_lead_id, $task->assignee_id])
            ->filter()
            ->unique()
            ->reject(fn ($id) => (int) $id === $actor->id);

        if ($ids->isNotEmpty()) {
            $message = "{$actor->name} cleared the block on {$this->quote($task->title)}";
            $this->deliver(User::whereKey($ids->all())->get(), $task, $this->taskRow('block_cleared', $message, $task, $actor));
        }
    }

    /** Someone sent the assignee's work back: to Recheck, or out of Done. */
    public function taskSentBack(Task $task, ?User $actor): void
    {
        $message = $task->status === 'recheck'
            ? "{$actor?->name} sent {$this->quote($task->title)} back for a recheck"
            : "{$actor?->name} reopened {$this->quote($task->title)}";

        $this->toAssignee('sent_back', $message, $task, $actor);
    }

    public function taskDueChanged(Task $task, ?User $actor): void
    {
        $message = $task->due_date
            ? "{$actor?->name} moved the due date of {$this->quote($task->title)} to {$task->due_date->format('M j')}"
            : "{$actor?->name} removed the due date of {$this->quote($task->title)}";

        $this->toAssignee('due_changed', $message, $task, $actor);
    }

    public function taskMarkedUrgent(Task $task, ?User $actor): void
    {
        $this->toAssignee('urgent', "{$actor?->name} marked {$this->quote($task->title)} urgent", $task, $actor);
    }

    /**
     * A task went to the trash: tell its assignee and those of the subtasks that
     * went with it, one row each, whatever the number of subtasks they hold.
     */
    public function taskDeleted(Task $task, ?User $actor): void
    {
        if (! $actor) {
            return;
        }

        $ids = $task->subtasks()->onlyTrashed()->where('trashed_with_parent', true)->pluck('assignee_id')
            ->push($task->assignee_id)
            ->filter()
            ->unique()
            ->reject(fn ($id) => (int) $id === $actor->id);

        if ($ids->isNotEmpty()) {
            $message = "{$actor->name} deleted {$this->quote($task->title)}";
            $this->deliver(User::whereKey($ids->all())->get(), $task, $this->taskRow('task_deleted', $message, $task, $actor));
        }
    }

    /**
     * Tell assignees about tasks the schedule made for them. There's no actor,
     * so the observer stayed silent, and this is the only word they get.
     *
     * @param  iterable<int, Task>  $tasks
     */
    public function recurringTasksCreated(iterable $tasks): void
    {
        $this->batch(function () use ($tasks) {
            foreach ($tasks as $task) {
                if ($task->assignee_id) {
                    $message = "Recurring task {$this->quote($task->title)} was created for you";
                    $this->deliver(User::whereKey($task->assignee_id)->get(), $task, $this->taskRow('assigned', $message, $task));
                }
            }
        });
    }

    /** A new request: to the team's lead when their role can decide it, else org-level leads. */
    public function requestSubmitted(TriageItem $item, User $reporter): void
    {
        $lead = $item->team?->teamLead;
        $leadCanDecide = $lead && $lead->isLead() && $lead->isActive() && ! $lead->trashed();

        $this->sendTo(
            $leadCanDecide ? [$lead->id] : $this->orgLeadIds(),
            new TriageNotification($item, 'request_new', $reporter),
            $reporter,
        );
    }

    /** @param  'request_accepted'|'request_declined'  $kind */
    public function requestDecided(TriageItem $item, string $kind, User $lead): void
    {
        $this->sendTo([$item->reporter_id], new TriageNotification($item, $kind, $lead), $lead);
    }

    /** The release got worse: the team's lead and org-level leads. */
    public function releaseStatusChanged(Release $release, ReleaseStatusUpdate $update, User $author): void
    {
        $this->sendTo(
            $this->orgLeadIds()->push($release->team?->team_lead_id),
            new ReleaseStatusChanged($release, $update, $author),
            $author,
            fn (User $user) => $release->isVisibleTo($user),
        );
    }

    public function releaseCreated(Release $release, ?User $actor): void
    {
        if ($actor) {
            $message = "{$actor->name} created the release {$this->quote($release->name)} for your team";
            $this->sendTo([$release->team?->team_lead_id], $this->releaseRow('release_created', $message, $release, $actor), $actor, fn (User $user) => $release->isVisibleTo($user));
        }
    }

    /** @param  iterable<int, int|string>  $userIds  the people just added */
    public function releaseMembersAdded(Release $release, iterable $userIds, ?User $actor): void
    {
        if ($actor) {
            $message = "{$actor->name} added you to the release {$this->quote($release->name)}";
            $this->sendTo($userIds, $this->releaseRow('release_member_added', $message, $release, $actor), $actor, fn (User $user) => $release->isVisibleTo($user));
        }
    }

    /** @param  iterable<int, int|string>  $except  people already told, such as those just added */
    public function releaseDatesChanged(Release $release, iterable $except, ?User $actor): void
    {
        if ($actor) {
            $message = "{$actor->name} changed the dates of {$this->quote($release->name)}";
            $ids = $this->releasePeople($release)->diff(collect($except)->map(fn ($id) => (int) $id));
            $this->sendTo($ids, $this->releaseRow('release_dates_changed', $message, $release, $actor), $actor, fn (User $user) => $release->isVisibleTo($user));
        }
    }

    public function releaseCompleted(Release $release, ?User $actor): void
    {
        if ($actor) {
            $message = "{$actor->name} completed the release {$this->quote($release->name)}";
            $ids = $this->releasePeople($release)->merge($release->team?->members()->pluck('users.id') ?? []);
            $this->sendTo($ids, $this->releaseRow('release_completed', $message, $release, $actor), $actor, fn (User $user) => $release->isVisibleTo($user));
        }
    }

    public function releaseReopened(Release $release, ?User $actor): void
    {
        if ($actor) {
            $message = "{$actor->name} reopened the release {$this->quote($release->name)}";
            $this->sendTo($this->releasePeople($release), $this->releaseRow('release_reopened', $message, $release, $actor), $actor, fn (User $user) => $release->isVisibleTo($user));
        }
    }

    /** A comment on a release: its lead, its members and anyone who commented before. */
    public function releaseCommented(Release $release, Comment $comment): void
    {
        $author = $comment->user;
        $text = Str::of(strip_tags((string) $comment->body))->squish()->limit(140)->value();
        $row = new InboxNotification(
            'release_commented',
            "{$author?->name} commented on {$this->quote($release->name)}",
            'release',
            $release->id,
            $author,
            ['release_id' => $release->id, 'excerpt' => $text !== '' ? $text : null],
        );

        $ids = $this->releasePeople($release)->merge($release->comments()->whereKeyNot($comment->id)->pluck('user_id'));

        $this->sendTo($ids, $row, $author, fn (User $user) => $release->isVisibleTo($user));
    }

    /** @param  iterable<int, int|string>  $userIds  the people just added */
    public function meetingAttendeesAdded(MeetingNote $note, iterable $userIds, ?User $actor): void
    {
        if ($actor) {
            $message = "{$actor->name} added you to the meeting notes {$this->quote($note->title)}";
            $row = new InboxNotification('meeting_attendee', $message, 'meeting_note', $note->id, $actor);
            $this->sendTo($userIds, $row, $actor, fn (User $user) => $note->isVisibleTo($user));
        }
    }

    /** @param  iterable<int, int|string>  $userIds  the people just added */
    public function eventAttendeesAdded(Event $event, iterable $userIds, ?User $actor): void
    {
        if ($actor) {
            $message = "{$actor->name} invited you to {$this->quote($event->title)}";
            $row = new InboxNotification('event_invited', $message, 'event', $event->id, $actor);
            $this->sendTo($userIds, $row, $actor, fn (User $user) => $event->isVisibleTo($user));
        }
    }

    /** @param  iterable<int, int|string>  $userIds  attendees who were already invited */
    public function eventChanged(Event $event, bool $timeChanged, bool $placeChanged, iterable $userIds, ?User $actor): void
    {
        if (! $actor || (! $timeChanged && ! $placeChanged)) {
            return;
        }

        $what = match (true) {
            $timeChanged && $placeChanged => 'time and place',
            $timeChanged => 'time',
            default => 'place',
        };
        $row = new InboxNotification('event_changed', "{$actor->name} changed the {$what} of {$this->quote($event->title)}", 'event', $event->id, $actor);

        $this->sendTo($userIds, $row, $actor, fn (User $user) => $event->isVisibleTo($user));
    }

    /** Called before the event goes, while its attendees can still be read. */
    public function eventCancelled(Event $event, ?User $actor): void
    {
        if ($actor) {
            $row = new InboxNotification('event_cancelled', "{$actor->name} cancelled {$this->quote($event->title)}", 'event', $event->id, $actor);
            $ids = $event->attendees()->pluck('users.id')->push($event->created_by);
            $this->sendTo($ids, $row, $actor, fn (User $user) => $event->isVisibleTo($user));
        }
    }

    /**
     * Tell attendees and creators about events starting tomorrow. Safe to run
     * more than once; an event moved to a new day is reminded again.
     *
     * @return int the number of reminders sent
     */
    public function sendEventReminders(?CarbonInterface $today = null): int
    {
        $tomorrow = ($today ?? now())->copy()->addDay()->toDateString();
        $sent = 0;

        Event::query()
            ->with('attendees')
            ->whereDate('starts_at', $tomorrow)
            ->each(function (Event $event) use ($tomorrow, &$sent) {
                $time = $event->all_day ? '' : ' at '.$event->starts_at->format('g:i A');
                $row = new InboxNotification('event_tomorrow', "{$this->quote($event->title)} starts tomorrow{$time}", 'event', $event->id, null, ['for_date' => $tomorrow]);

                $sent += $this->sendTo(
                    $event->attendees->pluck('id')->push($event->created_by),
                    $row,
                    null,
                    fn (User $user) => ! $user->notifications()
                        ->where('data->kind', 'event_tomorrow')
                        ->where('data->target_id', $event->id)
                        ->where('data->for_date', $tomorrow)
                        ->exists(),
                );
            });

        return $sent;
    }

    /** @param  iterable<int, int|string>  $userIds  the people just added */
    public function noteShared(Note $note, iterable $userIds, ?User $actor): void
    {
        if ($actor && $note->isSpecific()) {
            $row = new InboxNotification('note_shared', "{$actor->name} shared a note with you", 'note', $note->id, $actor);
            $this->sendTo($userIds, $row, $actor, fn (User $user) => $note->isVisibleTo($user));
        }
    }

    public function importFinished(EventImport $import): void
    {
        $count = count($import->proposalList());
        $message = $count > 0
            ? 'Your calendar import is ready to review: '.$count.' '.Str::plural('event', $count).' found'
            : 'Your calendar import finished and found no events';

        $this->sendTo([$import->user_id], new InboxNotification('import_ready', $message, 'event_import', $import->id));
    }

    public function importFailed(EventImport $import): void
    {
        $this->sendTo([$import->user_id], new InboxNotification('import_failed', 'Your calendar import couldn’t be finished', 'event_import', $import->id));
    }

    public function exportFinished(TasksheetExport $export): void
    {
        $this->sendTo([$export->requested_by], new InboxNotification('export_ready', 'Your tasksheet PDF is ready to download', 'tasksheet_exports'));
    }

    public function exportFailed(TasksheetExport $export): void
    {
        $this->sendTo([$export->requested_by], new InboxNotification('export_failed', 'Your tasksheet PDF couldn’t be created', 'tasksheet_exports'));
    }

    /** To the requesting admin only, naming the period and nothing about anyone in it. */
    public function kpiFinished(KpiReport $report): void
    {
        $this->sendKpiResult($report, 'kpi_ready', "Your KPI report for {$this->period($report)} is ready");
    }

    public function kpiFailed(KpiReport $report): void
    {
        $this->sendKpiResult($report, 'kpi_failed', "Your KPI report for {$this->period($report)} couldn’t be built");
    }

    /** To whoever asked for it; a scheduled result has no requester and stays silent. */
    public function insightFinished(AiInsight $insight, bool $succeeded): void
    {
        $subject = $insight->subject;
        if (! $insight->requested_by || ! $subject) {
            return;
        }

        $label = match ($insight->kind) {
            AiInsight::KIND_RELEASE_RISK => 'risk brief',
            AiInsight::KIND_STANDUP_DIGEST => 'standup digest',
            default => 'document analysis',
        };
        $release = $subject instanceof ReleaseDocument ? $subject->release : $subject;
        $about = $this->quote((string) ($release?->name ?? ''));

        [$type, $id, $extra] = $subject instanceof Team
            ? ['tasksheet', $subject->id, ['for_date' => $insight->for_date?->toDateString()]]
            : ['release', $release?->id, ['release_id' => $release?->id]];

        $row = $succeeded
            ? new InboxNotification('insight_ready', "The AI {$label} for {$about} is ready", $type, $id, null, $extra)
            : new InboxNotification('insight_failed', "The AI couldn’t finish the {$label} for {$about}", $type, $id, null, $extra);

        $this->sendTo([$insight->requested_by], $row, null, fn (User $user) => AiInsightPolicy::canView($user, $insight->kind, $subject));
    }

    public function teamMemberAdded(Team $team, int|string $userId, ?User $actor): void
    {
        if ($actor) {
            $row = new InboxNotification('team_added', "{$actor->name} added you to the team {$this->quote($team->name)}", 'team', $team->id, $actor);
            $this->sendTo([$userId], $row, $actor);
        }
    }

    public function teamMemberRemoved(Team $team, User $user, ?User $actor): void
    {
        if ($actor) {
            $row = new InboxNotification('team_removed', "{$actor->name} removed you from the team {$this->quote($team->name)}", 'dashboard', null, $actor);
            $this->sendTo([$user->id], $row, $actor);
        }
    }

    public function teamLeadChanged(Team $team, int|string|null $newLeadId, int|string|null $oldLeadId, ?User $actor): void
    {
        if (! $actor) {
            return;
        }

        $assigned = new InboxNotification('team_lead_assigned', "{$actor->name} made you the lead of {$this->quote($team->name)}", 'team', $team->id, $actor);
        $removed = new InboxNotification('team_lead_removed', "{$actor->name} removed you as lead of {$this->quote($team->name)}", 'team', $team->id, $actor);

        $this->sendTo([$newLeadId], $assigned, $actor);
        $this->sendTo([$oldLeadId], $removed, $actor);
    }

    public function roleChanged(User $user, ?User $actor): void
    {
        if ($actor) {
            $row = new InboxNotification('role_changed', "{$actor->name} changed your role to {$user->roleLabel()}", 'profile', null, $actor);
            $this->sendTo([$user->id], $row, $actor);
        }
    }

    /**
     * `$finished` is done: tell the assignee of each task that was waiting for
     * it and now waits for nothing. Someone who finished it themselves still
     * hears, unless they're that assignee.
     */
    public function dependencyFinished(Task $finished, ?User $actor): void
    {
        $finished->blocking()
            ->whereNotIn('tasks.status', Task::DONE_STATUSES)
            ->whereNotNull('assignee_id')
            ->whereNull('blocked_at')
            ->whereDoesntHave('openBlockers')
            ->get()
            ->reject(fn (Task $waiting) => (int) $waiting->assignee_id === $actor?->id)
            ->each(fn (Task $waiting) => $this->deliver(User::whereKey($waiting->assignee_id)->get(), $waiting, new TaskUnblocked($waiting, $finished)));
    }

    /**
     * Remind assignees of open tasks due tomorrow, and of tasks on their first
     * day overdue. Safe to run more than once a day: each reminder is sent once.
     *
     * @return int the number of reminders sent
     */
    public function sendDueReminders(?CarbonInterface $today = null): int
    {
        $today = ($today ?? now())->copy()->startOfDay();
        $sent = 0;

        foreach (['due_tomorrow' => $today->copy()->addDay(), 'overdue' => $today->copy()->subDay()] as $kind => $date) {
            Task::query()
                ->with('assignee')
                ->whereNotNull('assignee_id')
                ->whereNotIn('status', Task::DONE_STATUSES)
                ->whereDate('due_date', $date->toDateString())
                ->each(function (Task $task) use ($kind, &$sent) {
                    if ($task->assignee && ! $this->alreadyReminded($task->assignee, $task, $kind)) {
                        $sent += $this->deliver(collect([$task->assignee]), $task, new TaskDueSoon($task, $kind));
                    }
                });
        }

        return $sent;
    }

    /**
     * The user's notifications, newest first.
     *
     * @return MorphMany<DatabaseNotification, User>
     */
    public function inbox(User $user, bool $unreadOnly = false): MorphMany
    {
        return $user->notifications()->when($unreadOnly, fn ($q) => $q->whereNull('read_at'));
    }

    /** @return Collection<int, DatabaseNotification> */
    public function latest(User $user, int $limit = self::DROPDOWN_LIMIT): Collection
    {
        return $user->notifications()->limit($limit)->get();
    }

    public function unreadCount(User $user): int
    {
        return $user->unreadNotifications()->count();
    }

    /** One of the user's own notifications; anyone else's is a 404. */
    public function find(User $user, string $id): DatabaseNotification
    {
        return $user->notifications()->whereKey($id)->firstOrFail();
    }

    public function markRead(DatabaseNotification $notification): DatabaseNotification
    {
        $notification->markAsRead();

        return $notification;
    }

    /** @return int how many were unread */
    public function markAllRead(User $user): int
    {
        return $user->unreadNotifications()->update(['read_at' => now()]);
    }

    /**
     * Send to each recipient who is active and may see the task.
     *
     * @param  Collection<int, User>  $recipients
     */
    private function deliver(Collection $recipients, Task $task, Notification $notification): int
    {
        // Judge visibility on the stored row: a model just filled from a request
        // still holds string ids, which isVisibleTo's strict checks would miss.
        $stored = $task->fresh() ?? $task;
        $sent = 0;

        foreach ($recipients as $user) {
            if ($user->isActive() && ! $user->trashed() && $stored->isVisibleTo($user)) {
                $this->dispatch($user, $notification);
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Send to each of these people who is active, isn't the actor, and passes
     * `$canSee`. Deleted accounts are out of the query already.
     *
     * @param  iterable<int, int|string|null>  $ids
     * @param  (Closure(User): bool)|null  $canSee
     */
    private function sendTo(iterable $ids, Notification $notification, ?User $actor = null, ?Closure $canSee = null): int
    {
        $ids = collect($ids)->filter()->map(fn ($id) => (int) $id)->unique()->reject(fn (int $id) => $id === $actor?->id);
        $sent = 0;

        if ($ids->isEmpty()) {
            return 0;
        }

        foreach (User::whereKey($ids->all())->get() as $user) {
            if ($user->isActive() && (! $canSee || $canSee($user))) {
                $this->dispatch($user, $notification);
                $sent++;
            }
        }

        return $sent;
    }

    private function dispatch(User $user, Notification $notification): void
    {
        if ($this->batchDepth > 0) {
            $this->buffer[] = [$user, $notification];
        } else {
            $user->notify($notification);
        }
    }

    /** Send what a batch held back, one row per person and kind wherever wording exists. */
    private function flush(): void
    {
        $groups = [];
        foreach ($this->buffer as [$user, $notification]) {
            $groups[$user->id.'|'.$notification->toArray($user)['kind']][] = [$user, $notification];
        }
        $this->buffer = [];

        foreach ($groups as $rows) {
            [$user, $first] = $rows[0];
            $grouped = count($rows) > 1 ? $this->groupedRow($first->toArray($user), count($rows)) : null;

            if ($grouped) {
                $user->notify($grouped);
            } else {
                foreach ($rows as [, $notification]) {
                    $user->notify($notification);
                }
            }
        }
    }

    /** @param  array<string, mixed>  $first */
    private function groupedRow(array $first, int $count): ?InboxNotification
    {
        $actorName = $first['actor_name'] ?? null;
        $kind = $first['kind'];
        $wording = (! $actorName ? (self::GROUPED["{$kind}:scheduled"] ?? null) : null) ?? (self::GROUPED[$kind] ?? null);

        if (! $wording) {
            return null;
        }

        return new InboxNotification(
            $kind,
            strtr($wording[0], [':actor' => (string) $actorName, ':count' => (string) $count]),
            $wording[1],
            null,
            null,
            ['actor_id' => $first['actor_id'] ?? null, 'actor_name' => $actorName, 'count' => $count],
        );
    }

    private function toAssignee(string $kind, string $message, Task $task, ?User $actor): void
    {
        if ($actor && $task->assignee_id && (int) $task->assignee_id !== $actor->id) {
            $this->deliver(User::whereKey($task->assignee_id)->get(), $task, $this->taskRow($kind, $message, $task, $actor));
        }
    }

    private function taskRow(string $kind, string $message, Task $task, ?User $actor = null): InboxNotification
    {
        return new InboxNotification($kind, $message, 'task', $task->id, $actor, ['task_id' => $task->id, 'task_title' => $task->title]);
    }

    private function releaseRow(string $kind, string $message, Release $release, User $actor): InboxNotification
    {
        return new InboxNotification($kind, $message, 'release', $release->id, $actor, ['release_id' => $release->id]);
    }

    /** @return Collection<int, int> the release's team lead and members */
    private function releasePeople(Release $release): Collection
    {
        return $release->members()->pluck('users.id')->push($release->team?->team_lead_id)->filter()->map(fn ($id) => (int) $id);
    }

    /** @return Collection<int, int> */
    private function orgLeadIds(): Collection
    {
        return User::active()->whereIn('role', [User::ROLE_ADMIN, User::ROLE_CTO, User::ROLE_TECH_LEAD])->pluck('id');
    }

    private function sendKpiResult(KpiReport $report, string $kind, string $message): void
    {
        $this->sendTo([$report->requested_by], new InboxNotification($kind, $message, 'kpi_reports'), null, fn (User $user) => $user->isAdmin());
    }

    private function period(KpiReport $report): string
    {
        return $report->period_start->format('M j').' to '.$report->period_end->format('M j, Y');
    }

    private function quote(string $title): string
    {
        return '“'.Str::limit($title, 80).'”';
    }

    private function alreadyReminded(User $user, Task $task, string $kind): bool
    {
        return $user->notifications()
            ->where('type', TaskDueSoon::class)
            ->where('data->task_id', $task->id)
            ->where('data->kind', $kind)
            ->exists();
    }
}
