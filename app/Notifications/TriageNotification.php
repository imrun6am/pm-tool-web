<?php

namespace App\Notifications;

use App\Models\TriageItem;
use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/** Inbox notice about a request: a new one to decide on, or a decision on yours. */
class TriageNotification extends Notification
{
    public function __construct(
        public readonly TriageItem $item,
        public readonly string $kind,
        public readonly ?User $actor,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $title = '“'.Str::limit($this->item->title, 80).'”';
        $name = $this->actor?->name ?? 'Someone';

        return [
            'kind' => $this->kind,
            'task_id' => $this->item->task_id,
            'task_title' => $this->item->title,
            'triage_item_id' => $this->item->id,
            'actor_id' => $this->actor?->id,
            'actor_name' => $this->actor?->name,
            'excerpt' => $this->kind === 'request_declined' ? Str::limit((string) $this->item->decline_reason, 140) : null,
            'message' => match ($this->kind) {
                'request_new' => "{$name} sent a request: {$title}",
                'request_accepted' => "{$name} accepted your request {$title}",
                default => "{$name} declined your request {$title}",
            },
        ];
    }
}
