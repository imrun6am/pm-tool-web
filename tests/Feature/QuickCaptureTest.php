<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Release;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Quick capture and ⌘K task search (openspec/changes/quick-capture).
 */
class QuickCaptureTest extends TestCase
{
    use RefreshDatabase;

    private Team $core;

    private Team $growth;

    private User $dev;

    private User $lead;

    protected function setUp(): void
    {
        parent::setUp();

        $this->core = Team::create(['name' => 'Core', 'color' => '#0891b2']);
        $this->growth = Team::create(['name' => 'Growth', 'color' => '#e11d48']);
        $this->dev = User::factory()->create(['role' => User::ROLE_DEVELOPER, 'name' => 'Dana Dev']);
        $this->lead = User::factory()->create(['role' => User::ROLE_TEAM_LEAD, 'name' => 'Lee Lead']);
        $this->core->members()->attach($this->dev);
    }

    private function release(Team $team, string $name): Release
    {
        $project = Project::firstOrCreate(['name' => 'Checkout'], ['color' => '#4f46e5']);

        return Release::create([
            'project_id' => $project->id, 'team_id' => $team->id, 'name' => $name,
            'year' => 2026, 'quarter' => 4, 'start_date' => '2026-10-01', 'end_date' => '2026-12-31',
        ]);
    }

    public function test_dialog_capture_creates_a_task_assigned_to_me_on_my_team(): void
    {
        $this->actingAs($this->dev)
            ->postJson(route('tasks.quick.store'), ['title' => 'Update API docs', 'due_date' => '2026-11-02'])
            ->assertCreated()
            ->assertJsonPath('task.title', 'Update API docs')
            ->assertJsonPath('message', 'Added “Update API docs” to the board.');

        $task = Task::sole();
        $this->assertSame($this->dev->id, $task->assignee_id);
        $this->assertSame($this->dev->id, $task->created_by);
        $this->assertSame($this->core->id, $task->team_id);
        $this->assertSame('todo', $task->status);
        $this->assertSame('2026-11-02', $task->due_date->toDateString());
    }

    public function test_page_form_redirects_to_the_new_task(): void
    {
        $this->actingAs($this->lead)->get(route('tasks.quick'))
            ->assertOk()->assertSee('What needs doing?')->assertSee('Lee Lead (me)');

        $response = $this->post(route('tasks.quick.store'), ['title' => 'Plan Q1', 'assignee_id' => '']);

        $task = Task::sole();
        $response->assertRedirect(route('tasks.show', $task))->assertSessionHas('success');
        $this->assertNull($task->assignee_id);
    }

    public function test_dialog_fragment_is_just_the_form(): void
    {
        $this->actingAs($this->dev)
            ->get(route('tasks.quick'), ['X-Quick-Capture' => '1'])
            ->assertOk()
            ->assertSee('quickTaskForm(true)', false)
            ->assertDontSee('<html', false);
    }

    public function test_a_developer_cannot_assign_outside_their_teams_or_use_another_teams_release(): void
    {
        $outsider = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $this->growth->members()->attach($outsider);
        $growthRelease = $this->release($this->growth, 'Growth R1');

        $this->actingAs($this->dev)
            ->postJson(route('tasks.quick.store'), ['title' => 'Sneaky', 'assignee_id' => $outsider->id])
            ->assertUnprocessable()->assertJsonValidationErrors('assignee_id');

        $this->postJson(route('tasks.quick.store'), ['title' => 'Sneaky', 'release_id' => $growthRelease->id])
            ->assertForbidden();

        $this->assertSame(0, Task::count());
    }

    public function test_the_form_offers_only_my_teammates_and_releases(): void
    {
        $outsider = User::factory()->create(['role' => User::ROLE_DEVELOPER, 'name' => 'Olive Outsider']);
        $this->growth->members()->attach($outsider);
        $this->release($this->core, 'Core R1');
        $this->release($this->growth, 'Growth R1');

        $this->actingAs($this->dev)->get(route('tasks.quick'))
            ->assertOk()
            ->assertSee('Core R1')->assertDontSee('Growth R1')
            ->assertSee('Dana Dev (me)')->assertDontSee('Olive Outsider');
    }

    public function test_title_is_required(): void
    {
        $this->actingAs($this->dev)
            ->postJson(route('tasks.quick.store'), ['title' => ''])
            ->assertUnprocessable()->assertJsonValidationErrors('title');
    }

    public function test_new_task_is_the_first_create_action(): void
    {
        $this->actingAs($this->dev)->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('tasks.quick'))
            ->assertSee('x-data="quickCapture"', false);
    }

    public function test_search_returns_only_visible_tasks_open_first_and_matches_the_api(): void
    {
        $mine = Task::create(['title' => 'Login page polish', 'status' => 'todo', 'position' => 0, 'team_id' => $this->core->id]);
        $this->travel(1)->minutes();
        $done = Task::create(['title' => 'Login redirect', 'status' => 'done', 'position' => 1, 'team_id' => $this->core->id]);
        Task::create(['title' => 'Login for growth', 'status' => 'todo', 'position' => 0, 'team_id' => $this->growth->id]);
        Task::create(['title' => 'Unrelated', 'status' => 'todo', 'position' => 2, 'team_id' => $this->core->id]);

        $web = $this->actingAs($this->dev)->getJson(route('search.tasks', ['q' => 'login']))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $mine->id)
            ->assertJsonPath('data.1.id', $done->id)
            ->assertJsonPath('data.0.url', route('tasks.show', $mine));

        Sanctum::actingAs($this->dev);
        $api = $this->getJson(route('api-v1.search.tasks', ['q' => 'login']))->assertOk();
        $this->assertSame(array_column($web->json('data'), 'id'), array_column($api->json('data'), 'id'));

        // Leads see every team's task.
        $this->actingAs($this->lead)->getJson(route('search.tasks', ['q' => 'login']))->assertJsonCount(3, 'data');
    }

    public function test_search_needs_two_characters_and_escapes_wildcards(): void
    {
        Task::create(['title' => 'Alpha', 'status' => 'todo', 'position' => 0, 'team_id' => $this->core->id]);
        Task::create(['title' => '100% coverage', 'status' => 'todo', 'position' => 1, 'team_id' => $this->core->id]);

        $this->actingAs($this->dev)->getJson(route('search.tasks', ['q' => 'a']))->assertJsonCount(0, 'data');
        $this->getJson(route('search.tasks', ['q' => '0%']))->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', '100% coverage');
        $this->getJson(route('search.tasks', ['q' => '%%']))->assertJsonCount(0, 'data');
    }
}
