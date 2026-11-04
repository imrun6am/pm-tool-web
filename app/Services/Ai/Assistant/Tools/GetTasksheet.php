<?php

namespace App\Services\Ai\Assistant\Tools;

use App\Models\TasksheetEntry;
use App\Models\User;
use App\Services\Ai\Assistant\ReadTool;
use App\Services\Ai\Assistant\ToolOutput;
use App\Services\TasksheetService;
use Illuminate\Support\Carbon;

/** A day's sheet for a team the person may open. Lead feedback, scores and points stay out. */
class GetTasksheet extends ReadTool
{
    public function __construct(private readonly TasksheetService $tasksheet) {}

    public function name(): string
    {
        return 'get_tasksheet';
    }

    public function description(): string
    {
        return "A day's tasksheet for a team: each member's plan and result, and who has not filled theirs. Defaults to today and this person's team. Lead feedback is not available here.";
    }

    public function status(): string
    {
        return 'Reading the tasksheet';
    }

    public function properties(): array
    {
        return [
            'date' => ['type' => 'string', 'description' => 'YYYY-MM-DD. Defaults to today.'],
            'team_id' => ['type' => 'integer'],
        ];
    }

    public function run(array $input, User $user): ToolOutput
    {
        $teams = $this->tasksheet->teamsFor($user);
        $requested = filled($input['team_id'] ?? null) ? (int) $input['team_id'] : null;
        if ($requested && ! $teams->firstWhere('id', $requested)) {
            $this->fail('This person can’t open that team’s tasksheet.');
        }

        $team = $this->tasksheet->resolveTeam($teams, $requested);
        if (! $team) {
            return new ToolOutput('This person has no team with a tasksheet.');
        }

        try {
            $day = filled($input['date'] ?? null) ? Carbon::parse($input['date'])->startOfDay() : today();
        } catch (\Throwable) {
            $this->fail('The date must look like YYYY-MM-DD.');
        }

        $entries = $this->tasksheet->entriesFor($team, $day);
        $lines = $this->tasksheet->rowUsersFor($team, $day, $entries)->map(function (User $member) use ($entries) {
            /** @var TasksheetEntry|null $entry */
            $entry = $entries->get($member->id);
            if (! $entry) {
                return "{$member->name} (id {$member->id}): nothing saved.";
            }
            if ($entry->isFullDayLeave()) {
                return "{$member->name} (id {$member->id}): on leave.";
            }

            return sprintf('%s (id %d): plan: %s; result: %s.', $member->name, $member->id,
                self::excerpt($entry->plan, 600) ?: 'empty', self::excerpt($entry->result, 600) ?: 'empty');
        });

        return new ToolOutput("Tasksheet for {$team->name} (id {$team->id}) on {$day->toDateString()}:\n".($lines->implode("\n") ?: 'No members.'));
    }
}
