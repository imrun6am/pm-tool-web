<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Project;
use App\Models\Release;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Services\DashboardService;
use App\Services\ProjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tasks no longer need a release: they can be created unlinked, linked or
 * unlinked later, and release-scoped views and roll-ups ignore them.
 */
class ReleaseOptionalTasksTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private Team $team;

    private Release $release;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = Project::create(['name' => 'Checkout', 'color' => '#4f46e5']);
        $this->team = Team::create(['name' => 'Core', 'color' => '#0891b2']);
        $this->release = $this->release('Billing v2');
    }

    private function release(string $name): Release
    {
        return Release::create([
            'project_id' => $this->project->id, 'team_id' => $this->team->id, 'name' => $name,
            'year' => 2026, 'quarter' => 3, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31',
        ]);
    }

    private function task(?Release $release, array $attrs = []): Task
    {
        return Task::create($attrs + [
            'release_id' => $release?->id, 'title' => 'Task', 'status' => 'todo', 'position' => 0,
        ]);
    }

    // ── Creating ───────────────────────────────────────────────────────────

    public function test_board_quick_add_without_a_release(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_DEVELOPER]);

        $this->actingAs($user)
            ->post(route('board.tasks.store'), ['title' => 'Loose card', 'status' => 'todo', 'release_id' => ''])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $task = Task::sole();
        $this->assertSame('Loose card', $task->title);
        $this->assertNull($task->release_id);
        $this->assertSame($user->id, $task->created_by);

        // The board renders the unlinked card without a release chip.
        $this->actingAs($user)->get(route('board.index'))
            ->assertOk()
            ->assertSee('Loose card')
            ->assertDontSee(route('releases.show', $this->release), false);
    }

    public function test_api_creates_tasks_without_a_release(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_QA]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/tasks', ['title' => 'Standalone', 'status' => 'in_progress', 'phase' => 'qa'])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Standalone')
            ->assertJsonPath('data.release_id', null)
            ->assertJsonPath('data.release', null)
            ->assertJsonPath('data.status', 'in_progress');

        $this->postJson('/api/v1/board/tasks', ['title' => 'Quick', 'status' => 'todo'])
            ->assertCreated()
            ->assertJsonPath('data.release_id', null)
            ->assertJsonPath('data.release', null);

        $this->postJson('/api/v1/tasks', ['title' => 'Linked', 'release_id' => $this->release->id])
            ->assertCreated()
            ->assertJsonPath('data.release_id', $this->release->id)
            ->assertJsonPath('data.release.name', 'Billing v2');

        $this->postJson('/api/v1/tasks', ['title' => 'Bad', 'release_id' => 999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('release_id');

        $this->assertSame(2, Task::whereNull('release_id')->count());
    }

    public function test_release_route_always_links_to_that_release(): void
    {
        $other = $this->release('Other');

        $this->actingAs(User::factory()->create())
            ->post(route('releases.tasks.store', $this->release), ['title' => 'Pinned', 'release_id' => $other->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($this->release->id, Task::sole()->release_id);
    }

    public function test_subtask_inherits_the_parents_release_or_none(): void
    {
        $user = User::factory()->create();
        $unlinked = $this->task(null, ['title' => 'Parent']);
        $linked = $this->task($this->release, ['title' => 'Linked parent']);

        $this->actingAs($user)->post(route('tasks.subtasks.store', $unlinked), [
            'title' => 'Child', 'release_id' => $this->release->id,
        ])->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('tasks.subtasks.store', $linked), ['title' => 'Linked child']);

        $this->assertNull(Task::where('title', 'Child')->value('release_id'));
        $this->assertSame($this->release->id, Task::where('title', 'Linked child')->value('release_id'));
    }

    // ── Linking and unlinking ──────────────────────────────────────────────

    public function test_task_page_links_and_unlinks_a_release_and_subtasks_follow(): void
    {
        $user = User::factory()->create();
        $task = $this->task(null, ['title' => 'Floating']);
        $sub = $this->task(null, ['title' => 'Sub', 'parent_id' => $task->id]);

        $this->actingAs($user)->get(route('tasks.show', $task))
            ->assertOk()
            ->assertSee('name="release_id"', false)
            ->assertSee('No release');

        $this->actingAs($user)->put(route('tasks.update', $task), [
            'title' => 'Floating', 'status' => 'todo', 'release_id' => $this->release->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame($this->release->id, $task->fresh()->release_id);
        $this->assertSame($this->release->id, $sub->fresh()->release_id);

        $this->actingAs($user)->put(route('tasks.update', $task), [
            'title' => 'Floating', 'status' => 'todo', 'release_id' => '',
        ])->assertSessionHasNoErrors();

        $this->assertNull($task->fresh()->release_id);
        $this->assertNull($sub->fresh()->release_id);
    }

    public function test_a_subtask_cannot_change_its_own_release(): void
    {
        $parent = $this->task($this->release, ['title' => 'Parent']);
        $sub = $this->task($this->release, ['title' => 'Sub', 'parent_id' => $parent->id]);

        $this->actingAs(User::factory()->create())->put(route('tasks.update', $sub), [
            'title' => 'Sub', 'status' => 'todo', 'release_id' => '',
        ])->assertSessionHasNoErrors();

        $this->assertSame($this->release->id, $sub->fresh()->release_id);
    }

    public function test_api_update_links_unlinks_and_leaves_the_release_alone_when_omitted(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $task = $this->task(null, ['title' => 'T']);

        $this->putJson("/api/v1/tasks/{$task->id}", ['title' => 'T', 'release_id' => $this->release->id])
            ->assertOk()->assertJsonPath('data.release_id', $this->release->id)
            ->assertJsonPath('data.release.id', $this->release->id);

        $this->putJson("/api/v1/tasks/{$task->id}", ['title' => 'Renamed'])
            ->assertOk()->assertJsonPath('data.release_id', $this->release->id);

        $this->putJson("/api/v1/tasks/{$task->id}", ['title' => 'Renamed', 'release_id' => null])
            ->assertOk()->assertJsonPath('data.release_id', null)->assertJsonPath('data.release', null);
    }

    public function test_assignees_are_team_bound_only_when_a_release_is_linked(): void
    {
        $outsider = User::factory()->create();
        $member = User::factory()->create();
        $this->team->members()->attach($member);
        $task = $this->task(null, ['title' => 'T']);
        $actor = User::factory()->create();

        // Unlinked: anyone may be assigned.
        $this->actingAs($actor)->put(route('tasks.update', $task), [
            'title' => 'T', 'status' => 'todo', 'release_id' => '', 'assignee_id' => $outsider->id,
        ])->assertSessionHasNoErrors();
        $this->assertSame($outsider->id, $task->fresh()->assignee_id);

        // Linking checks a changed assignee against the release's team.
        $this->actingAs($actor)->put(route('tasks.update', $task), [
            'title' => 'T', 'status' => 'todo', 'release_id' => $this->release->id, 'assignee_id' => $member->id,
        ])->assertSessionHasNoErrors();

        $this->actingAs($actor)->put(route('tasks.update', $task), [
            'title' => 'T', 'status' => 'todo', 'release_id' => $this->release->id, 'assignee_id' => $outsider->id,
        ])->assertSessionHasErrors('assignee_id');

        // The task page offers every active user for an unlinked task.
        $loose = $this->task(null, ['title' => 'Loose']);
        $users = $this->actingAs($actor)->get(route('tasks.show', $loose))->viewData('users');
        $this->assertTrue($users->contains('id', $outsider->id));
    }

    // ── Filtering and release-scoped views ─────────────────────────────────

    public function test_board_no_release_filter(): void
    {
        $this->task(null, ['title' => 'Unlinked card']);
        $this->task($this->release, ['title' => 'Linked card']);
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('board.index', ['release_id' => 'none']))
            ->assertOk()
            ->assertSee('Unlinked card')
            ->assertDontSee('Linked card')
            ->assertSee('<option value="none" selected>No release</option>', false);

        $this->actingAs($user)->get(route('board.index', ['release_id' => $this->release->id]))
            ->assertOk()
            ->assertSee('Linked card')
            ->assertDontSee('Unlinked card');

        Sanctum::actingAs($user);
        $columns = $this->getJson('/api/v1/board?release_id=none')->assertOk()
            ->assertJsonPath('data.filters.release_id', 'none')
            ->json('data.columns');
        $this->assertSame(['Unlinked card'], collect($columns)->flatMap(fn ($c) => $c['tasks'])->pluck('title')->all());

        $this->getJson('/api/v1/tasks?release_id=none')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Unlinked card');
    }

    public function test_release_pages_and_roll_ups_ignore_unlinked_tasks(): void
    {
        $this->task($this->release, ['title' => 'Release work', 'status' => 'done']);
        $this->task(null, ['title' => 'Unlinked work', 'status' => 'todo']);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->get(route('releases.show', $this->release))
            ->assertOk()
            ->assertSee('Release work')
            ->assertDontSee('Unlinked work');

        Sanctum::actingAs($admin);
        $this->getJson("/api/v1/releases/{$this->release->id}/tasks")->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Release work');

        $stats = app(ProjectService::class)->analytics($this->project, $this->project->releases()->get());
        $this->assertSame(1, $stats['taskTotal']);
        $this->assertSame(100, $stats['donePct']);
    }

    public function test_member_dashboard_lists_unlinked_tasks(): void
    {
        $dev = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $this->task(null, ['title' => 'My loose task', 'assignee_id' => $dev->id]);

        $this->assertSame(['My loose task'], app(DashboardService::class)->memberSnapshot($dev)['tasks']->pluck('title')->all());

        $this->actingAs($dev)->get(route('dashboard'))->assertOk()->assertSee('My loose task');
    }

    // ── Access ─────────────────────────────────────────────────────────────

    public function test_limited_roles_can_see_and_work_on_unlinked_tasks(): void
    {
        $task = $this->task(null, ['title' => 'Open to everyone']);

        foreach ([User::ROLE_DEVELOPER, User::ROLE_QA, User::ROLE_VIEWER] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)->get(route('tasks.show', $task))
                ->assertOk()
                ->assertSee('Open to everyone')
                ->assertSee(route('board.index'), false); // breadcrumb falls back to the board

            $this->actingAs($user)->get(route('board.index'))->assertOk()->assertSee('Open to everyone');

            Sanctum::actingAs($user);
            $this->getJson("/api/v1/tasks/{$task->id}")->assertOk()->assertJsonPath('data.release', null);
            $this->app['auth']->forgetGuards();
        }
    }

    // ── Activity and release deletion ──────────────────────────────────────

    public function test_activity_is_logged_with_a_null_release(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('board.tasks.store'), ['title' => 'Logged', 'status' => 'todo']);
        $task = Task::sole();

        $created = Activity::where('subject_type', $task->getMorphClass())->where('subject_id', $task->id)->sole();
        $this->assertSame('created', $created->event);
        $this->assertNull($created->release_id);

        // Linking records against the new release.
        $this->actingAs($user)->put(route('tasks.update', $task), [
            'title' => 'Logged', 'status' => 'todo', 'release_id' => $this->release->id,
        ]);
        $this->assertSame($this->release->id, Activity::where('event', 'updated')->latest('id')->value('release_id'));
    }

    public function test_deleting_a_release_unlinks_its_tasks(): void
    {
        $task = $this->task($this->release, ['title' => 'Survivor']);
        $sub = $this->task($this->release, ['title' => 'Sub survivor', 'parent_id' => $task->id]);
        $task->comments()->create(['user_id' => User::factory()->create()->id, 'body' => 'Keep me']);

        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
            ->delete(route('releases.destroy', $this->release))
            ->assertRedirect();

        $this->assertModelMissing($this->release);
        $this->assertNull($task->fresh()->release_id);
        $this->assertNull($sub->fresh()->release_id);
        $this->assertSame(1, $task->comments()->count());
    }
}
