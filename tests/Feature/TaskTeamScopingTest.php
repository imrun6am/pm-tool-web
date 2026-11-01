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
 * Developers and QA are kept to their own teams' tasks: they never see or
 * touch another team's work, and they don't choose a team — what they create
 * goes to theirs. Leads and viewers are unaffected.
 */
class TaskTeamScopingTest extends TestCase
{
    use RefreshDatabase;

    private Team $core;

    private Team $growth;

    private Release $coreRelease;

    private Release $growthRelease;

    private User $dev;

    protected function setUp(): void
    {
        parent::setUp();

        $this->core = Team::create(['name' => 'Core', 'color' => '#0891b2']);
        $this->growth = Team::create(['name' => 'Growth', 'color' => '#e11d48']);
        $this->coreRelease = $this->release($this->core, 'Core R1');
        $this->growthRelease = $this->release($this->growth, 'Growth R1');

        $this->dev = User::factory()->create(['role' => User::ROLE_DEVELOPER, 'name' => 'Dana Dev']);
        $this->core->members()->attach($this->dev);
    }

    private function release(Team $team, string $name): Release
    {
        $project = Project::firstOrCreate(['name' => 'Checkout'], ['color' => '#4f46e5']);

        return Release::create([
            'project_id' => $project->id, 'team_id' => $team->id, 'name' => $name,
            'year' => 2026, 'quarter' => 3, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31',
        ]);
    }

    private function task(string $title, array $attrs = []): Task
    {
        return Task::create($attrs + ['title' => $title, 'status' => 'todo', 'position' => 0]);
    }

    // ── Visibility ─────────────────────────────────────────────────────────

    public function test_a_developer_sees_only_their_teams_work_on_the_board_and_api(): void
    {
        $outsider = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $this->task('Core linked', ['release_id' => $this->coreRelease->id]);
        $this->task('Core loose', ['team_id' => $this->core->id]);
        $this->task('Growth secret', ['release_id' => $this->growthRelease->id]);
        $this->task('Growth loose', ['team_id' => $this->growth->id]);
        $this->task('Assigned to Dana elsewhere', ['team_id' => $this->growth->id, 'assignee_id' => $this->dev->id]);
        $this->task('Dana personal', ['created_by' => $this->dev->id]);
        $this->task('Someone else personal', ['created_by' => $outsider->id]);

        $visible = ['Assigned to Dana elsewhere', 'Core linked', 'Core loose', 'Dana personal'];

        $page = $this->actingAs($this->dev)->get(route('board.index'))
            ->assertOk()
            ->assertSee($visible)
            ->assertDontSee('Growth secret')
            ->assertDontSee('Growth loose')
            ->assertDontSee('Someone else personal');
        // Other teams' releases aren't offered in the board's pickers either.
        $this->assertSame(['Core R1'], $page->viewData('releases')->pluck('name')->all());

        Sanctum::actingAs($this->dev);
        $board = collect($this->getJson('/api/v1/board')->assertOk()->json('data.columns'))
            ->flatMap(fn ($c) => $c['tasks'])->pluck('title')->sort()->values()->all();
        $this->assertSame($visible, $board);

        $index = collect($this->getJson('/api/v1/tasks?per_page=50')->assertOk()->json('data'))
            ->pluck('title')->sort()->values()->all();
        $this->assertSame($visible, $index);

        // Even asking for the other team explicitly returns nothing of theirs.
        $this->getJson("/api/v1/board?team_id={$this->growth->id}&release_id=none")->assertOk();
        $this->getJson("/api/v1/tasks?release_id={$this->growthRelease->id}")->assertOk()->assertJsonCount(0, 'data');
        // Other teams' release pages are off limits altogether.
        $this->getJson("/api/v1/releases/{$this->growthRelease->id}/tasks")->assertForbidden();
        $this->getJson("/api/v1/releases/{$this->growthRelease->id}")->assertForbidden();
    }

