<?php

namespace Tests\Feature\Services;

use App\Models\Comment;
use App\Models\Task;
use App\Models\TasksheetEntry;
use App\Models\Team;
use App\Models\User;
use App\Services\MemberAnalyticsService;
use App\Services\MemberPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Personal analytics are derived from the audit trail, so these tests build
 * real history: they travel to each moment and move the task there, exactly
 * as the board would, and let RecordsActivity write the trail.
 *
 * "Today" is Wednesday 2026-11-11 throughout.
 */
class MemberAnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    private const TODAY = '2026-11-11 10:00:00';

    private MemberAnalyticsService $service;

    private User $dev;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(MemberAnalyticsService::class);
        $this->dev = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
    }

    private function analytics(User $user, string $period = 'month'): array
    {
        $this->travelTo(Carbon::parse(self::TODAY));

        return $this->service->forUser($user, new MemberPeriod($period));
    }

    /** Create a task at a moment in time. */
    private function task(string $at, array $attrs = [], ?User $user = null): Task
    {
        $this->travelTo(Carbon::parse($at));

        return Task::create($attrs + [
            'title' => 'Task '.uniqid(), 'status' => 'todo', 'assignee_id' => ($user ?? $this->dev)->id,
        ]);
    }

    /** Move a task to a status at a moment in time — the board's write. */
    private function move(Task $task, string $status, string $at): Task
    {
        $this->travelTo(Carbon::parse($at));
        $task->update(['status' => $status]);

        return $task;
    }

    private function completedTask(string $doneAt, array $attrs = [], ?User $user = null): Task
    {
        $task = $this->task(Carbon::parse($doneAt)->subDays(3)->toDateTimeString(), $attrs, $user);

        return $this->move($task, 'done', $doneAt);
    }

    private function fullRow(Team $team, string $date, array $attrs = [], ?User $user = null): TasksheetEntry
    {
        return TasksheetEntry::create($attrs + [
            'team_id' => $team->id, 'user_id' => ($user ?? $this->dev)->id, 'date' => $date,
            'plan' => 'Plan', 'result' => 'Done', 'comment' => 'Fine', 'tickets' => 'PM-1',
            'work_points' => 5, 'ticket_count' => 2, 'ticket_points' => 3,
        ]);
    }

    private function leaveRow(Team $team, string $date): TasksheetEntry
    {
        return TasksheetEntry::create([
            'team_id' => $team->id, 'user_id' => $this->dev->id, 'date' => $date, 'leave_type' => 'sick',
        ]);
    }

    // -- Period -----------------------------------------------------------

    public function test_period_compares_the_same_elapsed_span_of_the_previous_period(): void
    {
        $month = new MemberPeriod('month', Carbon::parse('2026-11-11'));
        $this->assertSame(['2026-11-01', '2026-11-11', '2026-10-01', '2026-10-11'], [
            $month->start->toDateString(), $month->end->toDateString(),
            $month->previousStart->toDateString(), $month->previousEnd->toDateString(),
        ]);

        // A 31st never spills past a shorter previous month.
        $clamped = new MemberPeriod('month', Carbon::parse('2026-03-31'));
        $this->assertSame('2026-02-28', $clamped->previousEnd->toDateString());

        $week = new MemberPeriod('week', Carbon::parse('2026-11-11'));
        $this->assertSame(['2026-11-09', '2026-11-02', '2026-11-04'], [
            $week->start->toDateString(), $week->previousStart->toDateString(), $week->previousEnd->toDateString(),
        ]);

        $this->assertSame('month', MemberPeriod::fromRequest(request()->merge(['period' => 'year']))->key);
    }

    // -- Completions --------------------------------------------------------

    public function test_completed_counts_compare_with_the_same_point_last_month(): void
    {
        $this->completedTask('2026-11-03 12:00:00');
        $this->completedTask('2026-11-05 12:00:00');
        $this->completedTask('2026-11-10 12:00:00');
        $this->completedTask('2026-10-05 12:00:00');
        $this->completedTask('2026-10-20 12:00:00'); // past "this point last month"

        $kpi = $this->analytics($this->dev)['kpis']['completed'];

        $this->assertSame(['current' => 3, 'previous' => 1, 'change' => 2], $kpi);
    }

    public function test_reopened_work_drops_out(): void
    {
        $reopened = $this->completedTask('2026-11-03 12:00:00');
        $this->move($reopened, 'in_progress', '2026-11-04 12:00:00');

        $this->assertSame(0, $this->analytics($this->dev)['kpis']['completed']['current']);
    }

    public function test_archived_tasks_are_never_counted(): void
    {
        $archived = $this->completedTask('2026-11-03 12:00:00', ['due_date' => '2026-11-05']);
        $this->move($archived, 'archive', '2026-11-06 12:00:00');
        $straight = $this->task('2026-11-04 09:00:00', ['due_date' => '2026-11-01']);
        $this->move($straight, 'in_progress', '2026-11-04 10:00:00');
        $this->move($straight, 'archive', '2026-11-05 10:00:00'); // filed, never done

        $a = $this->analytics($this->dev);

        $this->assertSame(0, $a['kpis']['completed']['current']);
        $this->assertNull($a['kpis']['onTimeRate']['current']);
        $this->assertNull($a['kpis']['cycleDays']['current']);
        $this->assertSame(0, $a['today']['open']);
        $this->assertSame(0, $a['today']['overdue']);
        $this->assertSame(0, $a['taskTotal']);
        $this->assertArrayNotHasKey('archive', $a['statusCounts']);
        $this->assertSame([], $a['recent']);
        $this->assertSame(0, $a['bestWeek']['count']);
        $this->assertSame(0, $a['streak']['best']);
        $this->assertSame(0, $a['heatmap']['total']);

        // Restoring it to Done counts it again, from its original completion.
        $this->move($archived, 'done', '2026-11-10 12:00:00');
        $a = $this->analytics($this->dev);
        $this->assertSame(1, $a['kpis']['completed']['current']);
        $this->assertSame('2026-11-03', $a['recent'][0]['completedAt']->toDateString());
    }

    public function test_cycle_and_lead_time_come_from_status_history(): void
    {
        $first = $this->task('2026-11-01 09:00:00');
        $this->move($first, 'in_progress', '2026-11-02 09:00:00');
        $this->move($first, 'in_review', '2026-11-03 09:00:00');
        $this->move($first, 'done', '2026-11-04 09:00:00'); // 2 days in flight, 3 since created

        $second = $this->task('2026-11-05 09:00:00');
        $this->move($second, 'in_progress', '2026-11-05 09:00:00');
        $this->move($second, 'done', '2026-11-06 09:00:00'); // 1 day

        // Done without ever going In Progress: counts, but has no cycle time.
        $this->completedTask('2026-11-07 09:00:00');

        $kpis = $this->analytics($this->dev)['kpis'];

        $this->assertSame(3, $kpis['completed']['current']);
        $this->assertSame(1.5, $kpis['cycleDays']['current']);
        $this->assertNull($kpis['cycleDays']['previous']);
        $this->assertNull($kpis['cycleDays']['change']);
        $this->assertSame(2.3, $kpis['leadDays']['current']); // created → done: (3 + 1 + 3) / 3
    }

    public function test_on_time_rate_ignores_undated_tasks_and_guards_an_empty_denominator(): void
    {
        $this->assertNull($this->analytics($this->dev)['kpis']['onTimeRate']['current']);

        $this->completedTask('2026-11-04 12:00:00', ['due_date' => '2026-11-05']);
        $this->completedTask('2026-11-06 12:00:00', ['due_date' => '2026-11-05']);
        $this->completedTask('2026-11-05 18:00:00', ['due_date' => '2026-11-05']); // the due day itself is on time
        $this->completedTask('2026-11-06 12:00:00'); // undated

        $this->assertSame(67, $this->analytics($this->dev)['kpis']['onTimeRate']['current']);
    }

    // -- Today ---------------------------------------------------------------

    public function test_today_counts_and_focus_order(): void
    {
        $this->task('2026-11-01 09:00:00', ['title' => 'Started', 'status' => 'in_progress']);
        $this->task('2026-11-01 09:00:00', ['title' => 'Today', 'due_date' => '2026-11-11']);
        $this->task('2026-11-01 09:00:00', ['title' => 'Late', 'due_date' => '2026-11-09']);
        $this->task('2026-11-01 09:00:00', ['title' => 'Later', 'due_date' => '2026-11-17', 'status' => 'in_review']);
        $this->task('2026-11-01 09:00:00', ['title' => 'Far off', 'due_date' => '2026-12-30']);
        $this->completedTask('2026-11-05 09:00:00', ['due_date' => '2026-11-01']); // done work is never overdue

        $a = $this->analytics($this->dev);

        $this->assertSame(['open' => 5, 'overdue' => 1, 'dueSoon' => 2, 'inProgress' => 1, 'inReview' => 1], $a['today']);
        $this->assertSame(['Late', 'Today', 'Started'], collect($a['focus'])->pluck('task.title')->all());
        $this->assertSame(['overdue', 'due_today', 'in_progress'], collect($a['focus'])->pluck('reason')->all());
        $this->assertSame(1, $a['statusCounts']['done']);
        $this->assertSame(6, $a['taskTotal']);
    }

    // -- Habits ---------------------------------------------------------------

    public function test_streak_skips_weekends_and_full_day_leave_and_waits_for_today(): void
    {
        $team = Team::create(['name' => 'Alpha', 'color' => '#0891b2']);
        $team->members()->attach($this->dev);

        $this->completedTask('2026-11-06 15:00:00');          // Fri: active
        $this->fullRow($team, '2026-11-09');                  // Mon: active
        $this->leaveRow($team, '2026-11-10');                 // Tue: leave
        // Thu 5th: nothing — the run starts after it. Wed 11th (today): nothing yet.

        $streak = $this->analytics($this->dev)['streak'];

        $this->assertSame(2, $streak['current']);
        $this->assertSame(2, $streak['best']);
        $this->assertFalse($streak['activeToday']);
    }

    public function test_standup_and_tasksheet_rates(): void
    {
        $this->travelTo(Carbon::parse('2026-11-02 08:00:00'));
        $team = Team::create(['name' => 'Alpha', 'color' => '#0891b2']);
        $team->members()->attach($this->dev); // joined Monday the 2nd

        $this->fullRow($team, '2026-11-02')->forceFill(['standup_attended' => true])->save();
        TasksheetEntry::create(['team_id' => $team->id, 'user_id' => $this->dev->id, 'date' => '2026-11-03', 'plan' => 'Half'])
            ->forceFill(['standup_attended' => false])->save();
        $this->leaveRow($team, '2026-11-05');

        $kpis = $this->analytics($this->dev)['kpis'];

        $this->assertSame(1, $kpis['standupAttended']['current']);
        $this->assertSame(2, $kpis['standupMarked']['current']);
        $this->assertSame(50, $kpis['standupRate']['current']);

        // Weekdays Nov 2–10 minus the leave day = 6; today isn't owed until it's logged.
        $this->assertSame(6, $kpis['daysExpected']['current']);
        $this->assertSame(1, $kpis['daysLogged']['current']);
        $this->assertSame(17, $kpis['tasksheetRate']['current']);
        $this->assertSame(2, $kpis['tickets']['current']);
    }

    public function test_weekly_trend_best_week_and_pace(): void
    {
        // Week of Oct 19: 3 done. Week of Nov 2: 1 done. This week (Nov 9): 2 done.
        foreach (['2026-10-19', '2026-10-20', '2026-10-21', '2026-11-03', '2026-11-09', '2026-11-10'] as $day) {
            $this->completedTask($day.' 12:00:00');
        }

        $a = $this->analytics($this->dev);

        $this->assertCount(MemberAnalyticsService::TREND_WEEKS, $a['weekly']);
        $this->assertSame(2, end($a['weekly'])['count']);
        $this->assertTrue(end($a['weekly'])['current']);
        $best = collect($a['weekly'])->firstWhere('best', true);
        $this->assertSame(['2026-10-19', 3], [$best['weekStart']->toDateString(), $best['count']]);
        $this->assertSame('2026-10-19', $a['bestWeek']['weekStart']->toDateString());

        // 4 done in the 8 weeks before this one → ceil(0.5) = 1 a week, already beaten.
        $this->assertSame(['thisWeek' => 2, 'goal' => 1, 'pct' => 100], $a['pace']);

        $days = collect($a['heatmap']['weeks'])->flatten(1)->keyBy(fn ($d) => $d['date']->toDateString());
        $this->assertSame(1, $days['2026-11-10']['count']);
        $this->assertTrue($days['2026-11-12']['future']);
        $this->assertSame(6, $a['heatmap']['total']);
    }

    // -- Edge cases -------------------------------------------------------------

    public function test_a_brand_new_member_gets_nulls_not_zero_percentages(): void
    {
        $a = $this->analytics($this->dev);

        $this->assertFalse($a['hasHistory']);
        $this->assertFalse($a['onTeam']);
        $this->assertSame(0, $a['kpis']['completed']['current']);
        foreach (['onTimeRate', 'cycleDays', 'leadDays', 'standupRate', 'tasksheetRate'] as $rate) {
            $this->assertNull($a['kpis'][$rate]['current'], $rate);
        }
        $this->assertSame(['thisWeek' => 0, 'goal' => null, 'pct' => null], $a['pace']);
        $this->assertSame(['weekStart' => null, 'count' => 0], $a['bestWeek']);
        $this->assertSame(['current' => 0, 'best' => 0, 'activeToday' => false], $a['streak']);
        $this->assertSame([], $a['focus']);
        $this->assertSame([], $a['recent']);
    }

    public function test_only_the_members_own_work_is_counted(): void
    {
        $colleague = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $team = Team::create(['name' => 'Alpha', 'color' => '#0891b2']);
        $team->members()->attach([$this->dev->id, $colleague->id]);

        $this->completedTask('2026-11-10 12:00:00', [], $colleague);
        $this->task('2026-11-01 09:00:00', ['due_date' => '2026-11-02'], $colleague);
        $this->fullRow($team, '2026-11-10', [], $colleague);
        $task = $this->task('2026-11-01 09:00:00', [], $colleague);
        $comment = new Comment(['user_id' => $colleague->id, 'body' => 'Theirs']);
        $task->comments()->save($comment);

        $mine = $this->completedTask('2026-11-09 12:00:00');

        $a = $this->analytics($this->dev);

        $this->assertSame(1, $a['kpis']['completed']['current']);
        $this->assertSame(0, $a['today']['overdue']);
        $this->assertSame(0, $a['kpis']['tickets']['current']);
        $this->assertSame(0, $a['kpis']['comments']['current']);
        $this->assertSame(0, $a['kpis']['daysLogged']['current']);
        $this->assertSame([$mine->id], collect($a['recent'])->pluck('task.id')->all());
        $this->assertSame(1, $a['taskTotal']);
    }

    public function test_lead_feedback_is_never_read(): void
    {
        $team = Team::create(['name' => 'Alpha', 'color' => '#0891b2']);
        $team->members()->attach($this->dev);
        $this->fullRow($team, '2026-11-10', ['feedback' => 'Private lead note']);

        DB::enableQueryLog();
        $this->analytics($this->dev);

        $entryQueries = collect(DB::getQueryLog())->pluck('query')->filter(fn ($q) => str_contains($q, 'tasksheet_entries'));
        $this->assertNotEmpty($entryQueries);
        $entryQueries->each(fn ($q) => $this->assertStringNotContainsString('feedback', $q));
        $entryQueries->each(fn ($q) => $this->assertStringNotContainsString('*', $q));
    }

    public function test_query_count_is_fixed_regardless_of_history(): void
    {
        $count = function () {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->analytics($this->dev);

            return count(DB::getQueryLog());
        };

        $this->completedTask('2026-11-03 12:00:00');
        $few = $count();

        foreach (range(1, 8) as $i) {
            $this->completedTask('2026-10-'.str_pad((string) (10 + $i), 2, '0', STR_PAD_LEFT).' 12:00:00');
        }
        $this->assertSame($few, $count());
    }
}
