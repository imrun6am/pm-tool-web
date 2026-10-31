<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Release;
use App\Models\Task;
use App\Models\TasksheetEntry;
use App\Models\Team;
use App\Models\User;
use App\Services\Board\ClaudePlanTaskExtractor;
use App\Services\Board\PlanTaskExtractor;
use App\Services\Kpi\MissingApiKeyException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TasksheetPlanToBoardTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    private Release $release;

    private User $dev;

    /** What the fake extractor last received. */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->team = Team::create(['name' => 'Core', 'color' => '#0891b2']);
        $this->dev = User::factory()->create(['role' => User::ROLE_DEVELOPER, 'name' => 'Devin Dev']);
        $this->team->members()->attach($this->dev);
        $this->release = $this->release($this->team, 'Checkout v2.5');
    }

    private function release(Team $team, string $name, array $attrs = []): Release
    {
        $project = Project::firstOrCreate(['name' => 'Checkout'], ['color' => '#4f46e5']);

        return Release::create($attrs + [
            'project_id' => $project->id, 'team_id' => $team->id, 'name' => $name,
            'year' => (int) today()->year, 'quarter' => (int) today()->quarter,
            'start_date' => today()->subDays(5)->toDateString(), 'end_date' => today()->addDays(20)->toDateString(),
        ]);
    }

    /** Swap Claude for a fake that returns $tasks (or throws $error). */
    private function fakeClaude(array $tasks = [], ?\Throwable $error = null, int $covered = 0): void
    {
        $this->app->instance(PlanTaskExtractor::class, new class($tasks, $error, $covered, $this->sent) implements PlanTaskExtractor
        {
            public function __construct(private array $tasks, private ?\Throwable $error, private int $covered, private array &$sent) {}

            public function extract(string $plan, ?string $tickets = null, array $existing = []): array
            {
                $this->sent = ['plan' => $plan, 'tickets' => $tickets, 'existing' => $existing];
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
            'plan' => '<div>Checkout</div><ul><li>Fix coupon rounding</li><li>Write refund tests</li></ul>',
            'tickets' => '<div>PAY-142</div>',
            'work_points' => 3,
            'board_release_id' => $this->release->id,
        ];
    }

    public function test_plan_becomes_todo_tasks_assigned_to_the_member(): void
    {
        $lead = User::factory()->create(['role' => User::ROLE_TEAM_LEAD]);
        $this->fakeClaude([
            ['title' => 'Fix coupon rounding (PAY-142)', 'description' => null],
            ['title' => 'Write refund tests', 'description' => 'Cover partial refunds'],
        ]);

        $this->actingAs($lead)->put(route('tasksheet.entries.board'), $this->payload())
            ->assertRedirect(route('tasksheet.index', ['team' => $this->team->id, 'date' => today()->toDateString()]))
            ->assertSessionHas('success', 'Tasksheet saved. Added 2 tasks to the board for Devin Dev.');

        // The row is saved like "Save row" would.
        $entry = TasksheetEntry::where('user_id', $this->dev->id)->firstOrFail();
        $this->assertSame(3, $entry->work_points);

        // Claude received plain text: one line per block, list items as bullets.
        $this->assertSame("Checkout\n- Fix coupon rounding\n- Write refund tests", $this->sent['plan']);
        $this->assertSame('PAY-142', $this->sent['tickets']);

        $tasks = Task::orderBy('position')->get();
        $this->assertCount(2, $tasks);
        foreach ($tasks as $task) {
            $this->assertSame('todo', $task->status);
            $this->assertSame($this->dev->id, $task->assignee_id);
            $this->assertSame($lead->id, $task->created_by);
            $this->assertSame($this->release->id, $task->release_id);
            $this->assertNull($task->parent_id);
        }
        $this->assertSame('Cover partial refunds', $tasks[1]->description);
        $this->assertSame([0, 1], $tasks->pluck('position')->all());
    }

    public function test_pressing_twice_does_not_duplicate_tasks(): void
    {
        $this->fakeClaude([
            ['title' => 'Fix coupon rounding', 'description' => null],
            ['title' => '  fix   COUPON rounding ', 'description' => null], // same item twice in one batch
            ['title' => 'Write refund tests', 'description' => null],
        ]);

        $this->actingAs($this->dev)->put(route('tasksheet.entries.board'), $this->payload())
            ->assertSessionHas('success', 'Tasksheet saved. Added 2 tasks to the board for Devin Dev. Skipped 1 already on the board.');

        $this->actingAs($this->dev)->put(route('tasksheet.entries.board'), $this->payload())
            ->assertSessionHas('success', 'Tasksheet saved. Those tasks are already on the board for Devin Dev.');

        $this->assertSame(2, Task::count());
    }

    public function test_editing_the_plan_and_adding_again_only_adds_new_work(): void
    {
        // First press: two tasks.
        $this->fakeClaude([
            ['title' => 'Fix coupon rounding', 'description' => null],
            ['title' => 'Write refund tests', 'description' => null],
        ]);
        $this->actingAs($this->dev)->put(route('tasksheet.entries.board'), $this->payload());
        Task::where('title', 'Write refund tests')->update(['status' => 'done']); // finished since

        // The plan is edited (one new line) and pressed again. Claude sees every
        // existing task — done ones too — and returns only the new item, having
        // matched the two old lines by meaning.
        $this->fakeClaude([['title' => 'Update API docs', 'description' => null]], covered: 2);
        $this->actingAs($this->dev)
            ->put(route('tasksheet.entries.board'), $this->payload([
                'plan' => '<ul><li>Fix rounding of coupons</li><li>Refund tests</li><li>Update API docs</li></ul>',
            ]))
            ->assertSessionHas('success', 'Tasksheet saved. Added 1 task to the board for Devin Dev. Skipped 2 already on the board.');

        $this->assertEqualsCanonicalizing(['Fix coupon rounding', 'Write refund tests'], $this->sent['existing']);
        $this->assertSame(3, Task::count());
        $this->assertSame(1, TasksheetEntry::count()); // the same row, saved again
    }

    public function test_exact_titles_are_never_recreated_even_if_claude_repeats_them(): void
    {
        $this->fakeClaude([['title' => 'Fix coupon rounding', 'description' => null]]);
        $this->actingAs($this->dev)->put(route('tasksheet.entries.board'), $this->payload());
        Task::query()->update(['status' => 'archive']);

        // Safety net: Claude returns an existing title anyway (any status counts).
        $this->fakeClaude([['title' => 'FIX coupon rounding ', 'description' => null]]);
        $this->actingAs($this->dev)->put(route('tasksheet.entries.board'), $this->payload())
            ->assertSessionHas('success', 'Tasksheet saved. Those tasks are already on the board for Devin Dev.');

        $this->assertSame(1, Task::count());
    }

    public function test_only_the_members_own_tasks_count_as_existing(): void
    {
        $other = User::factory()->create();
        Task::create(['release_id' => $this->release->id, 'title' => 'Someone else’s task', 'status' => 'todo', 'assignee_id' => $other->id]);
        Task::create(['release_id' => $this->release->id, 'title' => 'My old task', 'status' => 'done', 'assignee_id' => $this->dev->id]);
        $this->fakeClaude([]);

        $this->actingAs($this->dev)->put(route('tasksheet.entries.board'), $this->payload());

        $this->assertSame(['My old task'], $this->sent['existing']);
    }

    public function test_release_must_be_an_open_release_of_the_team(): void
    {
        $this->fakeClaude([['title' => 'Anything', 'description' => null]]);
        $other = $this->release(Team::create(['name' => 'Other', 'color' => '#e11d48']), 'Other team release');
        $done = $this->release($this->team, 'Shipped', ['completed_at' => now()]);

        foreach ([$other, $done] as $release) {
            $this->actingAs($this->dev)
                ->put(route('tasksheet.entries.board'), $this->payload(['board_release_id' => $release->id]))
                ->assertSessionHasErrors('board_release_id');
        }

        $this->assertSame(0, Task::count());
    }

    public function test_empty_plan_and_full_day_leave_are_rejected(): void
    {
        $this->fakeClaude([['title' => 'Anything', 'description' => null]]);

        $this->actingAs($this->dev)->put(route('tasksheet.entries.board'), $this->payload(['plan' => '<div><br></div>']))
            ->assertSessionHasErrors('plan');

        $this->actingAs($this->dev)->put(route('tasksheet.entries.board'), $this->payload(['leave_type' => 'sick']))
            ->assertSessionHasErrors('leave_type');

        $this->assertSame(0, Task::count());
        $this->assertSame([], $this->sent);
    }

    public function test_a_teammate_cannot_add_tasks_for_someone_else(): void
    {
        $this->fakeClaude([['title' => 'Anything', 'description' => null]]);
        $teammate = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $this->team->members()->attach($teammate);

        $this->actingAs($teammate)->put(route('tasksheet.entries.board'), $this->payload())->assertForbidden();

        $this->assertSame(0, Task::count());
    }

    public function test_claude_failure_still_saves_the_row(): void
    {
        $this->fakeClaude(error: new MissingApiKeyException('ANTHROPIC_API_KEY is not set.'));

        $this->actingAs($this->dev)->put(route('tasksheet.entries.board'), $this->payload())
            ->assertSessionHas('error', 'Tasksheet saved, but no tasks were added: Claude isn’t configured (ANTHROPIC_API_KEY).');
        $this->assertSame(3, TasksheetEntry::where('user_id', $this->dev->id)->value('work_points'));

        $this->fakeClaude(error: new \RuntimeException('Claude returned an unreadable response.'));
        $this->actingAs($this->dev)->put(route('tasksheet.entries.board'), $this->payload())
            ->assertSessionHas('error', 'Tasksheet saved, but no tasks were added: Claude couldn’t split the plan right now. Please try again.');

        $this->assertSame(0, Task::count());
    }

    public function test_extractor_output_is_capped_and_cleaned(): void
    {
        $tasks = ClaudePlanTaskExtractor::normalize(array_merge(
            [['title' => '', 'description' => 'no title'], ['title' => str_repeat('x', 400), 'description' => '  ']],
            array_fill(0, 40, ['title' => 'Task', 'description' => null]),
        ));

        $this->assertCount(ClaudePlanTaskExtractor::MAX_TASKS, $tasks);
        $this->assertSame(255, mb_strlen($tasks[0]['title']));
        $this->assertNull($tasks[0]['description']);
    }

    public function test_drawer_offers_add_to_board_with_the_active_release_preselected(): void
    {
        $this->release($this->team, 'Future release', [
            'start_date' => today()->addMonth()->toDateString(), 'end_date' => today()->addMonths(2)->toDateString(),
        ]);

        $this->actingAs($this->dev)->get(route('tasksheet.index', ['team' => $this->team->id]))
            ->assertOk()
            ->assertSee('formaction="'.route('tasksheet.entries.board').'"', false)
            ->assertSee('Add to board')
            ->assertSee('<option value="'.$this->release->id.'" selected>Checkout v2.5</option>', false)
            ->assertSee('Future release');
    }

    public function test_drawer_explains_when_the_team_has_no_open_release(): void
    {
        $this->release->update(['completed_at' => now()]);

        $this->actingAs($this->dev)->get(route('tasksheet.index', ['team' => $this->team->id]))
            ->assertOk()
            ->assertSee('Add to board needs an open release for Core.')
            ->assertDontSee('name="board_release_id"', false);
    }
}
