<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Notification;

/**
 * An inbox row about anything: a task change, a release, an event, a team, a
 * finished background job, or several of a kind grouped into one. Holds the
 * facts and a ready-made sentence. `target_type` and `target_id` say what it
 * opens, and NotificationLink turns them into a page when it's opened.
 *
 * Only titles go in here, never note, event or score text: the stored row
 * stays after the recipient loses access to the record.
 */
class InboxNotification extends Notification
{
    /** @param  array<string, mixed>  $extra */
    public function __construct(
        public readonly string $kind,
        public readonly string $message,
        public readonly ?string $targetType = null,
        public readonly int|string|null $targetId = null,
        public readonly ?User $actor = null,
        public readonly array $extra = [],
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return array_merge([
            'kind' => $this->kind,
            'task_id' => null,
            'task_title' => null,
            'target_type' => $this->targetType,
            'target_id' => $this->targetId,
            'actor_id' => $this->actor?->id,
            'actor_name' => $this->actor?->name,
            'excerpt' => null,
            'message' => $this->message,
        ], $this->extra);
    }
}