    public function test_direct_urls_to_another_teams_task_are_forbidden(): void
    {
        $task = $this->task('Growth secret', ['release_id' => $this->growthRelease->id]);
        $sub = $this->task('Growth sub', ['parent_id' => $task->id, 'release_id' => $this->growthRelease->id]);

        $this->actingAs($this->dev)->get(route('tasks.show', $task))->assertForbidden();
        $this->actingAs($this->dev)->get(route('tasks.show', $sub))->assertForbidden();
        $this->actingAs($this->dev)->put(route('tasks.update', $task), ['title' => 'Hijack'])->assertForbidden();
        $this->actingAs($this->dev)->patch(route('tasks.status', $task), ['status' => 'done'])->assertForbidden();
        $this->actingAs($this->dev)->post(route('tasks.subtasks.store', $task), ['title' => 'Sneaky'])->assertForbidden();
        $this->actingAs($this->dev)->post(route('tasks.comments.store', $task), ['body' => 'Hi'])->assertForbidden();
        $this->actingAs($this->dev)->patchJson(route('board.move', $task), ['status' => 'done'])->assertForbidden();
        $this->actingAs($this->dev)->delete(route('tasks.destroy', $task))->assertForbidden();
        $this->actingAs($this->dev)->post(route('releases.tasks.store', $this->growthRelease), ['title' => 'Into theirs'])
            ->assertForbidden();

        Sanctum::actingAs($this->dev);
        $this->getJson("/api/v1/tasks/{$task->id}")->assertForbidden();
        $this->putJson("/api/v1/tasks/{$task->id}", ['title' => 'Hijack'])->assertForbidden();
        $this->patchJson("/api/v1/tasks/{$task->id}/status", ['status' => 'done'])->assertForbidden();
        $this->postJson("/api/v1/tasks/{$task->id}/subtasks", ['title' => 'Sneaky'])->assertForbidden();
        $this->getJson("/api/v1/tasks/{$task->id}/comments")->assertForbidden();
        $this->postJson("/api/v1/tasks/{$task->id}/comments", ['body' => 'Hi'])->assertForbidden();
        $this->patchJson("/api/v1/board/tasks/{$task->id}", ['status' => 'done'])->assertForbidden();
        $this->deleteJson("/api/v1/tasks/{$task->id}")->assertForbidden();
        $this->postJson("/api/v1/releases/{$this->growthRelease->id}/tasks", ['title' => 'Into theirs'])->assertForbidden();

        $this->assertSame(['todo', 'Growth secret'], [$task->fresh()->status, $task->fresh()->title]);
        $this->assertSame(0, $task->comments()->count());
        $this->assertSame(2, Task::count());
    }

    public function test_a_board_reorder_cannot_renumber_another_teams_cards(): void
    {
        $mine = $this->task('Mine', ['team_id' => $this->core->id, 'position' => 5]);
        $theirs = $this->task('Theirs', ['team_id' => $this->growth->id, 'position' => 7]);

        Sanctum::actingAs($this->dev);
        $this->patchJson("/api/v1/board/tasks/{$mine->id}", ['status' => 'todo', 'ordered_ids' => [$theirs->id, $mine->id]])
            ->assertOk();

        $this->assertSame(1, $mine->fresh()->position);
        $this->assertSame(7, $theirs->fresh()->position);
    }

    public function test_a_developer_reaches_only_their_own_teams_release_pages(): void
    {
        $this->task('Core work', ['release_id' => $this->coreRelease->id]);
        $this->task('Growth secret', ['release_id' => $this->growthRelease->id]);

        $this->actingAs($this->dev)->get(route('releases.show', $this->coreRelease))
            ->assertOk()
            ->assertSee('Core work');
        $this->actingAs($this->dev)->get(route('releases.show', $this->growthRelease))->assertForbidden();
    }

    // ── Team is set for them ───────────────────────────────────────────────

    public function test_tasks_a_developer_creates_go_to_their_team_whatever_they_send(): void
    {
        $this->actingAs($this->dev)->post(route('board.tasks.store'), [
            'title' => 'Web card', 'status' => 'todo', 'team_id' => $this->growth->id,
        ])->assertSessionHasNoErrors();

        Sanctum::actingAs($this->dev);
        $this->postJson('/api/v1/tasks', ['title' => 'API task', 'team_id' => $this->growth->id])
            ->assertCreated()->assertJsonPath('data.team_id', $this->core->id);
        $this->postJson('/api/v1/board/tasks', ['title' => 'API card', 'status' => 'todo', 'team_id' => $this->growth->id])
            ->assertCreated()->assertJsonPath('data.team_id', $this->core->id);

        $this->assertSame([$this->core->id], Task::pluck('team_id')->unique()->values()->all());

        // An edit can't move it to another team either.
        $task = Task::where('title', 'API task')->sole();
        $this->putJson("/api/v1/tasks/{$task->id}", ['title' => 'API task', 'team_id' => $this->growth->id])
            ->assertOk()->assertJsonPath('data.team_id', $this->core->id);
    }

    public function test_a_single_team_developer_gets_no_team_picker(): void
    {
        $task = $this->task('Loose', ['team_id' => $this->core->id, 'created_by' => $this->dev->id]);

        $this->actingAs($this->dev)->get(route('board.index'))
            ->assertOk()
            ->assertDontSee('id="team_id"', false)
            ->assertDontSee('name="team_id"', false);

        $this->actingAs($this->dev)->get(route('tasks.show', $task))
            ->assertOk()
            ->assertDontSee('name="team_id"', false)
            ->assertSee('Core');
    }

