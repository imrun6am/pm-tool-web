<?php

namespace App\Services\Ai\Assistant;

use App\Models\Event;
use App\Models\MeetingNote;
use App\Models\Release;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;

/**
 * Which record the person is looking at, so "this task" means something. The
 * browser sends only a path; a record is named only if they may open it.
 */
class PageContext
{
    /** @return array{text: string, refs: array<string, array{ref: string, kind: string, title: string, url: string}>} */
    public function describe(?string $path, User $user): array
    {
        $path = '/'.ltrim((string) parse_url((string) $path, PHP_URL_PATH), '/');

        $record = match (true) {
            (bool) preg_match('#^/tasks/(\d+)#', $path, $m) => $this->task((int) $m[1], $user),
            (bool) preg_match('#^/releases/(\d+)#', $path, $m) => $this->release((int) $m[1], $user),
            (bool) preg_match('#^/events/(\d+)#', $path, $m) => $this->event((int) $m[1], $user),
            (bool) preg_match('#^/meeting-notes/(\d+)#', $path, $m) => $this->meeting((int) $m[1], $user),
            (bool) preg_match('#^/teams/(\d+)#', $path, $m) => $this->team((int) $m[1], $user),
            default => null,
        };

        if ($record === null) {
            return ['text' => mb_strimwidth($path, 0, 80), 'refs' => []];
        }

        return ['text' => "{$record['kind']} [{$record['ref']}] (id {$record['id']}): {$record['title']}", 'refs' => [
            $record['ref'] => collect($record)->only(['ref', 'kind', 'title', 'url'])->all(),
        ]];
    }

    private function task(int $id, User $user): ?array
    {
        $task = Task::visibleTo($user)->find($id);

        return $task ? ['id' => $id, 'ref' => "T{$id}", 'kind' => 'Task', 'title' => $task->title, 'url' => route('tasks.show', $task)] : null;
    }

    private function release(int $id, User $user): ?array
    {
        $release = Release::visibleTo($user)->find($id);

        return $release ? ['id' => $id, 'ref' => "R{$id}", 'kind' => 'Release', 'title' => $release->name, 'url' => route('releases.show', $release)] : null;
    }

    private function event(int $id, User $user): ?array
    {
        $event = Event::visibleTo($user)->find($id);

        return $event ? ['id' => $id, 'ref' => "E{$id}", 'kind' => 'Event', 'title' => $event->title, 'url' => route('events.show', $event)] : null;
    }

    private function meeting(int $id, User $user): ?array
    {
        $note = MeetingNote::visibleTo($user)->find($id);

        return $note ? ['id' => $id, 'ref' => "M{$id}", 'kind' => 'Meeting note', 'title' => $note->title, 'url' => route('meeting-notes.show', $note)] : null;
    }

    private function team(int $id, User $user): ?array
    {
        $team = Team::when($user->hasLimitedAccess(), fn ($q) => $q->whereIn('id', $user->currentTeamIds()))->find($id);

        return $team ? ['id' => $id, 'ref' => "G{$id}", 'kind' => 'Team', 'title' => $team->name, 'url' => route('teams.show', $team)] : null;
    }
}
