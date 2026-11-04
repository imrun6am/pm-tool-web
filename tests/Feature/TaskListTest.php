<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Label;
use App\Models\Project;
use App\Models\Release;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Notifications\InboxNotification;
use App\Notifications\TaskAssigned;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The list of all tasks: filters, search, sorting, and changing many at once.
 */
class TaskListTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function card(string $title = 'Card', array $attrs = []): Task
    {
        return Task::create($attrs + ['title' => $title, 'status' => 'todo', 'position' => 0]);
    }

    /** @return array<int, int> ids in the order listed */
    private function listed(array $query = []): array
    {
        return $this->actingAs($this->admin)->get(route('tasks.index', $query))->assertOk()
            ->viewData('tasks')->pluck('id')->all();
    }

    public function test_the_list_shows_top_level_tasks_and_its_regions(): void
    {
        $task = $this->card('Parent task');
        $this->card('A subtask', ['parent_id' => $task->id]);

        $this->actingAs($this->admin)->get(route('tasks.index'))->assertOk()
            ->assertSee('id="task-list"', false)
            ->assertSee('Parent task')
            ->assertSee($task->reference())
            ->assertDontSee('A subtask');
    }

    public function test_search_treats_special_characters_literally(): void
    {
        $sale = $this->card('50% off banner');
        $this->card('500 offers');

        $this->assertSame([$sale->id], $this->listed(['q' => '50%']));
    }

    public function test_filters_combine_and_blank_ones_are_ignored(): void
    {
        $team = Team::create(['name' => 'Core', 'color' => '#0891b2']);
        $match = $this->card('Match', ['team_id' => $team->id, 'priority' => 'high', 'status' => 'in_review', 'blocked_at' => now(), 'blocked_reason' => 'x']);
        $this->card('Other team', ['priority' => 'high', 'status' => 'in_review']);
        $this->card('Other status', ['team_id' => $team->id, 'priority' => 'high']);

        $this->assertSame([$match->id], $this->listed(['team_id' => $team->id, 'priority' => 'high', 'status' => 'in_review', 'blocked' => 1, 'release_id' => '']));
    }

    public function test_unassigned_filter(): void
    {
        $free = $this->card('Free');
        $this->card('Taken', ['assignee_id' => $this->admin->id]);

        $this->assertSame([$free->id], $this->listed(['assignee_id' => 'none']));
    }

    public function test_sorting_uses_the_fields_own_order_with_empty_values_last(): void
    {
        $low = $this->card('Low', ['priority' => 'low', 'due_date' => '2026-12-01']);
        $urgent = $this->card('Urgent', ['priority' => 'urgent', 'due_date' => '2026-12-05']);
        $none = $this->card('None');

        $this->assertSame([$urgent->id, $low->id, $none->id], $this->listed(['sort' => 'priority']));
        $this->assertSame([$low->id, $urgent->id, $none->id], $this->listed(['sort' => 'priority', 'direction' => 'desc']));
        $this->assertSame([$low->id, $urgent->id, $none->id], $this->listed(['sort' => 'due_date']));
        $this->assertSame([$urgent->id, $low->id, $none->id], $this->listed(['sort' => 'due_date', 'direction' => 'desc']));
        $this->assertSame([$low->id, $none->id, $urgent->id], $this->listed(['sort' => 'title']));
    }

    public function test_sorting_by_status_and_assignee(): void
    {
        $ann = User::factory()->create(['name' => 'Ann']);
        $zed = User::factory()->create(['name' => 'Zed']);
        $done = $this->card('Done', ['status' => 'done', 'assignee_id' => $ann->id]);
        $review = $this->card('Review', ['status' => 'in_review', 'assignee_id' => $zed->id]);
        $todo = $this->card('Todo');

        $this->assertSame([$todo->id, $review->id, $done->id], $this->listed(['sort' => 'status']));
        $this->assertSame([$done->id, $review->id, $todo->id], $this->listed(['sort' => 'assignee']));
    }

    public function test_an_unknown_sort_is_ignored(): void
    {
        $task = $this->card();

        $this->assertSame([$task->id], $this->listed(['sort' => 'password; drop table tasks', 'direction' => 'sideways']));
    }

    public function test_developers_only_list_their_own_teams_tasks(): void
    {
        $team = Team::create(['name' => 'Core', 'color' => '#0891b2']);
        $other = Team::create(['name' => 'Other', 'color' => '#e11d48']);
        $dev = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $team->members()->attach($dev);
        $mine = $this->card('Mine', ['team_id' => $team->id]);
        $this->card('Theirs', ['team_id' => $other->id]);

        $response = $this->actingAs($dev)->get(route('tasks.index', ['team_id' => $other->id]))->assertOk();
        $this->assertSame([], $response->viewData('tasks')->pluck('id')->all());

        $this->assertSame([$mine->id], $this->actingAs($dev)->get(route('tasks.index'))->viewData('tasks')->pluck('id')->all());
    }

    public function test_the_list_pages_and_keeps_its_filters(): void
    {
        foreach (range(1, 55) as $i) {
            $this->card("Card {$i}", ['priority' => 'high']);
        }

        $response = $this->actingAs($this->admin)->get(route('tasks.index', ['priority' => 'high']))->assertOk();

        $this->assertCount(50, $response->viewData('tasks'));
        $this->assertSame(55, $response->viewData('tasks')->total());
        $response->assertSee('priority=high&amp;page=2', false);
    }

    public function test_bulk_edit_changes_each_task_and_keeps_the_list_url(): void
    {
        $bug = Label::where('name', 'Bug')->first();
        $assignee = User::factory()->create();
        $a = $this->card('A');
        $b = $this->card('B', ['priority' => 'low']);
        $untouched = $this->card('Untouched');

        $this->actingAs($this->admin)->from(route('tasks.index', ['q' => 'x']))
            ->post(route('tasks.bulk'), [
                'ids' => [$a->id, $b->id], 'status' => 'in_progress', 'assignee_id' => $assignee->id,
                'priority' => 'high', 'add_label_id' => $bug->id,
            ])
            ->assertRedirect(route('tasks.index', ['q' => 'x']))
            ->assertSessionHas('success', '2 tasks updated.');

        foreach ([$a, $b] as $task) {
            $task->refresh();
            $this->assertSame('in_progress', $task->status);
            $this->assertSame($assignee->id, $task->assignee_id);
            $this->assertSame('high', $task->priority);
            $this->assertSame([$bug->id], $task->labels->pluck('id')->all());
        }
        $this->assertSame('todo', $untouched->fresh()->status);
    }

    public function test_blank_fields_change_nothing_and_none_clears(): void
    {
        $task = $this->card('A', ['priority' => 'high', 'assignee_id' => $this->admin->id, 'status' => 'recheck']);

        $this->actingAs($this->admin)->post(route('tasks.bulk'), [
            'ids' => [$task->id], 'status' => '', 'priority' => 'none', 'assignee_id' => '', 'add_label_id' => '',
        ])->assertRedirect();

        $task->refresh();
        $this->assertNull($task->priority);
        $this->assertSame('recheck', $task->status);
        $this->assertSame($this->admin->id, $task->assignee_id);
    }

    public function test_labels_already_on_a_task_stay_when_adding_another(): void
    {
        $bug = Label::where('name', 'Bug')->first();
        $chore = Label::where('name', 'Chore')->first();
        $task = $this->card();
        $task->labels()->attach($bug);

        $this->actingAs($this->admin)->post(route('tasks.bulk'), ['ids' => [$task->id], 'add_label_id' => $chore->id])->assertRedirect();

        $this->assertCount(2, $task->fresh()->labels);
    }

    public function test_nothing_to_change_is_refused(): void
    {
        $task = $this->card();

        $this->actingAs($this->admin)->post(route('tasks.bulk'), ['ids' => [$task->id], 'status' => ''])
            ->assertSessionHasErrors('ids');
    }

    public function test_bulk_changes_are_logged_per_task_and_notified_once_per_person(): void
    {
        Notification::fake();
        $assignee = User::factory()->create();
        $a = $this->card('A');
        $b = $this->card('B');

        $this->actingAs($this->admin)->post(route('tasks.bulk'), ['ids' => [$a->id, $b->id], 'status' => 'done', 'assignee_id' => $assignee->id]);

        $this->assertSame(['todo', 'done'], $a->statusChanges()->pluck('to_status')->all());
        $this->assertSame(1, Activity::where('subject_id', $b->id)->where('event', 'updated')->count());
        Notification::assertSentToTimes($assignee, InboxNotification::class, 1);
        Notification::assertNotSentTo($assignee, TaskAssigned::class);
    }

    public function test_a_task_out_of_reach_stops_the_whole_change(): void
    {
        $team = Team::create(['name' => 'Core', 'color' => '#0891b2']);
        $dev = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $team->members()->attach($dev);
        $mine = $this->card('Mine', ['team_id' => $team->id]);
        $theirs = $this->card('Theirs', ['team_id' => Team::create(['name' => 'Other', 'color' => '#e11d48'])->id]);

        $this->actingAs($dev)->post(route('tasks.bulk'), ['ids' => [$mine->id, $theirs->id], 'status' => 'done'])->assertForbidden();

        $this->assertSame('todo', $mine->fresh()->status);
        $this->assertSame('todo', $theirs->fresh()->status);
    }

    /** @return array<string, array{0: array<string, mixed>, 1: string}> */
    public static function invalidBulk(): array
    {
        return [
            'no ids' => [['status' => 'done'], 'ids'],
            'missing id' => [['ids' => [999999], 'status' => 'done'], 'ids.0'],
            'non-integer id' => [['ids' => ['x'], 'status' => 'done'], 'ids.0'],
            'bad status' => [['ids' => [1], 'status' => 'whenever'], 'status'],
            'bad priority' => [['ids' => [1], 'priority' => 'whenever'], 'priority'],
            'bad assignee' => [['ids' => [1], 'assignee_id' => 999999], 'assignee_id'],
            'bad label' => [['ids' => [1], 'add_label_id' => 999999], 'add_label_id'],
            'too many' => [['ids' => 'many', 'status' => 'done'], 'ids'],
        ];
    }

    #[DataProvider('invalidBulk')]
    public function test_bad_bulk_requests_are_refused_on_the_api(array $payload, string $field): void
    {
        $this->card();
        if (($payload['ids'] ?? null) === 'many') {
            $payload['ids'] = range(1, 201);
        }
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/v1/tasks/bulk', $payload)->assertStatus(422)->assertJsonValidationErrors($field);
    }

    public function test_the_api_mirrors_the_web(): void
    {
        $task = $this->card('Findable', ['priority' => 'low']);
        $this->card('Other');
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/v1/tasks/bulk', ['ids' => [$task->id], 'priority' => 'urgent'])->assertOk()
            ->assertJsonPath('data.updated_ids', [$task->id])
            ->assertJsonPath('message', '1 task updated.');

        $listed = $this->getJson('/api/v1/tasks?q=Findable&sort=priority&direction=desc')->assertOk();
        $this->assertSame([$task->id], collect($listed->json('data'))->pluck('id')->all());
        $listed->assertJsonPath('data.0.priority', 'urgent');
    }

    public function test_a_new_assignee_must_suit_every_linked_task(): void
    {
        $project = Project::create(['name' => 'P', 'color' => '#4f46e5']);
        $team = Team::create(['name' => 'Core', 'color' => '#0891b2']);
        $release = Release::create([
            'project_id' => $project->id, 'team_id' => $team->id, 'name' => 'R',
            'year' => 2026, 'quarter' => 3, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31',
        ]);
        $linked = $this->card('Linked', ['release_id' => $release->id]);
        $free = $this->card('Free');
        $outsider = User::factory()->create();

        $this->actingAs($this->admin)->post(route('tasks.bulk'), ['ids' => [$linked->id, $free->id], 'assignee_id' => $outsider->id])
            ->assertSessionHasErrors('assignee_id');

        $this->assertNull($free->fresh()->assignee_id);
    }

    public function test_a_deactivated_person_cannot_be_given_tasks(): void
    {
        $task = $this->card();
        $gone = User::factory()->create(['deactivated_at' => now()]);

        $this->actingAs($this->admin)->post(route('tasks.bulk'), ['ids' => [$task->id], 'assignee_id' => $gone->id])
            ->assertSessionHasErrors('assignee_id');
    }

    public function test_a_developer_can_only_assign_teammates(): void
    {
        $team = Team::create(['name' => 'Core', 'color' => '#0891b2']);
        $dev = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $mate = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $team->members()->attach([$dev->id, $mate->id]);
        $task = $this->card('Mine', ['team_id' => $team->id]);
        $stranger = User::factory()->create();

        $this->actingAs($dev)->post(route('tasks.bulk'), ['ids' => [$task->id], 'assignee_id' => $stranger->id])->assertSessionHasErrors('assignee_id');
        $this->post(route('tasks.bulk'), ['ids' => [$task->id], 'assignee_id' => $mate->id])->assertSessionHasNoErrors();

        $this->assertSame($mate->id, $task->fresh()->assignee_id);
    }

    public function test_guests_are_refused(): void
    {
        $this->get(route('tasks.index'))->assertRedirect(route('login'));
        $this->post(route('tasks.bulk'), [])->assertRedirect(route('login'));
    }
}
