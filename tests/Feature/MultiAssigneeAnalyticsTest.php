<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Services\Ai\Assistant\Tools\MyWork;
use App\Services\CapacityService;
use App\Services\MemberAnalyticsService;
use App\Services\MemberPeriod;
use App\Services\PeopleAnalyticsService;
use App\Services\PerformanceAnalytics;
use App\Services\TaskAssignees;
use App\Services\TaskCompletions;
use App\Services\TeamService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A task shared by two people counts in full for each of them, its estimate
 * is split between them, and team totals still count it once.
 */
class MultiAssigneeAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    /** A Wednesday. */
    private const NOW = '2026-11-11 10:00:00';

    private Team $team;

    private User $dana;

    private User $quinn;

    protected function setUp(): void
    {
        parent::setUp();

        config(['planning.weekend_days' => ['saturday', 'sunday'], 'planning.hours_per_day' => 8]);
        $this->travelTo(self::NOW);
        $this->team = Team::create(['name' => 'Core', 'color' => '#0891b2']);
        $this->dana = User::factory()->create(['role' => User::ROLE_DEVELOPER, 'name' => 'Dana Dev']);
        $this->quinn = User::factory()->create(['role' => User::ROLE_QA, 'name' => 'Quinn QA']);

        foreach ([$this->dana, $this->quinn] as $user) {
            DB::table('team_user')->insert([
                'team_id' => $this->team->id, 'user_id' => $user->id,
                'created_at' => '2026-01-01', 'updated_at' => '2026-01-01',
            ]);
        }
    }

    /** A task led by Dana, shared with Quinn. */
    private function shared(array $attrs = []): Task
    {
        $task = Task::create($attrs + [
            'title' => 'Pair work', 'status' => 'todo', 'position' => 0,
            'assignee_id' => $this->dana->id, 'team_id' => $this->team->id,
        ]);
        app(TaskAssignees::class)->sync($task, [$this->dana->id, $this->quinn->id]);

        return $task->fresh();
    }

    /** Shared, started on Monday and finished on Tuesday. */
    private function finishedShared(): Task
    {
        $this->travelTo('2026-11-08 09:00:00');
        $task = $this->shared(['due_date' => '2026-11-12']);
        $this->travelTo('2026-11-09 09:00:00');
        $task->update(['status' => 'in_progress']);
        $this->travelTo('2026-11-10 09:00:00');
        $task->update(['status' => 'done']);
        $this->travelTo(self::NOW);

        return $task;
    }

    private function report(): array
    {
        return app(PeopleAnalyticsService::class)->report([
            'from' => Carbon::parse('2026-11-01'), 'to' => Carbon::parse('2026-11-11'), 'team_id' => null,
        ]);
    }

    private function personRecord(array $report, User $user): array
    {
        return collect($report['people'])->first(fn ($r) => $r['user']->is($user))['record'];
    }

    public function test_completions_give_one_record_per_task_and_one_per_assignee_on_request(): void
    {
        $task = $this->finishedShared();

        $records = app(TaskCompletions::class)->since(fn () => Task::query(), Carbon::parse('2026-11-01'));

        $this->assertCount(1, $records);
        $this->assertSame([$this->dana->id, $this->quinn->id], $records[0]['assigneeIds']);

        $each = TaskCompletions::perAssignee($records);
        $this->assertSame([$this->dana->id, $this->quinn->id], $each->pluck('assigneeId')->all());
        $this->assertSame([true, false], $each->pluck('lead')->all());
        $this->assertSame([$task->id, $task->id], $each->pluck('taskId')->all());
    }

    public function test_people_analytics_credit_both_and_count_the_task_once_for_the_team(): void
    {
        $this->finishedShared();
        $this->shared(['due_date' => '2026-11-02']);

        $report = $this->report();

        foreach ([$this->dana, $this->quinn] as $user) {
            $record = $this->personRecord($report, $user);
            $this->assertSame(1, $record['delivery']['completed'], "{$user->name} finished it");
            $this->assertSame(1, $record['workload']['open'], "{$user->name} has it open");
            $this->assertSame(1, $record['workload']['overdue']);
        }

        $team = collect($report['teams'])->firstWhere('name', 'Core')['record'];
        $this->assertSame(1, $team['delivery']['completed']);
        $this->assertSame(1, $team['workload']['open']);
        $this->assertSame(1, $report['summary']['delivery']['completed']);
        $this->assertSame(1, $report['summary']['workload']['open']);
        $this->assertSame(1, array_sum(array_column($report['trend']['buckets'], 'completed')));

        $person = app(PeopleAnalyticsService::class)->person($this->quinn, [
            'from' => Carbon::parse('2026-11-01'), 'to' => Carbon::parse('2026-11-11'), 'team_id' => null,
        ]);
        $this->assertCount(1, $person['finished']);
        $this->assertCount(1, $person['open']);
    }

    public function test_the_member_dashboard_counts_a_task_for_a_co_assignee(): void
    {
        $this->finishedShared();
        $this->shared();

        $quinn = app(MemberAnalyticsService::class)->forUser($this->quinn, new MemberPeriod('month'));

        $this->assertSame(1, $quinn['today']['open']);
        $this->assertCount(1, $quinn['recent']);
    }

    public function test_the_performance_record_credits_both_and_the_team_total_counts_once(): void
    {
        $this->finishedShared();
        $this->shared(['due_date' => '2026-11-02']);

        $overview = app(PerformanceAnalytics::class)->teamOverview($this->team, today());

        $this->assertSame(1, $overview['records'][$this->dana->id]['completed']);
        $this->assertSame(1, $overview['records'][$this->quinn->id]['completed']);
        $this->assertSame(1, $overview['records'][$this->quinn->id]['overdue']);
        $this->assertSame(1, $overview['recordTotals']['completed']);
        $this->assertSame(1, $overview['recordTotals']['overdue']);
    }

    public function test_team_workload_counts_the_task_for_each_assignee(): void
    {
        $this->shared();

        $summary = app(TeamService::class)->taskSummary($this->team);

        $this->assertSame(1, $summary['open']);
        $this->assertSame(1, $summary['byAssignee'][$this->dana->id]);
        $this->assertSame(1, $summary['byAssignee'][$this->quinn->id]);
    }

    public function test_capacity_splits_the_estimate_between_assignees(): void
    {
        $this->shared(['estimate_minutes' => 480, 'due_date' => '2026-11-13']);

        $people = collect(app(CapacityService::class)->forTeam($this->team)['people']);

        foreach ([$this->dana, $this->quinn] as $user) {
            $cell = $people->firstWhere('user.id', $user->id)['cells'][0];
            $this->assertSame(240, $cell['load_minutes'], "{$user->name} carries half of 8h");
            $this->assertSame(1, $cell['tasks']);
        }
    }

    public function test_the_assistant_lists_a_shared_task_for_a_co_assignee(): void
    {
        $task = $this->shared();

        $text = app(MyWork::class)->run([], $this->quinn)->text;

        $this->assertStringContainsString("[T{$task->id}] Pair work", $text);
        $this->assertStringContainsString("assignees Dana Dev (id {$this->dana->id}), Quinn QA (id {$this->quinn->id})", $text);
    }
}
