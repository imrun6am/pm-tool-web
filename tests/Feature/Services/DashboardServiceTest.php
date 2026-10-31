<?php

namespace Tests\Feature\Services;

use App\Models\Project;
use App\Models\Release;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Services\DashboardFilters;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Direct tests of the timeline geometry, grouping, conflict flags, and analytics
 * now that they are shared by the Blade dashboard and the API.
 */
class DashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    private DashboardService $service;

    private Project $project;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(DashboardService::class);
        $this->project = Project::create(['name' => 'Checkout', 'color' => '#4f46e5']);
        $this->team = Team::create(['name' => 'Alpha', 'color' => '#0891b2']);
    }

    private function release(array $overrides = []): Release
    {
        $release = Release::create($overrides + [
            'project_id' => $this->project->id,
            'team_id' => $this->team->id,
            'name' => 'Release '.uniqid(),
            'year' => 2026, 'quarter' => 3,
            'start_date' => '2026-07-01', 'end_date' => '2026-07-31',
        ]);

        $position = 0;
        foreach (array_keys(Release::PHASES) as $phase) {
            $release->phases()->create([
                'phase' => $phase,
                'position' => $position++,
                'start_date' => $release->start_date,
                'end_date' => $release->end_date,
            ]);
        }

        return $release->load(['project', 'team', 'phases']);
    }

    private function filters(array $overrides = []): DashboardFilters
    {
        return new DashboardFilters(
            year: $overrides['year'] ?? 2026,
            // array_key_exists, not ??: an explicit null quarter is the "all
            // quarters" choice and must not fall back to the default.
            quarter: array_key_exists('quarter', $overrides) ? $overrides['quarter'] : 3,
            projectId: $overrides['projectId'] ?? null,
            teamId: $overrides['teamId'] ?? null,
            groupBy: $overrides['groupBy'] ?? 'team',
        );
    }

    // -- The axis ---------------------------------------------------------

    public function test_a_quarter_spans_its_three_months(): void
    {
        $filters = $this->filters(['quarter' => 3]);

        $this->assertSame('2026-07-01', $filters->rangeStart->toDateString());
        $this->assertSame('2026-09-30', $filters->rangeEnd->toDateString());
        $this->assertSame('Q3 2026', $filters->periodLabel());
    }

    public function test_no_quarter_spans_the_whole_year(): void
    {
        $filters = $this->filters(['quarter' => null]);

        $this->assertSame('2026-01-01', $filters->rangeStart->toDateString());
        $this->assertSame('2026-12-31', $filters->rangeEnd->toDateString());
        $this->assertSame('2026', $filters->periodLabel());
    }

    public function test_month_columns_span_the_axis(): void
    {
        $this->assertCount(3, $this->service->months($this->filters(['quarter' => 3])));
        $this->assertCount(12, $this->service->months($this->filters(['quarter' => null])));
    }

    // -- Geometry ---------------------------------------------------------

    public function test_a_release_covering_the_whole_axis_fills_the_bar(): void
    {
        $this->release(['start_date' => '2026-07-01', 'end_date' => '2026-09-30']);
        $filters = $this->filters();

        $groups = $this->service->groups($this->service->timelineReleases($filters), $filters, []);
        $bar = $groups[0]['bars'][0];

        $this->assertSame(0.0, $bar['offset']);
        // Not exactly 100: the axis ends at end-of-day while a release's dates are
        // midnight, so a full-span bar lands a fraction under. Pre-existing
        // Timeline behaviour, asserted loosely rather than pinned to the artefact.
        $this->assertGreaterThan(98.0, $bar['width']);
        $this->assertLessThanOrEqual(100.0, $bar['width']);
    }

    public function test_a_bar_never_extends_past_the_right_edge(): void
    {
        // Runs beyond the quarter — must be clipped, not overflow.
        $this->release(['start_date' => '2026-09-01', 'end_date' => '2026-12-31']);
        $filters = $this->filters();

        $groups = $this->service->groups($this->service->timelineReleases($filters), $filters, []);
        $bar = $groups[0]['bars'][0];

        $this->assertLessThanOrEqual(100.0, $bar['offset'] + $bar['width']);
    }

    public function test_a_release_outside_the_axis_is_absent(): void
    {
        $this->release(['start_date' => '2026-01-01', 'end_date' => '2026-02-01']);
        $filters = $this->filters(['quarter' => 3]);

        $this->assertSame([], $this->service->groups(
            $this->service->timelineReleases($filters), $filters, []
        ));
    }

    public function test_each_bar_carries_its_four_phase_segments(): void
    {
        $this->release();
        $filters = $this->filters();

        $groups = $this->service->groups($this->service->timelineReleases($filters), $filters, []);
        $phases = $groups[0]['bars'][0]['phases'];

        $this->assertCount(4, $phases);
        $this->assertSame(array_keys(Release::PHASES), array_column($phases, 'phase'));
        // Colors travel with the phase so every client draws one palette.
        $this->assertSame(Release::PHASE_COLORS['development'], $phases[0]['color']);
    }

    public function test_a_bar_carries_the_release_model_not_an_id(): void
    {
        $release = $this->release();
        $filters = $this->filters();

        $groups = $this->service->groups($this->service->timelineReleases($filters), $filters, []);

        // Blade reads `$bar['release']->name`; the API maps the same model
        // through a resource. Returning an id would force a re-query.
        $this->assertInstanceOf(Release::class, $groups[0]['bars'][0]['release']);
        $this->assertSame($release->id, $groups[0]['bars'][0]['release']->id);
    }

    // -- Grouping ---------------------------------------------------------

    public function test_grouping_by_team_versus_project(): void
    {
        $otherTeam = Team::create(['name' => 'Bravo', 'color' => '#111111']);
        $this->release();
        $this->release(['team_id' => $otherTeam->id]);

        $byTeam = $this->filters(['groupBy' => 'team']);
        $this->assertCount(2, $this->service->groups(
            $this->service->timelineReleases($byTeam), $byTeam, []
        ));

        // Both releases share one project, so grouping by project collapses them.
        $byProject = $this->filters(['groupBy' => 'project']);
        $this->assertCount(1, $this->service->groups(
            $this->service->timelineReleases($byProject), $byProject, []
        ));
    }

    public function test_groups_are_alphabetical(): void
    {
        $zulu = Team::create(['name' => 'Zulu', 'color' => '#111111']);
        $this->release(['team_id' => $zulu->id]);
        $this->release(); // Alpha

        $filters = $this->filters();
        $groups = $this->service->groups($this->service->timelineReleases($filters), $filters, []);

        $this->assertSame(['Alpha', 'Zulu'], array_column($groups, 'label'));
    }

    // -- Conflicts --------------------------------------------------------

    public function test_overlapping_same_team_releases_are_flagged(): void
    {
        $a = $this->release(['start_date' => '2026-07-01', 'end_date' => '2026-07-20']);
        $b = $this->release(['start_date' => '2026-07-15', 'end_date' => '2026-07-31']);

        $flags = $this->service->conflictFlags($this->service->timelineReleases($this->filters()));

        $this->assertTrue($flags[$a->id]);
        $this->assertTrue($flags[$b->id]);
    }

    public function test_a_conflict_is_flagged_even_when_its_partner_is_off_screen(): void
    {
        // In view (Q3), overlapping a release that sits outside the filtered set.
        $inView = $this->release(['start_date' => '2026-09-20', 'end_date' => '2026-09-30']);
        $this->release(['start_date' => '2026-09-25', 'end_date' => '2026-10-15', 'quarter' => 4]);

        $filters = $this->filters(['quarter' => 3]);
        $flags = $this->service->conflictFlags($this->service->timelineReleases($filters));

        $this->assertTrue(
            $flags[$inView->id] ?? false,
            'Conflicts are computed across the team’s whole schedule, not just the visible slice.'
        );
    }

    public function test_a_completed_release_does_not_book_the_team(): void
    {
        $ongoing = $this->release(['start_date' => '2026-07-01', 'end_date' => '2026-07-20']);
        $this->release([
            'start_date' => '2026-07-15', 'end_date' => '2026-07-31', 'completed_at' => now(),
        ]);

        $flags = $this->service->conflictFlags($this->service->timelineReleases($this->filters()));

        $this->assertArrayNotHasKey($ongoing->id, array_filter($flags));
    }

    public function test_completed_releases_drop_off_the_timeline(): void
    {
        $this->release(['completed_at' => now()]);

        $this->assertCount(0, $this->service->timelineReleases($this->filters()));
    }

    // -- Analytics --------------------------------------------------------

    public function test_analytics_counts_active_releases_and_the_task_mix(): void
    {
        $release = $this->release();
        Task::create(['release_id' => $release->id, 'title' => 'A', 'status' => 'todo']);
        Task::create(['release_id' => $release->id, 'title' => 'B', 'status' => 'done']);
        Task::create(['release_id' => $release->id, 'title' => 'C', 'status' => 'archive']);

        $filters = $this->filters();
        $releases = $this->service->timelineReleases($filters);
        $analytics = $this->service->analytics($filters, $releases, []);

        $this->assertSame(1, $analytics['active']);
        $this->assertSame(3, $analytics['taskTotal']);
        $this->assertSame(1, $analytics['statusCounts']['todo']);
        // done + archive both count as finished.
        $this->assertSame(67, $analytics['donePct']);
    }

    public function test_done_percentage_is_zero_rather_than_dividing_by_nothing(): void
    {
        $this->release();
        $filters = $this->filters();

        $analytics = $this->service->analytics($filters, $this->service->timelineReleases($filters), []);

        $this->assertSame(0, $analytics['taskTotal']);
        $this->assertSame(0, $analytics['donePct']);
    }

    public function test_analytics_reports_monthly_load_across_the_axis(): void
    {
        $this->release(['start_date' => '2026-07-01', 'end_date' => '2026-07-31']);
        $filters = $this->filters();

        $analytics = $this->service->analytics($filters, $this->service->timelineReleases($filters), []);

        $this->assertCount(3, $analytics['monthly']);
        $this->assertSame(1, $analytics['monthly'][0]['count']); // July
        $this->assertSame(0, $analytics['monthly'][1]['count']); // August
        $this->assertSame(1, $analytics['monthlyMax']);
    }

    public function test_analytics_counts_double_booked_teams(): void
    {
        $this->release(['start_date' => '2026-07-01', 'end_date' => '2026-07-20']);
        $this->release(['start_date' => '2026-07-15', 'end_date' => '2026-07-31']);

        $filters = $this->filters();
        $releases = $this->service->timelineReleases($filters);
        $conflicts = $this->service->conflictFlags($releases);

        $analytics = $this->service->analytics($filters, $releases, $conflicts);

        $this->assertSame(2, $analytics['conflictCount']);
        $this->assertSame(1, $analytics['teamsDoubleBooked']);
    }

    public function test_available_years_always_span_the_selection_and_today(): void
    {
        $this->release(['year' => 2026]);

        $years = $this->service->availableYears(2030);

        $this->assertContains(2026, $years);
        $this->assertContains(2030, $years);
        $this->assertContains((int) now()->year, $years);
    }

    // -- Member dashboard -------------------------------------------------

    public function test_member_snapshot_lists_only_the_users_open_tasks(): void
    {
        $release = $this->release();
        $dev = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $other = User::factory()->create(['role' => User::ROLE_DEVELOPER]);

        Task::create(['release_id' => $release->id, 'title' => 'Mine', 'status' => 'todo', 'assignee_id' => $dev->id]);
        Task::create(['release_id' => $release->id, 'title' => 'Done', 'status' => 'done', 'assignee_id' => $dev->id]);
        Task::create(['release_id' => $release->id, 'title' => 'Theirs', 'status' => 'todo', 'assignee_id' => $other->id]);

        $snapshot = $this->service->memberSnapshot($dev);

        $this->assertCount(1, $snapshot['tasks']);
        $this->assertSame('Mine', $snapshot['tasks']->first()->title);
    }

    public function test_member_snapshot_counts_overdue_tasks(): void
    {
        $release = $this->release();
        $dev = User::factory()->create(['role' => User::ROLE_DEVELOPER]);

        Task::create([
            'release_id' => $release->id, 'title' => 'Late', 'status' => 'todo',
            'assignee_id' => $dev->id, 'due_date' => today()->subDay(),
        ]);
        Task::create([
            'release_id' => $release->id, 'title' => 'Soon', 'status' => 'todo',
            'assignee_id' => $dev->id, 'due_date' => today()->addWeek(),
        ]);

        $this->assertSame(1, $this->service->memberSnapshot($dev)['overdueCount']);
    }
}
