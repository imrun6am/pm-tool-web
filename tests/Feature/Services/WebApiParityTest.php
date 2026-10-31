<?php

namespace Tests\Feature\Services;

use App\Models\Project;
use App\Models\Release;
use App\Models\Task;
use App\Models\TasksheetEntry;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Guards the `domain-services` requirement that a behaviour reachable from both
 * the web app and the API resolves to one code path.
 *
 * These tests drive the *same records* through both surfaces and assert the
 * numbers agree. A future edit that reintroduces a private copy of a query in
 * one controller would drift, and drift is what fails here — which is the whole
 * point of the extraction.
 */
class WebApiParityTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

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
                'phase' => $phase, 'position' => $position++,
                'start_date' => $release->start_date, 'end_date' => $release->end_date,
            ]);
        }

        return $release;
    }

    public function test_the_dashboard_timeline_is_identical_on_both_surfaces(): void
    {
        $this->release(['start_date' => '2026-07-01', 'end_date' => '2026-07-20']);
        $this->release(['start_date' => '2026-07-15', 'end_date' => '2026-08-10']); // overlaps

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $query = ['year' => 2026, 'quarter' => 3];

        // Web: read the view data the Blade dashboard was handed.
        $web = $this->actingAs($admin)->get(route('dashboard', $query))->assertOk();
        $webGroups = $web->viewData('groups');
        $webAnalytics = $web->viewData('analytics');

        $this->app['auth']->forgetGuards();

        // API: the same filters through the JSON surface.
        Sanctum::actingAs($admin);
        $api = $this->getJson('/api/v1/dashboard?year=2026&quarter=3')->assertOk();

        // Same bars, in the same order, with the same geometry.
        $webBars = collect($webGroups)->flatMap->bars;
        $apiBars = collect($api->json('data.groups'))->flatMap(fn ($g) => $g['bars']);

        $this->assertCount($webBars->count(), $apiBars);

        // assertEquals, not assertSame: JSON has no float/int distinction, so a
        // PHP 0.0 comes back as 0 through the API. The claim under test is that
        // the *values* agree, not that they survive a round trip typed.
        $this->assertEquals(
            $webBars->map(fn ($b) => [
                'id' => $b['release']->id,
                'offset' => $b['offset'],
                'width' => $b['width'],
                'conflict' => $b['conflict'],
            ])->all(),
            $apiBars->map(fn ($b) => [
                'id' => $b['release']['id'],
                'offset' => $b['offset'],
                'width' => $b['width'],
                'conflict' => $b['conflict'],
            ])->all(),
            'The timeline geometry must come from one shared computation.'
        );

        // Same headline analytics, under each surface's own field names.
        $apiAnalytics = $api->json('data.analytics');

        $this->assertSame($webAnalytics['active'], $apiAnalytics['active']);
        $this->assertSame($webAnalytics['conflictCount'], $apiAnalytics['conflict_count']);
        $this->assertSame($webAnalytics['teamsDoubleBooked'], $apiAnalytics['teams_double_booked']);
        $this->assertSame($webAnalytics['upcoming'], $apiAnalytics['upcoming']);
        $this->assertSame($webAnalytics['donePct'], $apiAnalytics['done_pct']);
        $this->assertSame($webAnalytics['taskTotal'], $apiAnalytics['task_total']);
        $this->assertSame($webAnalytics['statusCounts'], $apiAnalytics['status_counts']);
    }

    public function test_the_phase_segments_agree_on_both_surfaces(): void
    {
        $this->release();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $web = $this->actingAs($admin)->get(route('dashboard', ['year' => 2026, 'quarter' => 3]))->assertOk();
        $webPhases = collect($web->viewData('groups'))->flatMap->bars->first()['phases'];

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($admin);
        $apiPhases = $this->getJson('/api/v1/dashboard?year=2026&quarter=3')
            ->assertOk()->json('data.groups.0.bars.0.phases');

        $this->assertEquals(
            collect($webPhases)->map(fn ($p) => [
                'phase' => $p['phase'],
                'offset' => $p['offset'],
                'width' => $p['width'],
                'color' => $p['color'],
                'start' => $p['start']->toDateString(),
            ])->all(),
            collect($apiPhases)->map(fn ($p) => [
                'phase' => $p['phase'],
                'offset' => $p['offset'],
                'width' => $p['width'],
                'color' => $p['color'],
                'start' => $p['start_date'],
            ])->all()
        );
    }

    public function test_the_tasksheet_trend_is_identical_on_both_surfaces(): void
    {
        $dev = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $this->team->members()->attach($dev);

        TasksheetEntry::create([
            'team_id' => $this->team->id, 'user_id' => $dev->id,
            'date' => today()->toDateString(), 'work_points' => 9,
        ]);

        $lead = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $web = $this->actingAs($lead)
            ->get(route('tasksheet.index', ['team' => $this->team->id]))
            ->assertOk();
        $webTrend = $web->viewData('trend');

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($lead);
        $apiTrend = $this->getJson("/api/v1/tasksheet?team_id={$this->team->id}")
            ->assertOk()->json('data.trend');

        $this->assertSame(
            collect($webTrend)->map(fn ($d) => [$d['date'], $d['wp'], $d['current']])->all(),
            collect($apiTrend)->map(fn ($d) => [$d['date'], $d['work_points'], $d['is_current']])->all()
        );

        // And the bug this refactor fixed: the viewed day is present on both.
        $this->assertSame(9, end($webTrend)['wp']);
        $this->assertSame(9, $apiTrend[count($apiTrend) - 1]['work_points']);
    }

    public function test_the_board_columns_agree_on_both_surfaces(): void
    {
        $release = $this->release();
        Task::create(['release_id' => $release->id, 'title' => 'A', 'status' => 'todo', 'position' => 0]);
        Task::create(['release_id' => $release->id, 'title' => 'B', 'status' => 'recheck', 'position' => 1]);

        $user = User::factory()->create(['role' => User::ROLE_DEVELOPER]);

        $web = $this->actingAs($user)->get(route('board.index'))->assertOk();
        $webColumns = $web->viewData('columns');

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($user);
        $apiColumns = $this->getJson('/api/v1/board')->assertOk()->json('data.columns');

        $this->assertSame(
            collect($webColumns)->map->count()->all(),
            collect($apiColumns)->mapWithKeys(fn ($c) => [$c['status'] => $c['count']])->all()
        );
    }

    public function test_the_activity_roll_ups_agree_on_both_surfaces(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin);
        $release = $this->release();
        $release->update(['name' => 'Renamed']);

        $web = $this->actingAs($admin)->get(route('activity.index'))->assertOk();
        $webStats = $web->viewData('stats');

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($admin);
        $apiTotals = $this->getJson('/api/v1/activities/stats')->assertOk()->json('data.totals');

        $this->assertSame($webStats, $apiTotals);
    }

    public function test_the_user_directory_stats_agree_on_both_surfaces(): void
    {
        User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        User::factory()->create(['role' => User::ROLE_QA, 'deactivated_at' => now()]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $web = $this->actingAs($admin)->get(route('users.index'))->assertOk();
        $webStats = $web->viewData('stats');

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($admin);
        $api = $this->getJson('/api/v1/users/stats')->assertOk()->json('data');

        $this->assertSame($webStats['total'], $api['total']);
        $this->assertSame($webStats['active'], $api['active']);
        $this->assertSame($webStats['inactive'], $api['inactive']);
        $this->assertSame($webStats['engineers'], $api['engineers']);
        $this->assertSame($webStats['roleDistribution'], $api['role_distribution']);
    }
}
