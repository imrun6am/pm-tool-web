<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Release;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Services\Board\PlanTaskExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tasks carry an optional team (the release's when linked) and the board
 * filters by it, alone or with the release / assignee filters.
 */
class BoardTeamFilterTest extends TestCase
{
    use RefreshDatabase;

    private Team $core;

    private Team $growth;

    private Release $coreRelease;

    protected function setUp(): void
    {
        parent::setUp();

        $this->core = Team::create(['name' => 'Core', 'color' => '#0891b2']);
        $this->growth = Team::create(['name' => 'Growth', 'color' => '#e11d48']);
        $this->coreRelease = $this->release($this->core, 'Core R1');
    }

    private function release(Team $team, string $name): Release
    {
        $project = Project::firstOrCreate(['name' => 'Checkout'], ['color' => '#4f46e5']);

        return Release::create([
            'project_id' => $project->id, 'team_id' => $team->id, 'name' => $name,
            'year' => 2026, 'quarter' => 3, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31',
        ]);
    }

    private function task(string $title, ?Release $release = null, ?Team $team = null, array $attrs = []): Task
    {
        return Task::create($attrs + [
            'release_id' => $release?->id, 'team_id' => $release?->team_id ?? $team?->id,
            'title' => $title, 'status' => 'todo', 'position' => 0,
        ]);
    }

    /** @return list<string> */
    private function apiBoardTitles(string $query): array
    {
        return collect($this->getJson('/api/v1/board?'.$query)->assertOk()->json('data.columns'))
            ->flatMap(fn ($c) => $c['tasks'])->pluck('title')->sort()->values()->all();
    }

    // ── Filtering ──────────────────────────────────────────────────────────

    public function test_team_filter_combines_with_release_assignee_and_no_release(): void
    {
        $lead = User::factory()->create(['role' => User::ROLE_TEAM_LEAD]);
        $dev = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $this->task('Core linked', $this->coreRelease);
        $this->task('Core loose', team: $this->core, attrs: ['assignee_id' => $dev->id]);
        $this->task('Growth loose', team: $this->growth);
        $this->task('Teamless');

        Sanctum::actingAs($lead);
        $this->assertSame(['Core linked', 'Core loose'], $this->apiBoardTitles("team_id={$this->core->id}"));
        $this->assertSame(['Teamless'], $this->apiBoardTitles('team_id=none'));
        $this->assertSame(['Core loose'], $this->apiBoardTitles("team_id={$this->core->id}&release_id=none"));
        $this->assertSame(['Core linked'], $this->apiBoardTitles("team_id={$this->core->id}&release_id={$this->coreRelease->id}"));
        $this->assertSame(['Core loose'], $this->apiBoardTitles("team_id={$this->core->id}&assignee_id={$dev->id}"));
        $this->assertSame([], $this->apiBoardTitles("team_id={$this->growth->id}&release_id={$this->coreRelease->id}"));

        $this->getJson("/api/v1/board?team_id={$this->core->id}&release_id=none")
            ->assertJsonPath('data.filters.team_id', $this->core->id)
            ->assertJsonPath('data.filters.release_id', 'none');

        $this->getJson("/api/v1/tasks?team_id={$this->growth->id}")->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Growth loose')
            ->assertJsonPath('data.0.team.name', 'Growth');
    }

    public function test_a_multi_team_member_filters_among_their_own_teams(): void
    {
        $other = Team::create(['name' => 'Payments', 'color' => '#7c3aed']);
        $this->task('Core linked', $this->coreRelease);
        $this->task('Growth loose', team: $this->growth);
        $this->task('Payments loose', team: $other);
        $this->task('Teamless');

        foreach ([User::ROLE_DEVELOPER, User::ROLE_QA] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->core->members()->attach($user);
            $this->growth->members()->attach($user);

            $this->actingAs($user)->get(route('board.index'))
                ->assertOk()
                ->assertSee('Core linked')
                ->assertSee('Growth loose')
                ->assertDontSee('Payments') // not their team: neither its tasks nor the option
                ->assertDontSee('Teamless')
                // No team filter set: quick-add asks which of their teams the card is for.
                ->assertSee('name="team_id" required', false);

            $this->actingAs($user)->get(route('board.index', ['team_id' => $this->growth->id]))
                ->assertOk()
                ->assertSee('Growth loose')
                ->assertDontSee('Core linked')
                ->assertSee('<option value="'.$this->growth->id.'" selected>Growth</option>', false)
                ->assertSee('<input type="hidden" name="team_id" value="'.$this->growth->id.'">', false);

            // Asking for another team's tasks returns none of them.
            $this->actingAs($user)->get(route('board.index', ['team_id' => $other->id]))
                ->assertOk()
                ->assertDontSee('Payments loose');
        }
    }

    // ── How a task gets its team ───────────────────────────────────────────

    public function test_quick_add_takes_the_release_team_or_the_chosen_team(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('board.tasks.store'), [
            'title' => 'Linked', 'status' => 'todo', 'release_id' => $this->coreRelease->id, 'team_id' => $this->growth->id,
        ])->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('board.tasks.store'), [
            'title' => 'Loose', 'status' => 'todo', 'team_id' => $this->growth->id,
        ])->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('board.tasks.store'), ['title' => 'Bare', 'status' => 'todo']);

        $this->assertSame($this->core->id, Task::where('title', 'Linked')->value('team_id'));
        $this->assertSame($this->growth->id, Task::where('title', 'Loose')->value('team_id'));
        $this->assertNull(Task::where('title', 'Bare')->value('team_id'));
    }

    public function test_api_create_defaults_the_team_from_a_single_team_assignee(): void
    {
        $single = User::factory()->create();
        $this->growth->members()->attach($single);
        $multi = User::factory()->create();
        $this->core->members()->attach($multi);
        $this->growth->members()->attach($multi);

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_TEAM_LEAD]));

        $this->postJson('/api/v1/tasks', ['title' => 'A', 'assignee_id' => $single->id])
            ->assertCreated()->assertJsonPath('data.team_id', $this->growth->id)
            ->assertJsonPath('data.team.name', 'Growth');
        $this->postJson('/api/v1/tasks', ['title' => 'B', 'assignee_id' => $multi->id])
            ->assertCreated()->assertJsonPath('data.team_id', null);
        $this->postJson('/api/v1/tasks', ['title' => 'C', 'assignee_id' => $single->id, 'team_id' => $this->core->id])
            ->assertCreated()->assertJsonPath('data.team_id', $this->core->id);
        $this->postJson('/api/v1/board/tasks', ['title' => 'D', 'status' => 'todo', 'team_id' => $this->core->id])
            ->assertCreated()->assertJsonPath('data.team_id', $this->core->id);
    }

    public function test_linking_sets_the_release_team_unlinking_keeps_it_and_subtasks_follow(): void
    {
        $user = User::factory()->create();
        $task = $this->task('Loose', team: $this->growth);
        $sub = Task::create(['parent_id' => $task->id, 'team_id' => $this->growth->id, 'title' => 'Sub', 'status' => 'todo']);

        // Link: the release's team wins over any team sent.
        $this->actingAs($user)->put(route('tasks.update', $task), [
            'title' => 'Loose', 'status' => 'todo', 'release_id' => $this->coreRelease->id, 'team_id' => $this->growth->id,
        ])->assertSessionHasNoErrors();
        $this->assertSame($this->core->id, $task->fresh()->team_id);
        $this->assertSame($this->core->id, $sub->fresh()->team_id);

        // Unlink without a team field (the page shows the team read-only while linked): keeps the team.
        $this->actingAs($user)->put(route('tasks.update', $task), [
            'title' => 'Loose', 'status' => 'todo', 'release_id' => '',
        ])->assertSessionHasNoErrors();
        $this->assertNull($task->fresh()->release_id);
        $this->assertSame($this->core->id, $task->fresh()->team_id);

        // Unlinked: the team is editable, and clearable.
        $this->actingAs($user)->get(route('tasks.show', $task))->assertOk()->assertSee('name="team_id"', false);
        $this->actingAs($user)->put(route('tasks.update', $task), [
            'title' => 'Loose', 'status' => 'todo', 'release_id' => '', 'team_id' => '',
        ])->assertSessionHasNoErrors();
        $this->assertNull($task->fresh()->team_id);
        $this->assertNull($sub->fresh()->team_id);

        // A subtask created now inherits its parent's team (none).
        $this->actingAs($user)->post(route('tasks.subtasks.store', $task), ['title' => 'New sub', 'team_id' => $this->core->id]);
        $this->assertNull(Task::where('title', 'New sub')->value('team_id'));
    }

    public function test_linked_task_team_follows_its_release_to_another_team(): void
    {
        $task = $this->task('Linked', $this->coreRelease);
        $loose = $this->task('Loose', team: $this->core);

        $this->coreRelease->update(['team_id' => $this->growth->id]);

        $this->assertSame($this->growth->id, $task->fresh()->team_id);
        $this->assertSame($this->core->id, $loose->fresh()->team_id);
    }

    public function test_tasksheet_add_to_board_uses_the_rows_team(): void
    {
        $this->app->instance(PlanTaskExtractor::class, new class implements PlanTaskExtractor
        {
            public function extract(string $plan, ?string $tickets = null, array $existing = []): array
            {
                return ['tasks' => [['title' => 'Planned', 'description' => null]], 'covered' => 0];
            }
        });
        $dev = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $this->growth->members()->attach($dev);

        $this->actingAs($dev)->put(route('tasksheet.entries.board'), [
            'team_id' => $this->growth->id, 'user_id' => $dev->id, 'date' => today()->toDateString(),
            'plan' => '<div>Planned</div>', 'board_release_id' => '',
        ])->assertSessionHasNoErrors();

        $task = Task::sole();
        $this->assertNull($task->release_id);
        $this->assertSame($this->growth->id, $task->team_id);
    }
}
