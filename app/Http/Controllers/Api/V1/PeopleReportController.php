<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ReadsPeopleReportFilters;
use App\Models\Task;
use App\Models\User;
use App\Services\PeopleAnalyticsService;
use App\Support\PeopleReportCsv;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Admin analytics per team and per person; the routes are behind `admin`. */
class PeopleReportController extends ApiController
{
    use ReadsPeopleReportFilters;

    public function index(Request $request, PeopleAnalyticsService $people): JsonResponse
    {
        $report = $people->report($this->peopleReportFilters($request));

        return $this->ok([
            'from' => $report['from']->toDateString(),
            'to' => $report['to']->toDateString(),
            'team_id' => $report['teamId'],
            'working_days' => $report['workingDays'],
            'blocked_history_from' => $report['blockedHistoryFrom']?->toDateString(),
            'summary' => $this->record($report['summary']),
            'compare' => $report['compare'],
            'teams' => array_map(fn (array $team) => [
                'id' => $team['id'],
                'name' => $team['name'],
                'color' => $team['color'],
                'lead' => $team['lead'] ? ['id' => $team['lead']->id, 'name' => $team['lead']->name] : null,
                'people' => $team['people'],
                'archived' => $team['archived'],
                'record' => $this->record($team['record']),
            ], $report['teams']),
            'people' => array_map(fn (array $row) => $this->person($row['user'], $row['teams']) + [
                'tag' => $row['tag'],
                'record' => $this->record($row['record']),
            ], $report['people']),
            'trend' => $this->trend($report['trend']),
        ]);
    }

    public function show(Request $request, User $user, PeopleAnalyticsService $people): JsonResponse
    {
        $report = $people->person($user, $this->peopleReportFilters($request));

        return $this->ok($this->person($user, $report['teams']) + [
            'from' => $report['from']->toDateString(),
            'to' => $report['to']->toDateString(),
            'team_id' => $report['teamId'],
            'working_days' => $report['workingDays'],
            'blocked_history_from' => $report['blockedHistoryFrom']?->toDateString(),
            'record' => $this->record($report['record']),
            'categories' => array_values($report['categories']),
            'compare' => $report['compare'],
            'by_team' => array_map(fn (array $row) => [
                'id' => $row['id'],
                'name' => $row['name'],
                'record' => $this->record($row['record']),
            ], $report['byTeam']),
            'trend' => $this->trend($report['trend']),
            'finished' => array_map(fn (array $row) => $this->task($row['task']) + [
                'completed_at' => $row['completedAt']->toIso8601String(),
                'cycle_days' => $row['cycleDays'],
                'on_time' => $row['onTime'],
                'reworked' => $row['reworked'],
            ], $report['finished']),
            'open' => array_map(fn (Task $task) => $this->task($task) + ['blocked' => $task->isBlocked()], $report['open']),
        ]);
    }

    public function export(Request $request, PeopleAnalyticsService $people): StreamedResponse
    {
        $filters = $this->peopleReportFilters($request);
        $report = $people->report($filters);
        $name = fn (string $what) => sprintf('%s_%s_%s.csv', $what, $filters['from']->toDateString(), $filters['to']->toDateString());

        return $request->input('level') === 'teams'
            ? PeopleReportCsv::teams($report['teams'], $name('team_analytics'))
            : PeopleReportCsv::people($report['people'], $name('people_analytics'));
    }

    /** @param  list<string>  $teams */
    private function person(User $user, array $teams): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'role' => $user->role,
            'status' => $user->statusTag() ? Str::lower((string) $user->statusTag()) : 'active',
            'teams' => $teams,
        ];
    }

    /** The record, with its dates as strings. */
    private function record(array $record): array
    {
        array_walk_recursive($record, function (&$value) {
            if ($value instanceof CarbonInterface) {
                $value = $value->toDateString();
            }
        });

        return $record;
    }

    private function trend(array $trend): array
    {
        return [
            'unit' => $trend['unit'],
            'buckets' => array_map(fn (array $b) => [
                'start' => $b['start']->toDateString(),
                'end' => $b['end']->toDateString(),
                'completed' => $b['completed'],
                'logged_minutes' => $b['minutes'],
            ], $trend['buckets']),
        ];
    }

    private function task(Task $task): array
    {
        return [
            'id' => $task->id,
            'key' => $task->reference(),
            'title' => $task->title,
            'status' => $task->status,
            'team' => $task->team?->name,
            'release' => $task->release?->name,
            'due_date' => $task->due_date?->toDateString(),
            'estimate_minutes' => $task->estimate_minutes,
            'logged_minutes' => (int) $task->logged_minutes,
        ];
    }
}
