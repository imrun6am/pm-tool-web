<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Base for the inbox notifications about a task. Delivered synchronously to
 * the database channel, so nothing waits on a queue worker. The payload keeps
 * the facts (kind, task, actor) plus a ready-made sentence; links are built
 * by whoever displays it, never stored.
 */
abstract class TaskNotification extends Notification
{
    public function __construct(public readonly Task $task) {}

    /** The `kind` stored in the payload (see NotificationService::KINDS). */
    abstract public function kind(): string;

    /** One plain-text sentence describing what happened. */
    abstract protected function message(): string;

    /** @return array<string, mixed> */
    protected function extra(): array
    {
        return [];
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return array_merge([
            'kind' => $this->kind(),
            'task_id' => $this->task->id,
            'task_title' => $this->task->title,
            'actor_id' => null,
            'actor_name' => null,
            'excerpt' => null,
            'message' => $this->message(),
        ], $this->extra());
    }

    protected function title(): string
    {
        return '“'.Str::limit($this->task->title, 80).'”';
    }
}
