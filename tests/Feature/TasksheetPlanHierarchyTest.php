<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Release;
use App\Models\Task;
use App\Models\TasksheetEntry;
use App\Models\Team;
use App\Models\User;
use App\Services\Ai\AiTransport;
use App\Services\Board\ClaudePlanTaskExtractor;
use App\Services\Board\PlanTaskExtractor;
use App\Services\Board\PlanToBoardService;
use App\Services\Kpi\MissingApiKeyException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FakeAiTransport;
use Tests\TestCase;

/**
 * "Add to board" keeps the plan's main tasks and subtasks. Most tests fake the
 * extractor, because what they check is what happens to its answer.
 */
class TasksheetPlanHierarchyTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    private Release $release;

    private User $dev;

    private User $teammate;

    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->team = Team::create(['name' => 'Core', 'color' => '#0891b2']);
        $this->dev = User::factory()->create(['role' => User::ROLE_DEVELOPER, 'name' => 'Devin Dev']);
        $this->teammate = User::factory()->create(['role' => User::ROLE_DEVELOPER, 'name' => 'Tara Mate']);
        $this->team->members()->attach([$this->dev->id, $this->teammate->id]);

        $project = Project::create(['name' => 'Checkout', 'color' => '#4f46e5']);
        $this->release = Release::create([
            'project_id' => $project->id, 'team_id' => $this->team->id, 'name' => 'Checkout v2.5',
            'year' => (int) today()->year, 'quarter' => (int) today()->quarter,
            'start_date' => today()->subDays(5)->toDateString(), 'end_date' => today()->addDays(20)->toDateString(),
        ]);
    }

    /** A main task as the AI returns it; subtasks are given by title. */
    private function main(string $title, array $subtasks = []): array
    {
        return [
            'title' => $title,
            'description' => null,
            'subtasks' => array_map(fn (string $s) => ['title' => $s, 'description' => null], $subtasks),
        ];
    }

    private function fakeAi(array $tasks, int $covered = 0, ?\Throwable $error = null): void
    {
        $this->app->instance(PlanTaskExtractor::class, new class($tasks, $covered, $error, $this->sent) implements PlanTaskExtractor
        {
            public function __construct(private array $tasks, private int $covered, private ?\Throwable $error, private array &$sent) {}

            public function extract(string $plan, ?string $tickets = null, array $existing = [], array $teamTasks = []): array
            {
                $this->sent = compact('plan', 'existing', 'teamTasks');
                if ($this->error) {
                    throw $this->error;
                }

                return ['tasks' => ClaudePlanTaskExtractor::normalize($this->tasks), 'covered' => $this->covered];
            }
        });
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'team_id' => $this->team->id,
            'user_id' => $this->dev->id,
            'date' => today()->toDateString(),
            'plan' => '<ul><li>Build refund flow<ul><li>Add refund API</li><li>Write refund tests</li></ul></li></ul>',
            'board_release_id' => $this->release->id,
        ];
    }

    private function press(array $overrides = [], ?User $as = null)
    {
        return $this->actingAs($as ?? $this->dev)->put(route('tasksheet.entries.board'), $this->payload($overrides));
    }

    private function openTask(string $title, ?User $assignee, array $attrs = []): Task
    {
        return Task::create($attrs + [
            'release_id' => $this->release->id, 'team_id' => $this->team->id, 'title' => $title,
            'status' => 'todo', 'assignee_id' => $assignee?->id,
        ]);
    }

    public function test_a_main_task_and_its_subtasks_are_added_together(): void
    {
        $lead = User::factory()->create(['role' => User::ROLE_TEAM_LEAD, 'name' => 'Lee Lead']);
        $this->openTask('Earlier card', $this->teammate, ['position' => 0]);
        $this->fakeAi([
            ['title' => 'Build refund flow', 'description' => 'The whole flow', 'subtasks' => [
                ['title' => 'Add refund API', 'description' => 'POST /refunds'],
                ['title' => 'Write refund tests', 'description' => null],
            ]],
            $this->main('Update API docs'),
        ]);

        $this->press(as: $lead)
            ->assertSessionHas('success', 'Tasksheet saved. Added 2 tasks and 2 subtasks to the board for Devin Dev.');

        $main = Task::where('title', 'Build refund flow')->sole();
        $this->assertNull($main->parent_id);
        $this->assertSame('The whole flow', $main->description);
        $this->assertSame(1, $main->position);
        $this->assertSame(2, Task::where('title', 'Update API docs')->value('position'));

        $subtasks = $main->subtasks;
        $this->assertSame(['Add refund API', 'Write refund tests'], $subtasks->pluck('title')->all());
        $this->assertSame([0, 1], $subtasks->pluck('position')->all());
        $this->assertSame('POST /refunds', $subtasks[0]->description);
        foreach ($subtasks as $subtask) {
            $this->assertSame('todo', $subtask->status);
            $this->assertSame($this->dev->id, $subtask->assignee_id);
            $this->assertSame($lead->id, $subtask->created_by);
            $this->assertSame($this->release->id, $subtask->release_id);
            $this->assertSame($this->team->id, $subtask->team_id);
        }

        // One grouped notification for the whole batch, subtasks included.
        $this->assertSame(1, $this->dev->notifications()->count());
        $this->assertSame('Lee Lead assigned you 4 tasks', $this->dev->notifications()->first()->data['message']);
    }

    public function test_the_hierarchy_shows_on_the_board_and_the_task_page(): void
    {
        $this->fakeAi([$this->main('Build refund flow', ['Add refund API', 'Write refund tests'])]);
        $this->press();
        $main = Task::where('title', 'Build refund flow')->sole();

        $this->actingAs($this->dev)->get(route('board.index', ['release_id' => $this->release->id]))
            ->assertOk()
            ->assertSee('Build refund flow')
            ->assertSee('0 of 2 subtasks done')
            ->assertDontSee('Add refund API'); // a subtask is not a card

        $this->actingAs($this->dev)->get(route('tasks.show', $main))
            ->assertOk()
            ->assertSee('Add refund API')
            ->assertSee('Write refund tests');

        $this->actingAs($this->dev)->get(route('tasks.show', $main->subtasks->first()))
            ->assertOk()
            ->assertSee('Subtask')
            ->assertSee('Build refund flow');
    }

    public function test_new_subtasks_attach_to_the_members_open_main_task(): void
    {
        $main = $this->openTask('Build refund flow', $this->dev, ['status' => 'in_progress']);
        $main->subtasks()->create(['title' => 'Add refund API', 'status' => 'todo', 'assignee_id' => $this->dev->id, 'position' => 0]);

        $this->fakeAi([$this->main(' build REFUND flow', ['Write refund tests'])], covered: 1);
        $this->press()
            ->assertSessionHas('success', 'Tasksheet saved. Added 1 subtask to the board for Devin Dev. Skipped 2 already on the board.');

        $this->assertSame(1, Task::whereNull('parent_id')->count());
        $this->assertSame('in_progress', $main->fresh()->status);
        $this->assertSame(['Add refund API', 'Write refund tests'], $main->subtasks()->pluck('title')->all());
        $this->assertSame([
            ['title' => 'Build refund flow', 'subtasks' => ['Add refund API']],
        ], $this->sent['existing']);
    }

    public function test_new_subtasks_attach_to_a_teammates_main_task_without_taking_it_over(): void
    {
        $main = $this->openTask('Build refund flow', $this->teammate);

        $this->fakeAi([$this->main('Build refund flow', ['Write refund tests'])]);
        $this->press()
            ->assertSessionHas('success', 'Tasksheet saved. Added 1 subtask to the board for Devin Dev. Skipped 1 already on the board.');

        $this->assertSame(['Build refund flow'], $this->sent['teamTasks']);
        $this->assertSame($this->teammate->id, $main->fresh()->assignee_id);

        $subtask = $main->subtasks()->sole();
        $this->assertSame($this->dev->id, $subtask->assignee_id);

        // Next press: that main task now counts as the member's own work.
        $this->fakeAi([$this->main('Build refund flow', ['Write refund tests'])]);
        $this->press()->assertSessionHas('success', 'Tasksheet saved. Those tasks are already on the board for Devin Dev.');
        $this->assertSame([['title' => 'Build refund flow', 'subtasks' => ['Write refund tests']]], $this->sent['existing']);
        $this->assertSame([], $this->sent['teamTasks']);
        $this->assertSame(2, Task::count());
    }

    public function test_a_teammates_task_of_the_same_name_is_not_a_duplicate_of_a_plain_line(): void
    {
        $this->openTask('Update API docs', $this->teammate);

        $this->fakeAi([$this->main('Update API docs')]);
        $this->press()->assertSessionHas('success', 'Tasksheet saved. Added 1 task to the board for Devin Dev.');

        $this->assertSame(2, Task::whereNull('parent_id')->where('title', 'Update API docs')->count());
    }

    public function test_the_main_task_is_created_when_it_is_not_on_the_board(): void
    {
        $this->fakeAi([$this->main('Build refund flow', ['Write refund tests'])]);
        $this->press()->assertSessionHas('success', 'Tasksheet saved. Added 1 task and 1 subtask to the board for Devin Dev.');

        $main = Task::whereNull('parent_id')->sole();
        $this->assertSame('Build refund flow', $main->title);
        $this->assertSame($this->dev->id, $main->assignee_id);
        $this->assertSame(['Write refund tests'], $main->subtasks->pluck('title')->all());
    }

    public function test_a_done_or_archived_main_task_is_not_attached_to(): void
    {
        foreach (['done', 'archive'] as $status) {
            $this->openTask('Build refund flow', $this->dev, ['status' => $status]);
            $this->openTask('Build refund flow', $this->teammate, ['status' => $status]);
        }

        $this->fakeAi([$this->main('Build refund flow', ['Write refund tests'])]);
        $this->press()->assertSessionHas('success', 'Tasksheet saved. Added 1 task and 1 subtask to the board for Devin Dev.');

        $this->assertSame([], $this->sent['existing']);
        $this->assertSame([], $this->sent['teamTasks']);
        $new = Task::whereNull('parent_id')->where('status', 'todo')->sole();
        $this->assertSame(['Write refund tests'], $new->subtasks->pluck('title')->all());
    }

    public function test_pressing_twice_creates_nothing_new(): void
    {
        $answer = [$this->main('Build refund flow', ['Add refund API', 'Write refund tests'])];

        $this->fakeAi($answer);
        $this->press();

        // Even if the AI repeats everything instead of leaving it out.
        $this->fakeAi($answer);
        $this->press()->assertSessionHas('success', 'Tasksheet saved. Those tasks are already on the board for Devin Dev.');

        $this->assertSame(3, Task::count());
        $this->assertSame([
            ['title' => 'Build refund flow', 'subtasks' => ['Add refund API', 'Write refund tests']],
        ], $this->sent['existing']);
    }

    public function test_a_finished_subtask_under_an_open_main_task_is_not_created_again(): void
    {
        $answer = [$this->main('Build refund flow', ['Add refund API', 'Write refund tests'])];
        $this->fakeAi($answer);
        $this->press();
        Task::where('title', 'Add refund API')->update(['status' => 'done']);

        $this->fakeAi($answer);
        $this->press()->assertSessionHas('success', 'Tasksheet saved. Those tasks are already on the board for Devin Dev.');

        $this->assertSame(3, Task::count());
        $this->assertContains('Add refund API', $this->sent['existing'][0]['subtasks']);
    }

    public function test_a_flat_card_from_before_is_skipped_and_left_where_it_is(): void
    {
        $flat = $this->openTask('Write refund tests', $this->dev);

        $this->fakeAi([$this->main('Build refund flow', ['Write refund tests', 'Add refund API'])]);
        $this->press()->assertSessionHas('success', 'Tasksheet saved. Added 1 task and 1 subtask to the board for Devin Dev. Skipped 1 already on the board.');

        $this->assertNull($flat->fresh()->parent_id);
        $this->assertSame(['Add refund API'], Task::where('title', 'Build refund flow')->sole()->subtasks->pluck('title')->all());
    }

    public function test_a_plain_line_matching_an_existing_subtask_is_skipped(): void
    {
        $main = $this->openTask('Build refund flow', $this->dev);
        $main->subtasks()->create(['title' => 'Write refund tests', 'status' => 'todo', 'assignee_id' => $this->dev->id, 'position' => 0]);

        $this->fakeAi([$this->main('Write refund tests')]);
        $this->press()->assertSessionHas('success', 'Tasksheet saved. Those tasks are already on the board for Devin Dev.');

        $this->assertSame(2, Task::count());
    }

    public function test_items_under_an_existing_subtask_go_to_its_parent(): void
    {
        $main = $this->openTask('Build refund flow', $this->dev);
        $main->subtasks()->create(['title' => 'Write refund tests', 'status' => 'todo', 'assignee_id' => $this->dev->id, 'position' => 0]);

        $this->fakeAi([$this->main('Write refund tests', ['Cover partial refunds'])]);
        $this->press()->assertSessionHas('success', 'Tasksheet saved. Added 1 subtask to the board for Devin Dev. Skipped 1 already on the board.');

        $this->assertSame(['Write refund tests', 'Cover partial refunds'], $main->subtasks()->pluck('title')->all());
        $this->assertSame(0, Task::whereIn('parent_id', $main->subtasks()->pluck('id'))->count());
    }

    public function test_duplicates_are_judged_per_parent(): void
    {
        $this->fakeAi([
            $this->main('Build refund flow', ['Write tests', ' write  TESTS', 'Build refund flow']),
            $this->main('Build coupon flow', ['Write tests']),
            $this->main('Build refund flow', ['Add refund API']), // the same main again in one answer
        ]);
        $this->press()->assertSessionHas('success', 'Tasksheet saved. Added 2 tasks and 3 subtasks to the board for Devin Dev. Skipped 3 already on the board.');

        $this->assertSame(['Write tests', 'Add refund API'], Task::where('title', 'Build refund flow')->sole()->subtasks->pluck('title')->all());
        $this->assertSame(['Write tests'], Task::where('title', 'Build coupon flow')->sole()->subtasks->pluck('title')->all());
    }

    public function test_another_teams_task_is_never_a_parent(): void
    {
        $other = Team::create(['name' => 'Other', 'color' => '#e11d48']);
        Task::create(['team_id' => $other->id, 'title' => 'Build refund flow', 'status' => 'todo']);
        $unlinkedHere = $this->openTask('Build coupon flow', null, ['release_id' => null]);

        $this->fakeAi([
            $this->main('Build refund flow', ['Write refund tests']),
            $this->main('Build coupon flow', ['Write coupon tests']),
        ]);
        $this->press(['board_release_id' => ''])
            ->assertSessionHas('success', 'Tasksheet saved. Added 1 task and 2 subtasks to the board for Devin Dev. Skipped 1 already on the board.');

        $this->assertSame(['Build coupon flow'], $this->sent['teamTasks']);
        $this->assertSame(['Write coupon tests'], $unlinkedHere->subtasks()->pluck('title')->all());
        $this->assertSame($this->team->id, Task::where('title', 'Write refund tests')->sole()->team_id);
    }

    public function test_a_task_the_actor_cannot_see_is_not_offered_as_a_parent(): void
    {
        // Another release's task never counts, and neither does one out of a limited user's view.
        $otherRelease = Release::create([
            'project_id' => $this->release->project_id, 'team_id' => $this->team->id, 'name' => 'Other',
            'year' => (int) today()->year, 'quarter' => (int) today()->quarter,
            'start_date' => today()->toDateString(), 'end_date' => today()->addDays(9)->toDateString(),
        ]);
        Task::create(['release_id' => $otherRelease->id, 'team_id' => $this->team->id, 'title' => 'Other release task', 'status' => 'todo', 'assignee_id' => $this->teammate->id]);
        $hidden = Team::create(['name' => 'Hidden', 'color' => '#e11d48']);
        $hiddenTask = $this->openTask('Hidden team task', $this->teammate);
        Task::whereKey($hiddenTask->id)->toBase()->update(['team_id' => $hidden->id]); // the model would reset it

        $this->fakeAi([]);
        $this->press();

        $this->assertSame([], $this->sent['teamTasks']);
    }

    public function test_plain_text_keeps_nested_lists_and_leaves_flat_text_alone(): void
    {
        $this->assertSame(
            "- Main A\n  - Sub 1\n  - Sub 2\n- Main B",
            PlanToBoardService::plainText('<ul><li>Main A<ul><li>Sub 1</li><li>Sub 2</li></ul></li><li>Main B</li></ul>'),
        );
        $this->assertSame(
            "- One\n  - Two\n    - Three\n- Four",
            PlanToBoardService::plainText('<ol><li>One<ol><li>Two<ul><li>Three</li></ul></li></ol></li><li>Four</li></ol>'),
        );
        $this->assertSame(
            "Checkout\n- Fix coupon rounding\n- Write refund tests",
            PlanToBoardService::plainText('<div>Checkout</div><ul><li>Fix coupon rounding</li><li>Write refund tests</li></ul>'),
        );
        $this->assertSame("Line one\nLine two", PlanToBoardService::plainText('<div>Line one<br>Line two</div>'));
    }

    public function test_the_ai_is_asked_for_subtasks_and_shown_the_hierarchy(): void
    {
        $transport = (new FakeAiTransport)->respond([
            'tasks' => [$this->main('Build refund flow', ['Write refund tests'])],
            'covered_by_existing' => 0,
        ]);
        $this->app->instance(AiTransport::class, $transport);
        config(['services.anthropic.key' => 'test-key']);

        $main = $this->openTask('Harden checkout', $this->dev);
        $main->subtasks()->create(['title' => 'Add rate limit', 'status' => 'done', 'position' => 0]);
        $this->openTask('Teammate work', $this->teammate);

        $this->press()->assertSessionHas('success', 'Tasksheet saved. Added 1 task and 1 subtask to the board for Devin Dev.');

        $request = $transport->requests[0];
        $this->assertStringContainsString("- Build refund flow\n  - Add refund API\n  - Write refund tests", $request['content']);
        $this->assertStringContainsString("<existing_tasks>\n- Harden checkout\n  - Add rate limit\n</existing_tasks>", $request['content']);
        $this->assertStringContainsString("<team_tasks>\n- Teammate work\n</team_tasks>", $request['content']);

        $item = $request['schema']['properties']['tasks']['items'];
        $this->assertContains('subtasks', $item['required']);
        $this->assertSame(['title', 'description'], $item['properties']['subtasks']['items']['required']);
    }

    public function test_subtasks_are_capped_and_cleaned(): void
    {
        $tasks = ClaudePlanTaskExtractor::normalize([[
            'title' => ' Main ',
            'description' => ' ',
            'subtasks' => array_merge(
                [['title' => '  ', 'description' => null], 'not an item', ['title' => str_repeat('x', 400), 'description' => '  ']],
                array_fill(0, 40, ['title' => 'Step', 'description' => 'Why', 'subtasks' => [['title' => 'Too deep']]]),
            ),
        ], ['title' => 'No subtasks key', 'description' => null]]);

        $this->assertSame('Main', $tasks[0]['title']);
        $this->assertCount(ClaudePlanTaskExtractor::MAX_SUBTASKS, $tasks[0]['subtasks']);
        $this->assertSame(255, mb_strlen($tasks[0]['subtasks'][0]['title']));
        $this->assertSame(['title' => 'Step', 'description' => 'Why'], $tasks[0]['subtasks'][1]);
        $this->assertSame([], $tasks[1]['subtasks']);
    }

    public function test_the_web_and_the_api_build_the_same_hierarchy(): void
    {
        $answer = [$this->main('Build refund flow', ['Add refund API', 'Write refund tests']), $this->main('Update API docs')];
        $board = fn () => Task::whereNull('parent_id')->orderBy('position')->get()
            ->map(fn (Task $t) => $t->only(['title', 'status', 'release_id', 'team_id', 'assignee_id', 'created_by', 'position']) + [
                'subtasks' => $t->subtasks->map(fn (Task $s) => $s->only(['title', 'status', 'release_id', 'team_id', 'assignee_id', 'created_by', 'position']))->all(),
            ])->all();

        $this->fakeAi($answer);
        $webMessage = $this->press()->assertRedirect()->getSession()->get('success');
        $webBoard = $board();
        Task::query()->forceDelete();

        $this->fakeAi($answer);
        Sanctum::actingAs($this->dev);
        $api = $this->putJson('/api/v1/tasksheet/entries/board', $this->payload())
            ->assertOk()
            ->assertJsonPath('data.tasks_created', 2)
            ->assertJsonPath('data.subtasks_created', 2)
            ->assertJsonPath('data.skipped', 0)
            ->assertJsonPath('data.entry.user_id', $this->dev->id)
            ->assertJsonCount(4, 'data.tasks');

        $this->assertSame($webMessage, $api->json('message'));
        $this->assertSame($webBoard, $board());
        $this->assertSame(1, TasksheetEntry::where('user_id', $this->dev->id)->count());

        $parent = Task::where('title', 'Build refund flow')->sole();
        $subtask = collect($api->json('data.tasks'))->firstWhere('title', 'Add refund API');
        $this->assertTrue($subtask['is_subtask']);
        $this->assertSame($parent->id, $subtask['parent_id']);

        // Pressing again through the API skips what the web created, and vice versa.
        $this->fakeAi($answer);
        $this->putJson('/api/v1/tasksheet/entries/board', $this->payload())
            ->assertOk()
            ->assertJsonPath('data.tasks', [])
            ->assertJsonPath('message', 'Tasksheet saved. Those tasks are already on the board for Devin Dev.');
    }

    public function test_the_api_applies_the_same_rules(): void
    {
        $this->fakeAi([$this->main('Anything')]);

        Sanctum::actingAs($this->teammate);
        $this->putJson('/api/v1/tasksheet/entries/board', $this->payload())->assertForbidden();

        Sanctum::actingAs($this->dev);
        $this->putJson('/api/v1/tasksheet/entries/board', $this->payload(['plan' => '<div><br></div>']))
            ->assertUnprocessable()->assertJsonValidationErrors('plan');

        $this->assertSame(0, Task::count());
    }

    public function test_an_ai_failure_on_the_api_saves_the_row_and_says_so(): void
    {
        $this->fakeAi([], error: new MissingApiKeyException('ANTHROPIC_API_KEY is not set.'));

        Sanctum::actingAs($this->dev);
        $this->putJson('/api/v1/tasksheet/entries/board', $this->payload(['work_points' => 3]))
            ->assertStatus(503)
            ->assertJsonPath('message', 'Tasksheet saved, but no tasks were added: AI isn’t configured (ANTHROPIC_API_KEY).')
            ->assertJsonPath('data.entry.work_points', 3)
            ->assertJsonPath('data.tasks', []);

        $this->assertSame(0, Task::count());
    }

    public function test_the_drawer_says_indented_lines_become_subtasks(): void
    {
        $this->actingAs($this->dev)->get(route('tasksheet.index', ['team' => $this->team->id]))
            ->assertOk()
            ->assertSee('Indented lines become subtasks.');
    }
}
