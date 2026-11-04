<?php

namespace App\Services\Ai\Assistant\Tools;

use App\Http\Requests\TasksheetEntryRequest;
use App\Models\TasksheetEntry;
use App\Models\Team;
use App\Models\User;
use App\Services\Ai\Assistant\ActionDraft;
use App\Services\Ai\Assistant\ActionResult;
use App\Services\Ai\Assistant\ActionTool;
use App\Services\Ai\Assistant\FormRules;
use App\Services\Board\PlanToBoardService;
use App\Services\TasksheetService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;

/**
 * Adds lines to a row's plan or result, after whatever it already holds, so
 * nobody's words are overwritten. Feedback, attendance and points aren't
 * reachable from here.
 */
class AddToTasksheet extends ActionTool
{
    private const MAX_LINES = 20;

    private const MAX_LINE = 500;

    public function __construct(private readonly TasksheetService $tasksheet) {}

    public function name(): string
    {
        return 'add_to_tasksheet';
    }

    public function description(): string
    {
        return "Draft lines to add to a tasksheet row's morning plan or day-end result, for the person to confirm. Existing text is kept. Defaults to their own row today; on several teams they must choose one. Keep each line short, starting with a verb. A result may only claim what they told you or what the tools show.";
    }

    public function properties(): array
    {
        return [
            'field' => ['type' => 'string', 'enum' => ['plan', 'result'], 'description' => 'Defaults to plan.'],
            'lines' => ['type' => 'array', 'items' => ['type' => 'string']],
            'date' => ['type' => 'string', 'description' => 'YYYY-MM-DD. Defaults to today.'],
            'team_id' => ['type' => 'integer'],
            'user_id' => ['type' => 'integer', 'description' => 'Whose row, for a lead correcting someone else’s. Defaults to the person.'],
        ];
    }

    public function required(): array
    {
        return ['lines'];
    }

    public function draft(array $input, User $user): ActionDraft
    {
        $field = $input['field'] ?? 'plan';
        if (! in_array($field, ['plan', 'result'], true)) {
            $this->fail('field', 'Choose the plan or the result.');
        }

        $lines = collect(is_array($input['lines'] ?? null) ? $input['lines'] : [])
            ->map(fn ($line) => is_string($line) ? trim($line) : '')->filter()->values();
        if ($lines->isEmpty()) {
            $this->fail('lines', 'There’s nothing to add.');
        }
        if ($lines->count() > self::MAX_LINES || $lines->contains(fn (string $line) => mb_strlen($line) > self::MAX_LINE)) {
            $this->fail('lines', 'Keep it to '.self::MAX_LINES.' short lines.');
        }

        $userId = (int) ($input['user_id'] ?? $user->id);
        $date = self::day($input['date'] ?? null);
        $teamId = $input['team_id'] ?? $this->defaultTeam($userId, $user);

        $probe = ['team_id' => $teamId, 'user_id' => $userId, 'date' => $date, $field => null];
        $checked = FormRules::check(TasksheetEntryRequest::class, $probe, $user)->validated();

        $entry = $this->tasksheet->resolveEntry($checked);
        if (! $user->can('update', $entry)) {
            throw new AuthorizationException('Members can only change their own row on a team they’re on.');
        }
        if ($entry->isFullDayLeave()) {
            $this->fail('date', 'That day is marked as leave, so its row takes no plan or result.');
        }

        $merged = $this->merged($entry, $field, $lines->all());
        FormRules::check(TasksheetEntryRequest::class, [$field => $merged] + $checked, $user);

        $member = User::withTrashed()->find($userId);
        $existing = PlanToBoardService::plainText((string) $entry->{$field});
        $day = Carbon::parse($checked['date']);

        return new ActionDraft(
            ['field' => $field, 'lines' => $lines->all(), 'team_id' => (int) $checked['team_id'], 'user_id' => $userId, 'date' => $day->toDateString()],
            [
                'kind' => 'sheet',
                'action' => $field === 'plan' ? 'Add to plan' : 'Add to result',
                'title' => $field === 'plan' ? 'Morning plan' : 'Day-end result',
                'member' => self::person($member),
                'team' => Team::find($checked['team_id'])?->name,
                'day' => $day->isToday() ? 'Today' : self::dayLabel($day),
                'lines' => $lines->all(),
                'keeps' => filled(trim($existing)) ? mb_strimwidth(trim($existing), 0, 160, '…') : null,
                'warning' => $userId !== $user->id ? "This is {$member?->name}’s row, not yours." : null,
            ],
        );
    }

    public function run(array $input, User $user): ActionResult
    {
        $entry = $this->tasksheet->resolveEntry($input);
        $this->tasksheet->save($entry, [$input['field'] => $this->merged($entry, $input['field'], $input['lines'])], $user);

        return new ActionResult(
            $input['field'] === 'plan' ? 'Added to plan' : 'Added to result',
            route('tasksheet.index', ['team' => $entry->team_id, 'date' => $entry->date->toDateString()]),
            "Added to the {$input['field']} on the tasksheet for {$entry->date->toDateString()}.",
        );
    }

    private function merged(TasksheetEntry $entry, string $field, array $lines): string
    {
        return ((string) $entry->{$field}).'<ul>'.implode('', array_map(fn (string $line) => '<li>'.e($line).'</li>', $lines)).'</ul>';
    }

    private function defaultTeam(int $userId, User $user): int
    {
        $teams = Team::active()->whereHas('members', fn ($q) => $q->whereKey($userId))->orderBy('name')->get(['id', 'name']);
        if ($teams->count() === 1) {
            return $teams->first()->id;
        }

        $this->fail('team_id', $teams->isEmpty()
            ? ($userId === $user->id ? 'This person isn’t on a team, so they have no tasksheet row.' : 'That person isn’t on a team.')
            : 'Say which team: '.$teams->map(fn (Team $t) => "{$t->name} (id {$t->id})")->implode(', ').'.');
    }
}
