<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Release;
use App\Models\Task;
use App\Models\TasksheetEntry;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The team directory (search / archived / sort, all in the URL) and the team
 * page's workload, schedule, activity and lead-only health panels.
 */
class TeamPagesTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, array $attrs = []): User
    {
        return User::factory()->create(['role' => $role] + $attrs);
    }

    private function release(Team $team, string $name, array $attrs = []): Release
    {
        $project = Project::firstOrCreate(['name' => 'Checkout'], ['color' => '#4f46e5']);

        return Release::create($attrs + [
            'project_id' => $project->id,
            'team_id' => $team->id,
            'name' => $name,
            'year' => (int) today()->format('Y'),
            'quarter' => today()->quarter,
            'start_date' => today()->subDays(5)->toDateString(),
            'end_date' => today()->addDays(10)->toDateString(),
        ]);
    }

    public function test_directory_hides_archived_teams_unless_asked(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        Team::create(['name' => 'Alpha', 'color' => '#0891b2']);
        Team::create(['name' => 'Legacy', 'color' => '#475569', 'archived_at' => now()]);

        $this->actingAs($admin)->get(route('teams.index'))
            ->assertOk()
            ->assertSee('Alpha')
            ->assertDontSee('Legacy')
            ->assertSee('Show archived');

        $this->actingAs($admin)->get(route('teams.index', ['archived' => 1]))
            ->assertOk()
            ->assertSeeInOrder(['Alpha', 'Legacy']) // archived always last
            ->assertSee('Archived');
    }

    public function test_directory_searches_by_name(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        Team::create(['name' => 'Alpha', 'color' => '#0891b2']);
        Team::create(['name' => 'Bravo', 'color' => '#0891b2']);

        $response = $this->actingAs($admin)->get(route('teams.index', ['search' => 'brav']))->assertOk();
        $this->assertSame(['Bravo'], $response->viewData('teams')->pluck('name')->all());

        $this->actingAs($admin)->get(route('teams.index', ['search' => 'zzz']))
            ->assertOk()
            ->assertSee('No teams match');
    }

    public function test_directory_sorts_and_counts_open_work(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $lead = $this->user(User::ROLE_TEAM_LEAD, ['name' => 'Lena Lead']);
        $alpha = Team::create(['name' => 'Alpha', 'color' => '#0891b2']);
        $bravo = Team::create(['name' => 'Bravo', 'color' => '#0891b2', 'team_lead_id' => $lead->id]);

        $bravo->memberRecords()->attach([$this->user(User::ROLE_DEVELOPER)->id, $this->user(User::ROLE_QA)->id]);
        $alpha->memberRecords()->attach($this->user(User::ROLE_DEVELOPER)->id);

        $this->release($bravo, 'Bravo live');
        $this->release($bravo, 'Bravo shipped', ['completed_at' => now()]);

        Task::create(['team_id' => $bravo->id, 'title' => 'Open', 'status' => 'todo']);
        Task::create(['team_id' => $bravo->id, 'title' => 'Done', 'status' => 'done']);
        Task::create(['team_id' => $bravo->id, 'title' => 'Filed', 'status' => 'archive']);
        Task::create(['team_id' => $alpha->id, 'title' => 'A1', 'status' => 'in_progress']);
        Task::create(['team_id' => $alpha->id, 'title' => 'A2', 'status' => 'recheck']);

        $byMembers = $this->actingAs($admin)->get(route('teams.index', ['sort' => 'members']))->assertOk();
        $this->assertSame(['Bravo', 'Alpha'], $byMembers->viewData('teams')->pluck('name')->all());
        $byMembers->assertSee('Lena Lead');

        $teams = $this->actingAs($admin)->get(route('teams.index', ['sort' => 'tasks']))->viewData('teams');
        $this->assertSame(['Alpha', 'Bravo'], $teams->pluck('name')->all());
        $this->assertSame(2, (int) $teams->firstWhere('name', 'Alpha')->open_tasks_count);
        $this->assertSame(1, (int) $teams->firstWhere('name', 'Bravo')->open_tasks_count); // done + archived excluded
        $this->assertSame(1, $teams->firstWhere('name', 'Bravo')->active_releases_count);
        $this->assertSame(2, $teams->firstWhere('name', 'Bravo')->releases_count);

        $this->assertSame(['Bravo', 'Alpha'], $this->actingAs($admin)
            ->get(route('teams.index', ['sort' => 'releases']))->viewData('teams')->pluck('name')->all());

        // An unknown sort falls back to name.
        $this->assertSame('name', $this->actingAs($admin)
            ->get(route('teams.index', ['sort' => 'nope']))->viewData('filters')['sort']);
    }

    public function test_team_page_shows_workload_schedule_and_activity(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $team = Team::create(['name' => 'Alpha', 'color' => '#0891b2']);
        $dev = $this->user(User::ROLE_DEVELOPER, ['name' => 'Dana Dev']);
        $gone = $this->user(User::ROLE_QA, ['name' => 'Quinn Gone']);
        $team->memberRecords()->attach($dev->id);
        $team->memberRecords()->attach($gone->id, ['left_at' => now()->subDay()]);

        $release = $this->release($team, 'Checkout v9');
        Task::create(['team_id' => $team->id, 'release_id' => $release->id, 'title' => 'Late', 'status' => 'todo',
            'assignee_id' => $dev->id, 'due_date' => today()->subDays(2)->toDateString()]);
        Task::create(['team_id' => $team->id, 'title' => 'Loose end', 'status' => 'in_review']);

        $response = $this->actingAs($admin)->get(route('teams.show', $team))->assertOk()
            ->assertSee('Checkout v9')
            ->assertSee('Dana Dev')
            ->assertSee('Past members')
            ->assertSee('Quinn Gone')
            ->assertSee('Recent activity')
            ->assertSee('Team health')
            ->assertSee(route('tasksheet.index', ['team' => $team->id]), false)
            ->assertSee(e(route('board.index', ['team_id' => $team->id, 'assignee_id' => $dev->id])), false);

        $tasks = $response->viewData('tasks');
        $this->assertSame([2, 1, 1], [$tasks['open'], $tasks['overdue'], $tasks['unassigned']]);
        $this->assertSame(1, $tasks['byAssignee'][$dev->id]);
        $this->assertNotEmpty($response->viewData('activities'));
    }

    public function test_health_panel_is_for_leads_only(): void
    {
        $team = Team::create(['name' => 'Alpha', 'color' => '#0891b2']);

        $this->actingAs($this->user(User::ROLE_VIEWER))->get(route('teams.show', $team))
            ->assertOk()
            ->assertDontSee('Team health')
            ->assertViewHas('health', null);
    }

    public function test_tasksheet_fill_and_standup_attendance(): void
    {
        Carbon::setTestNow('2026-10-28 10:00:00'); // a Wednesday
        $team = Team::create(['name' => 'Alpha', 'color' => '#0891b2']);
        $logged = $this->user(User::ROLE_DEVELOPER, ['name' => 'Logged']);
        $idle = $this->user(User::ROLE_DEVELOPER, ['name' => 'Idle']);
        $team->memberRecords()->attach([$logged->id, $idle->id]);

        TasksheetEntry::create(['team_id' => $team->id, 'user_id' => $logged->id, 'date' => '2026-10-28', 'plan' => 'Ship it']);
        // A row created only by a standup mark is not "logged".
        $marked = TasksheetEntry::create(['team_id' => $team->id, 'user_id' => $idle->id, 'date' => '2026-10-28']);
        $marked->forceFill(['standup_attended' => false])->save();
        $earlier = TasksheetEntry::create(['team_id' => $team->id, 'user_id' => $logged->id, 'date' => '2026-10-27', 'plan' => 'x']);
        $earlier->forceFill(['standup_attended' => true])->save();

        $service = app(TeamService::class);

        $fill = $service->tasksheetFill($team);
        $this->assertSame([2, 1], [$fill['roster'], $fill['logged']]);
        $this->assertSame(['Idle'], $fill['missing']->pluck('name')->all());

        $standup = $service->standupAttendance($team);
        $this->assertCount(TeamService::STANDUP_DAYS, $standup['days']);
        $this->assertTrue(collect($standup['days'])->every(fn ($d) => $d['date']->isWeekday()));
        $this->assertTrue(end($standup['days'])['today']);
        $this->assertSame([1, 2, 50], [$standup['attended'], $standup['marked'], $standup['rate']]);

        Carbon::setTestNow();
    }

    public function test_edit_page_offers_delete_only_without_releases(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $empty = Team::create(['name' => 'Empty', 'color' => '#0891b2']);
        $busy = Team::create(['name' => 'Busy', 'color' => '#0891b2']);
        $this->release($busy, 'R1');

        $this->actingAs($admin)->get(route('teams.edit', $empty))->assertOk()->assertSee('Delete team');
        $this->actingAs($admin)->get(route('teams.edit', $busy))->assertOk()
            ->assertDontSee('Delete team')
            ->assertSee('Archive team');
    }
}
