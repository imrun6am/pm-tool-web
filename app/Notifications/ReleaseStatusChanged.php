<?php

namespace App\Notifications;

use App\Models\Release;
use App\Models\ReleaseStatusUpdate;
use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/** A release was marked at risk or off track. */
class ReleaseStatusChanged extends Notification
{
    public function __construct(
        public readonly Release $release,
        public readonly ReleaseStatusUpdate $update,
        public readonly User $actor,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'release_status',
            'task_id' => null,
            'task_title' => null,
            'release_id' => $this->release->id,
            'actor_id' => $this->actor->id,
            'actor_name' => $this->actor->name,
            'excerpt' => Str::limit($this->update->note, 140),
            'message' => "{$this->actor->name} marked “".Str::limit($this->release->name, 80).'” as '.strtolower($this->update->statusLabel()),
        ];
    }
}
