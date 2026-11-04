<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Release;
use App\Models\ReleaseOffDay;
use App\Models\Task;
use App\Models\TasksheetEntry;
use App\Models\TaskTimeLog;
use App\Models\Team;
use App\Models\User;
use App\Services\CapacityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Available time per week against the estimated work due that week. "Today" is
 * a Monday, so a full week has five 8-hour working days.
 */
class CapacityTest extends TestCase
{
    use RefreshDatabase;

    private const FULL_WEEK = 2400;

    private Team $team;

    private User $dev;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-02 09:00:00'); // a Monday
        config(['planning.weekend_days' => ['saturday', 'sunday'], 'planning.hours_per_day' => 8]);
        $this->team = Team::create(['name' => 'Core', 'color' => '#0891b2']);
        $this->dev = User::factory()->create(['role' => User::ROLE_DEVELOPER, 'name' => 'Dana Dev']);
        $this->team->members()->attach($this->dev);
    }

    private function task(array $attrs = []): Task
    {
        return Task::create($attrs + ['title' => 'Work', 'status' => 'todo', 'position' => 0, 'assignee_id' => $this->dev->id]);
    }

    /** @return array<string, mixed> */
    private function row(?User $person = null): ?array
    {
        return collect(app(CapacityService::class)->forTeam($this->team)['people'])
            ->firstWhere('user.id', ($person ?? $this->dev)->id);
    }

    public function test_it_shows_six_weeks_from_this_one_and_a_full_week_of_time(): void
    {
        $result = app(CapacityService::class)->forTeam($this->team);

        $this->assertCount(6, $result['weeks']);
        $this->assertTrue($result['weeks'][0]['current']);
        $this->assertSame('2026-11-02', $result['weeks'][0]['start']->toDateString());
        $this->assertSame(self::FULL_WEEK, $this->row()['cells'][0]['available_minutes']);
    }

    public function test_this_week_counts_only_the_days_left(): void
    {
        $this->travelTo('2026-11-05 09:00:00'); // Thursday

        $cells = $this->row()['cells'];

        $this->assertSame(960, $cells[0]['available_minutes']);
        $this->assertSame(self::FULL_WEEK, $cells[1]['available_minutes']);
    }

    public function test_the_working_week_comes_from_config(): void
    {
        config(['planning.weekend_days' => ['friday', 'saturday'], 'planning.hours_per_day' => 6]);

        $this->assertSame(5 * 6 * 60, $this->row()['cells'][0]['available_minutes']);
    }

    public function test_leave_and_off_days_reduce_available_time(): void
    {
        TasksheetEntry::create(['team_id' => $this->team->id, 'user_id' => $this->dev->id, 'date' => '2026-11-03', 'leave_type' => 'sick']);
        TasksheetEntry::create(['team_id' => $this->team->id, 'user_id' => $this->dev->id, 'date' => '2026-11-04', 'leave_type' => 'half_day']);
        $project = Project::create(['name' => 'P', 'color' => '#4f46e5']);
        $release = Release::create(['project_id' => $project->id, 'team_id' => $this->team->id, 'name' => 'R', 'year' => 2026, 'quarter' => 4, 'start_date' => '2026-11-01', 'end_date' => '2026-12-31']);
        ReleaseOffDay::create(['release_id' => $release->id, 'date' => '2026-11-11', 'reason' => 'Holiday']);

        $cells = $this->row()['cells'];

        $this->assertSame(self::FULL_WEEK - 480 - 240, $cells[0]['available_minutes']);
        $this->assertSame(self::FULL_WEEK - 480, $cells[1]['available_minutes']);
    }

    public function test_someone_elses_leave_does_not_count(): void
    {
        $other = User::factory()->create();
        $this->team->members()->attach($other);
        TasksheetEntry::create(['team_id' => $this->team->id, 'user_id' => $other->id, 'date' => '2026-11-03', 'leave_type' => 'sick']);

        $this->assertSame(self::FULL_WEEK, $this->row()['cells'][0]['available_minutes']);
        $this->assertSame(self::FULL_WEEK - 480, $this->row($other)['cells'][0]['available_minutes']);
    }

    public function test_load_is_whats_left_of_each_estimate_in_the_week_it_is_due(): void
    {
        $this->task(['estimate_minutes' => 300, 'due_date' => '2026-11-04']);
        $part = $this->task(['estimate_minutes' => 120, 'due_date' => '2026-11-04']);
        TaskTimeLog::create(['task_id' => $part->id, 'user_id' => $this->dev->id, 'logged_on' => '2026-11-02', 'minutes' => 60]);
        $over = $this->task(['estimate_minutes' => 30, 'due_date' => '2026-11-12']);
        TaskTimeLog::create(['task_id' => $over->id, 'user_id' => $this->dev->id, 'logged_on' => '2026-11-02', 'minutes' => 90]);

        $cells = $this->row()['cells'];

        $this->assertSame(360, $cells[0]['load_minutes']);
        $this->assertSame(2, $cells[0]['tasks']);
        $this->assertSame(0, $cells[1]['load_minutes'], 'Time beyond the estimate leaves nothing, never a negative.');
    }

    public function test_overdue_counts_this_week_and_undated_or_far_off_counts_later(): void
    {
        $this->task(['estimate_minutes' => 60, 'due_date' => '2026-10-01']);
        $this->task(['estimate_minutes' => 120]);
        $this->task(['estimate_minutes' => 180, 'due_date' => '2027-06-01']);

        $row = $this->row();

        $this->assertSame(60, $row['cells'][0]['load_minutes']);
        $this->assertSame(300, $row['later']['load_minutes']);
        $this->assertSame(2, $row['later']['tasks']);
    }

    public function test_tasks_with_no_estimate_are_reported_not_counted(): void
    {
        $this->task(['due_date' => '2026-11-04']);
        $this->task();

        $row = $this->row();

        $this->assertSame(0, $row['cells'][0]['load_minutes']);
        $this->assertSame(1, $row['cells'][0]['unestimated']);
        $this->assertSame(1, $row['later']['unestimated']);
    }

    public function test_finished_and_trashed_tasks_do_not_count(): void
    {
        $this->task(['estimate_minutes' => 60, 'due_date' => '2026-11-04', 'status' => 'done']);
        $this->task(['estimate_minutes' => 60, 'due_date' => '2026-11-04', 'status' => 'archive']);
        $this->task(['estimate_minutes' => 60, 'due_date' => '2026-11-04'])->delete();

        $this->assertSame(0, $this->row()['cells'][0]['tasks']);
    }

    public function test_over_capacity_is_flagged(): void
    {
        $this->task(['estimate_minutes' => self::FULL_WEEK + 1, 'due_date' => '2026-11-04']);

        $this->assertTrue($this->row()['cells'][0]['over']);
        $this->assertFalse($this->row()['cells'][1]['over']);

        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))->get(route('capacity.index'))->assertOk()
            ->assertSee('40h 1m')
            ->assertSee('Dana Dev')
            ->assertSee('This week');
    }

    public function test_deactivated_members_are_left_out(): void
    {
        $gone = User::factory()->create(['deactivated_at' => now()]);
        $this->team->members()->attach($gone);

        $this->assertNull($this->row($gone));
    }

    public function test_weeks_cannot_go_before_this_one(): void
    {
        $service = app(CapacityService::class);

        $this->assertSame('2026-11-02', $service->firstWeek(now()->subMonth())->toDateString());
        $this->assertSame('2026-11-16', $service->firstWeek(now()->addWeeks(2))->toDateString());
    }

    public function test_a_team_lead_sees_only_teams_they_lead(): void
    {
        $lead = User::factory()->create(['role' => User::ROLE_TEAM_LEAD]);
        $mine = Team::create(['name' => 'Mine', 'color' => '#16a34a', 'team_lead_id' => $lead->id]);

        $response = $this->actingAs($lead)->get(route('capacity.index'))->assertOk();
        $this->assertSame([$mine->id], $response->viewData('teams')->pluck('id')->all());

        $this->get(route('capacity.index', ['team_id' => $this->team->id]))->assertForbidden();

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($lead);
        $this->getJson('/api/v1/capacity?team_id='.$this->team->id)->assertForbidden();
        $this->getJson('/api/v1/capacity')->assertOk()->assertJsonPath('data.team.id', $mine->id);
    }

    public function test_a_lead_with_no_team_gets_an_empty_page(): void
    {
        $lead = User::factory()->create(['role' => User::ROLE_TEAM_LEAD]);

        $this->actingAs($lead)->get(route('capacity.index'))->assertOk()->assertSee('No teams to plan.');
    }

    /** @return array<string, array{0: string}> */
    public static function nonLeads(): array
    {
        return ['developer' => [User::ROLE_DEVELOPER], 'qa' => [User::ROLE_QA], 'viewer' => [User::ROLE_VIEWER]];
    }

    #[DataProvider('nonLeads')]
    public function test_only_leads_can_see_capacity(string $role): void
    {
        $user = User::factory()->create(['role' => $role]);

        $this->actingAs($user)->get(route('capacity.index'))->assertForbidden();

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/capacity')->assertForbidden();
    }

    public function test_web_and_api_agree(): void
    {
        $this->task(['estimate_minutes' => 300, 'due_date' => '2026-11-04']);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $web = $this->actingAs($admin)->get(route('capacity.index', ['team_id' => $this->team->id]))->viewData('capacity');

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($admin);
        $api = $this->getJson('/api/v1/capacity?team_id='.$this->team->id)->assertOk()->json('data');

        $this->assertSame($web['people'][0]['cells'], $api['people'][0]['weeks']);
        $this->assertSame($web['weeks'][0]['start']->toDateString(), $api['weeks'][0]['start']);
    }
}