    public function test_a_developer_links_and_assigns_only_within_their_team(): void
    {
        $teammate = User::factory()->create(['name' => 'Tim Teammate']);
        $this->core->members()->attach($teammate);
        $stranger = User::factory()->create(['name' => 'Sam Stranger']);
        $this->growth->members()->attach($stranger);

        Sanctum::actingAs($this->dev);
        $this->postJson('/api/v1/tasks', ['title' => 'X', 'release_id' => $this->growthRelease->id])
            ->assertUnprocessable()->assertJsonValidationErrors('release_id');
        $this->postJson('/api/v1/tasks', ['title' => 'X', 'assignee_id' => $stranger->id])
            ->assertUnprocessable()->assertJsonValidationErrors('assignee_id');
        $this->postJson('/api/v1/board/tasks', ['title' => 'X', 'status' => 'todo', 'release_id' => $this->growthRelease->id])
            ->assertUnprocessable()->assertJsonValidationErrors('release_id');
        $this->postJson('/api/v1/tasks', ['title' => 'Y', 'assignee_id' => $teammate->id, 'release_id' => $this->coreRelease->id])
            ->assertCreated();
        $this->app['auth']->forgetGuards();

        // The pickers offer only teammates.
        $board = $this->actingAs($this->dev)->get(route('board.index'))->assertOk();
        $this->assertEqualsCanonicalizing(['Dana Dev', 'Tim Teammate'], $board->viewData('users')->pluck('name')->all());

        $task = Task::where('title', 'Y')->sole();
        $page = $this->actingAs($this->dev)->get(route('tasks.show', $task))->assertOk();
        $this->assertFalse($page->viewData('users')->contains('id', $stranger->id));
        $this->assertTrue($page->viewData('users')->contains('id', $teammate->id));
    }

    public function test_a_task_from_another_team_assigned_to_them_keeps_its_release_and_team_on_save(): void
    {
        $task = $this->task('Lent out', ['release_id' => $this->growthRelease->id, 'assignee_id' => $this->dev->id]);

        $this->actingAs($this->dev)->get(route('tasks.show', $task))->assertOk()
            ->assertSee('<option value="'.$this->growthRelease->id.'" selected>Growth R1</option>', false);

        $this->actingAs($this->dev)->put(route('tasks.update', $task), [
            'title' => 'Lent out', 'status' => 'in_progress', 'release_id' => $this->growthRelease->id, 'assignee_id' => $this->dev->id,
        ])->assertSessionHasNoErrors();

        $fresh = $task->fresh();
        $this->assertSame([$this->growthRelease->id, $this->growth->id, 'in_progress'], [$fresh->release_id, $fresh->team_id, $fresh->status]);
    }

    // ── Several teams, or none ─────────────────────────────────────────────

    public function test_a_multi_team_developer_must_pick_one_of_their_own_teams(): void
    {
        $this->growth->members()->attach($this->dev);
        $payments = Team::create(['name' => 'Payments', 'color' => '#7c3aed']);
        Sanctum::actingAs($this->dev);

        $this->postJson('/api/v1/tasks', ['title' => 'Which team?'])
            ->assertUnprocessable()->assertJsonValidationErrors('team_id');
        $this->postJson('/api/v1/tasks', ['title' => 'Not mine', 'team_id' => $payments->id])
            ->assertUnprocessable()->assertJsonValidationErrors('team_id');
        $this->postJson('/api/v1/tasks', ['title' => 'Growth work', 'team_id' => $this->growth->id])
            ->assertCreated()->assertJsonPath('data.team_id', $this->growth->id);
        // A linked release decides the team, so no pick is needed.
        $this->postJson('/api/v1/tasks', ['title' => 'Release work', 'release_id' => $this->coreRelease->id])
            ->assertCreated()->assertJsonPath('data.team_id', $this->core->id);
    }

    public function test_a_developer_with_no_team_files_personal_tasks(): void
    {
        $loner = User::factory()->create(['role' => User::ROLE_QA]);
        $this->task('Core loose', ['team_id' => $this->core->id]);

        Sanctum::actingAs($loner);
        $id = $this->postJson('/api/v1/tasks', ['title' => 'My own', 'team_id' => $this->core->id])
            ->assertCreated()->assertJsonPath('data.team_id', null)->json('data.id');
        $this->postJson('/api/v1/tasks', ['title' => 'X', 'release_id' => $this->coreRelease->id])
            ->assertUnprocessable()->assertJsonValidationErrors('release_id');

        $titles = collect($this->getJson('/api/v1/tasks')->json('data'))->pluck('title')->all();
        $this->assertSame(['My own'], $titles);
        $this->app['auth']->forgetGuards();

        // Nobody else on a team sees it; leads do.
        $this->actingAs($this->dev)->get(route('tasks.show', $id))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => User::ROLE_TEAM_LEAD]))->get(route('tasks.show', $id))->assertOk();
    }

    // ── Leads are unaffected ───────────────────────────────────────────────

    public function test_leads_and_viewers_see_everything_and_pick_any_team(): void
    {
        $this->task('Core loose', ['team_id' => $this->core->id]);
        $this->task('Growth secret', ['release_id' => $this->growthRelease->id]);
        $this->task('Teamless', ['created_by' => $this->dev->id]);

        foreach ([User::ROLE_TEAM_LEAD, User::ROLE_ADMIN, User::ROLE_VIEWER] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)->get(route('board.index'))
                ->assertOk()
                ->assertSee(['Core loose', 'Growth secret', 'Teamless'])
                ->assertSee('id="team_id"', false);
        }

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_TEAM_LEAD]));
        $this->postJson('/api/v1/tasks', ['title' => 'For growth', 'team_id' => $this->growth->id])
            ->assertCreated()->assertJsonPath('data.team_id', $this->growth->id);
    }
}
