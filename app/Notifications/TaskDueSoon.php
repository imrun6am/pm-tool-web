<?php

namespace App\Notifications;

use App\Models\Task;

/** A gentle reminder: once the day before the due date, once on the first day late. */
class TaskDueSoon extends TaskNotification
{
    public function __construct(Task $task, public readonly string $reminder)
    {
        parent::__construct($task);
    }

    public function kind(): string
    {
        return $this->reminder;
    }

    protected function message(): string
    {
        return $this->reminder === 'overdue'
            ? "{$this->title()} was due yesterday"
            : "{$this->title()} is due tomorrow";
    }
}
