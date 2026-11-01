<?php

namespace Tests\Feature\Services;

use App\Models\Project;
use App\Models\Release;
use App\Models\Task;
use App\Models\TasksheetEntry;
use App\Models\Team;
use App\Models\User;
use App\Services\DashboardFilters;
use App\Services\DashboardService;
use App\Services\DeliveryAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Delivery health on the planning dashboard. As with the member analytics,
 * history is built by travelling to each moment and moving the task there.
 * "Today" is Wednesday 2026-11-11 throughout.
 */
class DeliveryAnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    private const TODAY = '2026-11-11 10:00:00';

    private Team $alpha;

    private Team $bravo;

    private Release $alphaRelease;

    private Release $bravoRelease;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-01 09:00:00'));
        $project = Project::create(['name' => 'Checkout', 'color' => '#4f46e5']);
        $this->alpha = Team::create(['name' => 'Alpha', 'color' => '#0891b2']);
        $this->bravo = Team::create(['name' => 'Bravo', 'color' => '#d97706']);
        $this->alphaRelease = $this->release($project, $this->alpha, 'Alpha 1.0');
        $this->bravoRelease = $this->release($project, $this->bravo, 'Bravo 1.0');
    }

    private function release(Project $project, Team $team, string $name): Release
    {
        $release = Release::create([
            'project_id' => $project->id, 'team_id' => $team->id, 'name' => $name,
            'year' => 2026, 'quarter' => 4, 'start_date' => '2026-10-01', 'end_date' => '2026-12-31',
        ]);
        $release->phases()->create(['phase' => 'development', 'position' => 0, 'start_date' => '2026-10-01', 'end_date' => '2026-11-05']);
        $release->phases()->create(['phase' => 'qa', 'position' => 1, 'start_date' => '2026-11-06', 'end_date' => '2026-12-31']);

        return $release;
    }

    private function task(Release $release, string $at, array $attrs = []): Task
    {
        $this->travelTo(Carbon::parse($at));

        return Task::create($attrs + ['release_id' => $release->id, 'title' => 'Task '.uniqid(), 'status' => 'todo']);
    }

    private function move(Task $task, string $status, string $at): Task
    {
        $this->travelTo(Carbon::parse($at));
        $task->update(['status' => $status]);

        return $task;
    }

    private function delivery(User $user, array $query = []): array
    {
        $this->travelTo(Carbon::parse(self::TODAY));
        $filters = DashboardFilters::fromRequest(request()->duplicate($query + ['year' => 2026, 'quarter' => 4]));
        $dashboard = app(DashboardService::class);
        $releases = $dashboard->timelineReleases($filters);

        return app(DeliveryAnalyticsService::class)->forUser($user, $filters, $releases, $dashboard->conflictFlags($releases));
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    public function test_audience_follows_the_role(): void
    {
        $service = app(DeliveryAnalyticsService::class);

        foreach ([User::ROLE_ADMIN => 'org', User::ROLE_CTO => 'org', User::ROLE_TECH_LEAD => 'org',
            User::ROLE_TEAM_LEAD => 'team', User::ROLE_VIEWER => 'viewer'] as $role => $audience) {
            $this->assertSame($audience, $service->audience(User::factory()->make(['role' => $role])), $role);
        }
    }

    public function test_flow_kpis_come_from_status_history(): void
    {
        // In the window (Oct 15 – Nov 11): two done, one of them reworked and late.
        $clean = $this->task($this->alphaRelease, '2026-11-01 09:00:00', ['due_date' => '2026-11-10']);
        $this->move($clean, 'in_progress', '2026-11-02 09:00:00');
        $this->move($clean, 'done', '2026-11-04 09:00:00'); // 2 days

        $reworked = $this->task($this->bravoRelease, '2026-11-01 09:00:00', ['due_date' => '2026-11-03']);
        $this->move($reworked, 'in_progress', '2026-11-03 09:00:00');
        $this->move($reworked, 'recheck', '2026-11-05 09:00:00');
        $this->move($reworked, 'done', '2026-11-07 09:00:00'); // 4 days, late

        // The previous window (Sep 17 – Oct 14): one done.
        $older = $this->task($this->alphaRelease, '2026-10-01 09:00:00');
        $this->move($older, 'done', '2026-10-10 09:00:00');

        $k = $this->delivery($this->admin())['kpis'];

        $this->assertSame(['current' => 2, 'previous' => 1, 'change' => 1], $k['throughput']);
        $this->assertSame(3.0, $k['cycleDays']['current']);
        $this->assertSame(50, $k['onTimeRate']['current']);
        $this->assertSame(50, $k['reworkRate']['current']);
        $this->assertSame(0, $k['reworkRate']['previous']);
        $this->assertSame(50, $k['reworkRate']['change']); // more rework — the UI shows this rise neutrally
    }

    public function test_archived_tasks_are_never_counted(): void
    {
        $filed = $this->task($this->alphaRelease, '2026-11-01 09:00:00', ['due_date' => '2026-11-02']);
        $this->move($filed, 'done', '2026-11-03 09:00:00');
        $this->move($filed, 'archive', '2026-11-04 09:00:00');
        $this->task($this->alphaRelease, '2026-11-01 09:00:00', ['status' => 'archive', 'due_date' => '2026-11-02']);
        $this->task($this->alphaRelease, '2026-11-01 09:00:00', ['status' => 'todo']);

        $d = $this->delivery($this->admin());

        $this->assertSame(0, $d['kpis']['throughput']['current']);
        $this->assertSame(1, $d['backlog']['open']);
        $this->assertSame(0, $d['backlog']['overdue']);
        $this->assertSame([1], array_column($d['teamLoad'], 'open'));
        $alpha = collect($d['releases'])->firstWhere('release.id', $this->alphaRelease->id);
        $this->assertSame([0, 1], [$alpha['done'], $alpha['total']]);
    }

    public function test_backlog_and_aging(): void
    {
        $this->task($this->alphaRelease, '2026-11-10 09:00:00', ['due_date' => '2026-11-09']);           // 1 day old, overdue
        $this->task($this->alphaRelease, '2026-11-01 09:00:00', ['status' => 'in_review', 'due_date' => '2026-11-15']); // 10 days
        $this->task($this->alphaRelease, '2026-10-20 09:00:00', ['status' => 'recheck']);                // 22 days
        $this->task($this->bravoRelease, '2026-10-01 09:00:00', ['assignee_id' => $this->admin()->id]);  // 41 days

        $d = $this->delivery($this->admin());

        $this->assertSame(['open' => 4, 'overdue' => 1, 'dueSoon' => 1, 'inReview' => 1, 'recheck' => 1, 'unassigned' => 3], $d['backlog']);
        $this->assertSame([1, 1, 1, 1], array_column($d['aging'], 'count'));
        $this->assertSame(['Alpha', 'Bravo'], array_column($d['teamLoad'], 'name'));
    }

    public function test_release_health_flags_late_phases_and_lagging_progress(): void
    {
        // Development ended Nov 5 with open development work; 0% done at 45% of time.
        $this->task($this->alphaRelease, '2026-10-02 09:00:00', ['phase' => 'development']);
        // Bravo: all done.
        $done = $this->task($this->bravoRelease, '2026-10-02 09:00:00', ['phase' => 'development']);
        $this->move($done, 'done', '2026-11-01 09:00:00');

        $d = $this->delivery($this->admin());
        $byName = collect($d['releases'])->keyBy(fn ($r) => $r['release']->name);

        $this->assertSame(1, $d['atRiskCount']);
        $this->assertSame('Alpha 1.0', $d['releases'][0]['release']->name); // at-risk first
        $this->assertSame(['Development'], $byName['Alpha 1.0']['latePhases']);
        $this->assertContains('Behind plan', $byName['Alpha 1.0']['flags']);
        $this->assertSame(45, $byName['Alpha 1.0']['elapsedPct']); // 41 of 91 days
        $this->assertSame([], $byName['Bravo 1.0']['flags']);
        $this->assertSame(100, $byName['Bravo 1.0']['pct']);
    }

    public function test_team_lead_sees_only_the_teams_they_lead_unless_they_filter(): void
    {
        $lead = User::factory()->create(['role' => User::ROLE_TEAM_LEAD]);
        $this->alpha->update(['team_lead_id' => $lead->id]);

        $this->task($this->alphaRelease, '2026-11-01 09:00:00');
        $this->task($this->bravoRelease, '2026-11-01 09:00:00');
        $this->task($this->bravoRelease, '2026-11-01 09:00:00');

        $d = $this->delivery($lead);
        $this->assertSame('team', $d['audience']);
        $this->assertSame('Alpha', $d['scopeLabel']);
        $this->assertSame(1, $d['backlog']['open']);
        $this->assertSame(['Alpha 1.0'], collect($d['releases'])->pluck('release.name')->all());

        $filtered = $this->delivery($lead, ['team_id' => $this->bravo->id]);
        $this->assertSame(2, $filtered['backlog']['open']);
    }

    public function test_viewer_gets_no_people_level_detail(): void
    {
        $dev = User::factory()->create(['role' => User::ROLE_DEVELOPER, 'name' => 'Pat Person']);
        $this->task($this->alphaRelease, '2026-11-01 09:00:00', ['assignee_id' => $dev->id, 'title' => 'Named work', 'due_date' => '2026-11-02']);

        $d = $this->delivery(User::factory()->create(['role' => User::ROLE_VIEWER]));
        $this->assertSame('viewer', $d['audience']);
        $this->assertNull($d['people']);
        $this->assertNull($d['attention']);
        $this->assertNull($d['compliance']);
        $this->assertSame(1, $d['backlog']['overdue']); // portfolio numbers still there

        $org = $this->delivery($this->admin());
        $this->assertSame('Pat Person', $org['people'][0]['user']->name);
        $this->assertSame(['Named work'], $org['attention']['overdue']->pluck('title')->all());
    }

    public function test_compliance_rates_and_todays_standup(): void
    {
        $this->travelTo(Carbon::parse('2026-10-01 09:00:00'));
        $dev = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $qa = User::factory()->create(['role' => User::ROLE_QA]);
        $this->alpha->members()->attach([$dev->id, $qa->id]);

        // 10 working days before Wed Nov 11: Oct 29 – Nov 10. 2 members → 20 member-days.
        $full = ['plan' => 'P', 'result' => 'R', 'comment' => 'C', 'tickets' => 'T', 'work_points' => 1, 'ticket_count' => 1, 'ticket_points' => 1];
        TasksheetEntry::create(['team_id' => $this->alpha->id, 'user_id' => $dev->id, 'date' => '2026-11-10'] + $full)
            ->forceFill(['standup_attended' => true])->save();
        TasksheetEntry::create(['team_id' => $this->alpha->id, 'user_id' => $dev->id, 'date' => '2026-11-09', 'plan' => 'P'])
            ->forceFill(['standup_attended' => false])->save();
        TasksheetEntry::create(['team_id' => $this->alpha->id, 'user_id' => $qa->id, 'date' => '2026-11-10', 'leave_type' => 'sick']);
        TasksheetEntry::create(['team_id' => $this->alpha->id, 'user_id' => $qa->id, 'date' => '2026-11-11', 'plan' => 'P'])
            ->forceFill(['standup_attended' => true])->save();

        $c = $this->delivery($this->admin())['compliance'];

        $this->assertSame(2, $c['members']);
        $this->assertSame(1, $c['filled']);
        $this->assertSame(19, $c['expected']); // 20 member-days less one sick day
        $this->assertSame(5, $c['fillRate']);
        $this->assertSame(50, $c['standupRate']);
        $this->assertSame(1, $c['todayAttended']);
        $this->assertSame([2, 1], [$c['teams'][0]['members'], $c['teams'][0]['todayAttended']]);
    }

    public function test_query_count_is_fixed_regardless_of_volume(): void
    {
        $count = function () {
            $user = $this->admin();
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->delivery($user);

            return count(DB::getQueryLog());
        };

        $this->task($this->alphaRelease, '2026-11-01 09:00:00');
        $few = $count();

        foreach (range(1, 6) as $i) {
            $t = $this->task($this->bravoRelease, '2026-11-01 09:00:00', ['assignee_id' => User::factory()->create()->id]);
            $this->move($t, 'done', '2026-11-0'.$i.' 12:00:00');
        }
        $this->assertSame($few, $count());
    }

    public function test_dashboard_renders_per_role_and_matches_the_api(): void
    {
        $lead = User::factory()->create(['role' => User::ROLE_TEAM_LEAD]);
        $this->alpha->update(['team_lead_id' => $lead->id]);
        $dev = User::factory()->create(['role' => User::ROLE_DEVELOPER, 'name' => 'Pat Person']);
        $t = $this->task($this->alphaRelease, '2026-11-01 09:00:00', ['assignee_id' => $dev->id]);
        $this->move($t, 'done', '2026-11-04 09:00:00');
        $this->task($this->alphaRelease, '2026-11-01 09:00:00', ['assignee_id' => $lead->id, 'due_date' => '2026-11-12']);
        $this->travelTo(Carbon::parse(self::TODAY));

        $query = ['year' => 2026, 'quarter' => 4];

        $this->actingAs($this->admin())->get(route('dashboard', $query))->assertOk()
            ->assertSeeInOrder(['id="timeline"', 'Team workload', 'Delivery health', 'Throughput per week', 'Release progress vs plan', 'Workload by person', 'Tasksheet &amp; standups', 'Waiting in review'], false)
            ->assertSee('All teams view');

        $web = $this->actingAs($lead)->get(route('dashboard', $query))->assertOk()
            ->assertSee('Your teams')
            ->assertSee('Team members’ load')
            ->assertSee('Your work')
            ->viewData('delivery');

        $this->actingAs(User::factory()->create(['role' => User::ROLE_VIEWER]))->get(route('dashboard', $query))->assertOk()
            ->assertSee('Portfolio view')
            ->assertSee('Release progress vs plan')
            ->assertDontSee('Workload by person')
            ->assertDontSee('Tasksheet &amp; standups', false)
            ->assertDontSee('Pat Person');

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($lead);
        $api = $this->getJson('/api/v1/dashboard?year=2026&quarter=4')->assertOk()->json('data.delivery');

        $this->assertSame('team', $api['audience']);
        $this->assertSame([$this->alpha->id], $api['team_ids']);
        $this->assertSame($web['kpis']['throughput'], $api['kpis']['throughput']);
        $this->assertEquals($web['kpis']['cycleDays'], $api['kpis']['cycle_days']);
        $this->assertSame($web['backlog']['open'], $api['backlog']['open']);
        $this->assertSame($web['atRiskCount'], $api['at_risk_count']);
        $this->assertSame(array_column($web['weekly'], 'count'), array_column($api['weekly'], 'count'));
        $this->assertSame(collect($web['people'])->pluck('user.id')->all(), collect($api['people'])->pluck('user.id')->all());
        $this->assertSame(['open' => 1, 'overdue' => 0, 'due_soon' => 1], $api['mine']);

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_VIEWER]));
        $viewer = $this->getJson('/api/v1/dashboard?year=2026&quarter=4')->assertOk()->json('data.delivery');
        $this->assertNull($viewer['people']);
        $this->assertNull($viewer['attention']);
        $this->assertNull($viewer['compliance']);
    }
}
