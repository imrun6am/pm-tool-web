<?php

namespace App\Support;

use App\Models\Event;
use App\Models\EventImport;
use App\Models\MeetingNote;
use App\Models\Note;
use App\Models\Release;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Gate;

/**
 * What a notification points at, and where opening it goes. Worked out when
 * it's opened, so a record that's gone or out of reach never ends in a 403.
 */
class NotificationLink
{
    /**
     * The record this row is about. Rows written before targets existed carry
     * only the ids they were built with.
     *
     * @param  array<string, mixed>  $data
     * @return array{type: ?string, id: int|string|null}
     */
    public static function target(array $data): array
    {
        return match (true) {
            ! empty($data['target_type']) => ['type' => $data['target_type'], 'id' => $data['target_id'] ?? null],
            ! empty($data['triage_item_id']) => ['type' => 'request', 'id' => $data['triage_item_id']],
            ! empty($data['task_id']) => ['type' => 'task', 'id' => $data['task_id']],
            ! empty($data['release_id']) => ['type' => 'release', 'id' => $data['release_id']],
            default => ['type' => null, 'id' => null],
        };
    }

    /** The page to open, or null when the record is gone or out of the viewer's reach. */
    public static function url(DatabaseNotification $notification, User $viewer): ?string
    {
        $data = $notification->data;
        $target = self::target($data);

        // An accepted request names its task, but the task may sit on a team the
        // reporter isn't on, and the Requests page always shows the outcome.
        if (! empty($data['task_id']) && ($task = Task::find($data['task_id'])) && $task->isVisibleTo($viewer)) {
            return route('tasks.show', $task);
        }

        $id = $target['id'];

        return match ($target['type']) {
            'request' => route('triage.index'),
            'tasks' => route('tasks.index', ['assignee_id' => $viewer->id]),
            'release' => ($release = Release::find($id)) && $release->isVisibleTo($viewer) ? route('releases.show', $release) : null,
            'meeting_note' => ($note = MeetingNote::find($id)) && $note->isVisibleTo($viewer) ? route('meeting-notes.show', $note) : null,
            'event' => ($event = Event::find($id)) && $event->isVisibleTo($viewer) ? route('events.show', $event) : null,
            'calendar' => route('calendar.index'),
            'note' => ($note = Note::find($id)) && $note->isVisibleTo($viewer) ? route('notes.index', ['date' => $note->date->toDateString()]) : null,
            'event_import' => ($import = EventImport::find($id)) && Gate::forUser($viewer)->allows('view', $import) ? route('calendar.imports.show', $import) : null,
            'tasksheet' => route('tasksheet.index', array_filter(['team' => $id, 'date' => $data['for_date'] ?? null])),
            'tasksheet_exports' => route('tasksheet.exports.index'),
            'kpi_reports' => $viewer->isAdmin() ? route('kpi-reports.index') : null,
            'team' => $viewer->hasLimitedAccess() || ! Team::whereKey($id)->exists() ? route('dashboard') : route('teams.show', $id),
            'dashboard' => route('dashboard'),
            'profile' => route('profile.edit'),
            'inbox' => route('notifications.index'),
            default => null,
        };
    }
}
