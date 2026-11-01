<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssigned;
use App\Notifications\TaskCommented;
use App\Notifications\TaskDueSoon;
use App\Notifications\TaskNotification;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;

/**
 * The in-app inbox: who is told about what, and reading it back.
 *
 * Recipient rules live here and nowhere else. Nobody is told about their own
 * action, deactivated accounts are skipped, and a recipient must be able to
 * see the task (Task::isVisibleTo), so a developer or QA never hears about
 * another team's work. Triggers are called from the model observers, which
 * makes every write path (board, task page, API, tasksheet) behave the same.
 */
class NotificationService
{
    public const KINDS = ['assigned', 'commented', 'due_tomorrow', 'overdue'];

    /** How many notifications the top-bar dropdown shows. */
    public const DROPDOWN_LIMIT = 8;

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
    private function deliver(Collection $recipients, Task $task, TaskNotification $notification): int
    {
        // Judge visibility on the stored row: a model just filled from a request
        // still holds string ids, which isVisibleTo's strict checks would miss.
        $stored = $task->fresh() ?? $task;
        $sent = 0;

        foreach ($recipients as $user) {
            if ($user->isActive() && ! $user->trashed() && $stored->isVisibleTo($user)) {
                $user->notify($notification);
                $sent++;
            }
        }

        return $sent;
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
