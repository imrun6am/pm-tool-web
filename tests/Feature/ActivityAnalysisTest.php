<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Concerns\RecordsActivity;
use App\Models\PerformanceScore;
use App\Models\Project;
use App\Models\Release;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Services\ActivityInsights;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The activity analysis page and its API twin. History is real audit rows:
 * each change is made after travelling to its moment. "Today" is Wednesday
 * 2026-11-11 10:00 throughout.
 */
class ActivityAnalysisTest extends TestCase
{
    use RefreshDatabase;

    private const TODAY = '2026-11-11 10:00:00';

    private User $admin;

    private Team $alpha;

    private Team $bravo;

    private Release $alphaRelease;

    private Release $bravoRelease;

    protected function setUp(): void
    {
        parent::setUp();

        // Users are recorded too, so every fixture is made before the windows.
        $this->travelTo(Carbon::parse('2026-09-01 09:00:00'));
        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'name' => 'Ada Admin']);
        $this->actingAs($this->admin);
        $project = Project::create(['name' => 'Checkout', 'color' => '#4f46e5']);
        $this->alpha = Team::create(['name' => 'Alpha', 'color' => '#0891b2']);
        $this->bravo = Team::create(['name' => 'Bravo', 'color' => '#d97706']);
        $make = fn (Team $t, string $name) => Release::create([
            'project_id' => $project->id, 'team_id' => $t->id, 'name' => $name,
            'year' => 2026, 'quarter' => 4, 'start_date' => '2026-10-01', 'end_date' => '2026-12-31',
        ]);
        $this->alphaRelease = $make($this->alpha, 'Alpha 1.0');
        $this->bravoRelease = $make($this->bravo, 'Bravo 1.0');
        auth()->logout();
    }

    private function user(string $role): User
    {
        $this->travelTo(Carbon::parse('2026-09-01 09:00:00'));

        return User::factory()->create(['role' => $role]);
    }

    /** Make a change as a user at a moment, letting RecordsActivity log it. */
    private function as(User $user, string $at, \Closure $change): mixed
    {
        $this->travelTo(Carbon::parse($at));
        $this->actingAs($user);
        $result = $change();
        auth()->logout();

        return $result;
    }

    private function analysis(array $input = []): array
    {
        $this->travelTo(Carbon::parse(self::TODAY));

        return app(ActivityInsights::class)->analysis(ActivityInsights::filters($input));
    }

    public function test_only_full_access_roles_reach_the_page_and_the_api(): void
    {
        foreach ([User::ROLE_DEVELOPER, User::ROLE_QA] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get(route('activity.index'))->assertForbidden();
            Sanctum::actingAs($user);
            $this->getJson('/api/v1/activities/analysis')->assertForbidden();
            $this->app['auth']->forgetGuards();
        }

        foreach ([User::ROLE_ADMIN, User::ROLE_TEAM_LEAD, User::ROLE_VIEWER] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get(route('activity.index'))
                ->assertOk()->assertSee('Weekly rhythm')->assertSee('Activity log');
        }
    }

    public function test_volume_compares_with_the_previous_window(): void
    {
        $dev = $this->user(User::ROLE_DEVELOPER);
        // Previous 30-day window (Sep 13 – Oct 12): one change.
        $this->as($dev, '2026-10-01 12:00:00', fn () => $this->alphaRelease->update(['name' => 'Alpha 1.0.1']));
        // This window (Oct 13 – Nov 11): three changes by two people, one deletion.
        $task = $this->as($dev, '2026-11-02 12:00:00', fn () => Task::create(['title' => 'A', 'release_id' => $this->alphaRelease->id]));
        $this->as($this->admin, '2026-11-03 12:00:00', fn () => $task->update(['title' => 'B']));
        $this->as($this->admin, '2026-11-04 12:00:00', fn () => $task->delete());

        $a = $this->analysis();

        $this->assertSame(['current' => 3, 'previous' => 1, 'change' => 2], $a['kpis']['events']);
        $this->assertSame(2, $a['kpis']['people']['current']);
        $this->assertSame(['current' => 1, 'previous' => 0, 'change' => 1], $a['kpis']['deletions']);
        $this->assertSame(['created' => 1, 'updated' => 1, 'deleted' => 1], $a['byEvent']);
        $this->assertCount(30, $a['volume']);
        $this->assertSame(3, array_sum(array_column($a['volume'], 'count')));
        $this->assertSame([['type' => 'task', 'label' => 'Task', 'count' => 3]], $a['byType']);
        $this->assertSame('Ada Admin', $a['people'][0]['user']->name);
        $this->assertSame([2, 0, 1, 1], [$a['people'][0]['total'], $a['people'][0]['created'], $a['people'][0]['updated'], $a['people'][0]['deleted']]);
        $this->assertSame(['Task'], $a['deletions']->map(fn (Activity $d) => class_basename($d->subject_type))->all());

        // A 90-day window buckets by week.
        $this->assertCount(13, $this->analysis(['days' => 90])['volume']);
    }

    public function test_team_scope_and_breakdowns_follow_the_release(): void
    {
        $this->as($this->admin, '2026-11-02 12:00:00', function () {
            Task::create(['title' => 'Alpha work', 'release_id' => $this->alphaRelease->id]);
            Task::create(['title' => 'Bravo work', 'release_id' => $this->bravoRelease->id]);
            Task::create(['title' => 'More bravo', 'release_id' => $this->bravoRelease->id]);
        });

        $all = $this->analysis();
        $this->assertSame(['Bravo', 'Alpha'], array_column($all['byTeam'], 'name'));
        $this->assertSame(['Bravo 1.0', 'Alpha 1.0'], array_column($all['byRelease'], 'name'));

        $alpha = $this->analysis(['team_id' => $this->alpha->id]);
        $this->assertSame(1, $alpha['kpis']['events']['current']);
        $this->assertSame(['Alpha'], array_column($alpha['byTeam'], 'name'));
    }

    public function test_workflow_moves_dwell_times_and_rework(): void
    {
        $dev = $this->user(User::ROLE_DEVELOPER);
        $task = $this->as($dev, '2026-11-02 09:00:00', fn () => Task::create(['title' => 'Flow', 'release_id' => $this->alphaRelease->id]));
        $this->as($dev, '2026-11-03 09:00:00', fn () => $task->update(['status' => 'in_progress']));  // 24h in To Do
        $this->as($dev, '2026-11-03 21:00:00', fn () => $task->update(['status' => 'in_review']));    // 12h in progress
        $this->as($dev, '2026-11-04 09:00:00', fn () => $task->update(['status' => 'recheck']));      // 12h in review
        $this->as($dev, '2026-11-04 15:00:00', fn () => $task->update(['status' => 'in_review']));    // 6h in recheck
        $this->as($dev, '2026-11-05 15:00:00', fn () => $task->update(['status' => 'done']));         // 24h in review

        $f = $this->analysis()['flow'];

        $this->assertSame(5, $f['moves']);
        $this->assertSame(['started' => 1, 'review' => 2, 'rework' => 1, 'done' => 1, 'reopened' => 0], $f['milestones']);
        $this->assertSame(50, $f['reworkRate']);
        $this->assertSame('In Progress → In Review', collect($f['transitions'])->firstWhere('to', 'in_review')['label']);
        $dwell = collect($f['dwell'])->keyBy('status');
        $this->assertSame(24.0, $dwell['todo']['avgHours']);
        $this->assertSame(12.0, $dwell['in_progress']['avgHours']);
        $this->assertSame(18.0, $dwell['in_review']['avgHours']); // (12 + 24) / 2
        $this->assertSame(6.0, $dwell['recheck']['avgHours']);
    }

    public function test_rhythm_finds_the_busiest_slot(): void
    {
        $this->as($this->admin, '2026-11-10 14:20:00', fn () => $this->alphaRelease->update(['name' => 'One']));   // Tue 14h
        $this->as($this->admin, '2026-11-10 14:40:00', fn () => $this->alphaRelease->update(['name' => 'Two']));   // Tue 14h
        $this->as($this->admin, '2026-11-09 09:00:00', fn () => $this->alphaRelease->update(['name' => 'Three'])); // Mon 9h

        $r = $this->analysis()['rhythm'];

        $this->assertSame(['day' => 'Tue', 'hour' => 14, 'count' => 2], $r['peak']);
        $this->assertSame('Mon', $r['grid'][0]['day']);
        $this->assertSame(1, $r['grid'][0]['hours'][9]);
        $this->assertSame(2, $r['max']);
    }

    public function test_drill_down_narrows_the_feed_but_not_the_charts(): void
    {
        $this->as($this->admin, '2026-11-02 12:00:00', fn () => Task::create(['title' => 'Kept', 'release_id' => $this->alphaRelease->id]));
        $this->as($this->admin, '2026-11-05 12:00:00', fn () => $this->bravoRelease->update(['name' => 'Bravo renamed']));
        $this->travelTo(Carbon::parse(self::TODAY));

        $response = $this->actingAs($this->admin)
            ->get(route('activity.index', ['subject_type' => 'task', 'from' => '2026-11-02', 'to' => '2026-11-02']))
            ->assertOk()
            ->assertSee('Clear drill-down');

        $this->assertSame(1, $response->viewData('activities')->total());
        $this->assertSame(2, $response->viewData('analysis')['kpis']['events']['current']); // charts keep the whole scope

        // Every chart row links into the filtered feed.
        $response->assertSee('subject_type=release', false)->assertSee('#feed', false);
    }

    public function test_an_empty_window_renders_a_friendly_state(): void
    {
        $this->travelTo(Carbon::parse(self::TODAY));

        $this->actingAs($this->admin)->get(route('activity.index', ['days' => 7]))
            ->assertOk()
            ->assertSee('No activity in this window.');

        $a = $this->analysis(['days' => 7]);
        $this->assertSame(0, $a['kpis']['events']['current']);
        $this->assertNull($a['flow']['reworkRate']);
        $this->assertNull($a['rhythm']['peak']);
    }

    public function test_api_matches_the_page_and_validates_the_period(): void
    {
        $this->as($this->admin, '2026-11-02 12:00:00', fn () => Task::create(['title' => 'X', 'release_id' => $this->alphaRelease->id]));
        $this->travelTo(Carbon::parse(self::TODAY));

        $web = $this->actingAs($this->admin)->get(route('activity.index', ['days' => 7]))->viewData('analysis');

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($this->admin);
        $api = $this->getJson('/api/v1/activities/analysis?days=7')->assertOk()->json('data');

        $this->assertSame($web['kpis']['events'], $api['kpis']['events']);
        $this->assertSame($web['byEvent'], $api['by_event']);
        $this->assertSame(array_column($web['volume'], 'count'), array_column($api['volume'], 'count'));
        $this->assertSame($web['flow']['milestones'], $api['flow']['milestones']);
        $this->assertSame($web['rhythm']['grid'], $api['rhythm']['grid']);
        $this->assertSame(7, $api['window']['days']);

        $this->getJson('/api/v1/activities/analysis?days=365')->assertStatus(422)->assertJsonValidationErrors('days');
    }

    public function test_performance_scores_never_reach_the_feed(): void
    {
        $this->assertFalse(in_array(RecordsActivity::class, class_uses_recursive(PerformanceScore::class), true));
    }
}
