<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Services\FlowReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Team-level flow over the last 12 weeks. "Today" is Monday, 2 November 2026. */
class FlowReportTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-02 09:00:00');
        $this->team = Team::create(['name' => 'Core', 'color' => '#0891b2']);
    }

    /** @param  array<string, string>  $moves  date => status */
    private function task(string $createdAt, array $moves = [], array $attrs = []): Task
    {
        $back = now()->copy();
        $this->travelTo($createdAt);
        $task = Task::create($attrs + ['title' => 'Work', 'status' => 'todo', 'position' => 0, 'team_id' => $this->team->id]);
        foreach ($moves as $at => $status) {
            $this->travelTo($at);
            $task->update(['status' => $status]);
        }
        $this->travelTo($back);

        return $task;
    }

    private function report(?int $teamId = null): array
    {
        return app(FlowReportService::class)->report($teamId);
    }

    private function weekOf(array $report, string $date): int
    {
        return collect($report['weeks'])->search(fn ($w) => $w['start']->toDateString() === $date);
    }

    public function test_it_counts_what_sat_in_each_status_at_the_end_of_each_week(): void
    {
        $this->task('2026-10-13 09:00:00', ['2026-10-14 09:00:00' => 'in_progress', '2026-10-21 09:00:00' => 'in_review', '2026-10-28 09:00:00' => 'done']);

        $report = $this->report();

        $this->assertCount(12, $report['weeks']);
        $this->assertSame(1, $report['flow'][$this->weekOf($report, '2026-10-12')]['in_progress']);
        $this->assertSame(1, $report['flow'][$this->weekOf($report, '2026-10-19')]['in_review']);
        $this->assertSame(1, $report['flow'][$this->weekOf($report, '2026-10-26')]['done']);
        $this->assertSame(0, array_sum($report['flow'][$this->weekOf($report, '2026-10-05')]));
    }

    public function test_archived_work_and_work_finished_before_the_window_are_left_out(): void
    {
        $this->task('2026-10-13 09:00:00', ['2026-10-14 09:00:00' => 'archive']);
        $this->task('2026-01-05 09:00:00', ['2026-01-07 09:00:00' => 'done']);

        $this->assertSame(0, collect($this->report()['flow'])->flatten()->sum());
    }

    public function test_it_compares_tasks_added_with_tasks_finished_each_week(): void
    {
        $this->task('2026-10-13 09:00:00');
        $this->task('2026-10-14 09:00:00', ['2026-10-20 09:00:00' => 'done']);

        $report = $this->report();
        $added = $this->weekOf($report, '2026-10-12');
        $finished = $this->weekOf($report, '2026-10-19');

        $this->assertSame(2, $report['arrivals'][$added]);
        $this->assertSame(0, $report['completions'][$added]);
        $this->assertSame(1, $report['completions'][$finished]);
    }

    public function test_cycle_time_runs_from_starting_to_finishing(): void
    {
        foreach ([2, 4, 6, 8, 20] as $days) {
            $this->task('2026-09-01 09:00:00', [
                '2026-10-01 09:00:00' => 'in_progress',
                now()->setDate(2026, 10, 1)->addDays($days)->setTime(9, 0)->toDateTimeString() => 'done',
            ]);
        }
        $this->task('2026-09-01 09:00:00', ['2026-10-02 09:00:00' => 'done']); // never started: no duration

        $report = $this->report();

        $this->assertCount(5, $report['cycle']);
        $this->assertSame(6.0, $report['cycle_p50']);
        $this->assertSame(20.0, $report['cycle_p85']);
        $this->assertSame(6, $report['finished']);
    }

    public function test_it_flags_work_in_progress_for_longer_than_most_work_takes(): void
    {
        foreach (range(1, 5) as $n) {
            $this->task('2026-09-01 09:00:00', ['2026-10-01 09:00:00' => 'in_progress', '2026-10-03 09:00:00' => 'done']);
        }
        $old = $this->task('2026-10-01 09:00:00', ['2026-10-05 09:00:00' => 'in_progress'], ['title' => 'Stuck']);
        $this->task('2026-10-31 09:00:00', ['2026-11-01 09:00:00' => 'in_progress'], ['title' => 'Fresh']);
        $this->task('2026-09-01 09:00:00', [], ['title' => 'Not started']);

        $aging = $this->report()['aging'];

        $this->assertSame([$old->id], array_column($aging, 'task_id'));
        $this->assertSame(28, $aging[0]['days']);
    }

    public function test_it_can_be_narrowed_to_one_team(): void
    {
        $other = Team::create(['name' => 'Other', 'color' => '#db2777']);
        $this->task('2026-10-13 09:00:00');
        $this->task('2026-10-13 09:00:00', [], ['team_id' => $other->id]);

        $this->assertSame(2, array_sum($this->report()['arrivals']));
        $this->assertSame(1, array_sum($this->report($other->id)['arrivals']));
    }

    public function test_old_tasks_without_history_are_counted_as_they_are(): void
    {
        $task = $this->task('2026-10-13 09:00:00', ['2026-10-20 09:00:00' => 'done']);
        $task->statusChanges()->delete();

        $this->assertSame(1, last($this->report()['flow'])['done']);
    }

    public function test_leads_and_viewers_can_see_it_and_developers_cannot(): void
    {
        foreach ([User::ROLE_ADMIN, User::ROLE_CTO, User::ROLE_TECH_LEAD, User::ROLE_TEAM_LEAD, User::ROLE_VIEWER] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get(route('reports.flow'))->assertOk();
        }

        foreach ([User::ROLE_DEVELOPER, User::ROLE_QA] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get(route('reports.flow'))->assertForbidden();
        }
    }

    public function test_the_page_draws_the_charts_and_names_no_people(): void
    {
        $dana = User::factory()->create(['name' => 'Dana Dev']);
        foreach (range(1, 3) as $n) {
            $this->task('2026-09-01 09:00:00', ['2026-10-01 09:00:00' => 'in_progress', '2026-10-03 09:00:00' => 'done'], ['assignee_id' => $dana->id]);
        }
        $this->task('2026-10-01 09:00:00', ['2026-10-05 09:00:00' => 'in_progress'], ['assignee_id' => $dana->id, 'title' => 'Stuck one']);

        $viewer = User::factory()->create(['role' => User::ROLE_VIEWER, 'name' => 'Vera Viewer']);
        $page = $this->actingAs($viewer)->get(route('reports.flow'))->assertOk();

        $page->assertSee('Work in each status')->assertSee('Tasks added and finished')->assertSee('Days to finish each task')->assertSee('Stuck one');
        $this->assertStringNotContainsString('Dana Dev', $page->getContent());
    }

    public function test_the_page_has_a_friendly_empty_state(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))->get(route('reports.flow'))
            ->assertOk()->assertSee('Nothing to report yet.');
    }

    public function test_the_time_tab_is_only_offered_to_leads(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_VIEWER]))->get(route('reports.flow'))
            ->assertDontSee(route('reports.time'));
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))->get(route('reports.flow'))
            ->assertSee(route('reports.time'));
    }

    public function test_the_api_mirrors_the_page(): void
    {
        $this->task('2026-10-13 09:00:00');
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_VIEWER]));

        $this->getJson('/api/v1/reports/flow')->assertOk()
            ->assertJsonCount(12, 'data.weeks')
            ->assertJsonPath('data.weeks.0.start', '2026-08-17')
            ->assertJsonStructure(['data' => ['flow', 'arrivals', 'completions', 'cycle', 'cycle_p50', 'cycle_p85', 'finished', 'in_progress', 'aging']]);

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_DEVELOPER]));
        $this->getJson('/api/v1/reports/flow')->assertForbidden();
    }
}
