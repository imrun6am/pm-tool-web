<?php

namespace Tests\Feature;

use App\Models\Label;
use App\Models\Task;
use App\Models\TaskTimeLog;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Priority, labels, estimates and keys on tasks, and the filters that use them.
 */
class TaskFieldsTest extends TestCase
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

    private function label(string $name): Label
    {
        return Label::where('name', $name)->firstOrFail();
    }

    public function test_the_migration_adds_starter_labels(): void
    {
        $this->assertSame(['Bug', 'Chore', 'Feature', 'Improvement'], Label::orderBy('name')->pluck('name')->all());
    }

    public function test_the_task_page_saves_priority_estimate_and_labels(): void
    {
        $task = $this->card();
        $bug = $this->label('Bug');

        $this->actingAs($this->admin)->put(route('tasks.update', $task), [
            'title' => 'Card', 'priority' => 'high', 'estimate' => '4h 30m',
            'label_ids_sent' => 1, 'label_ids' => [$bug->id],
        ])->assertRedirect();

        $task->refresh();
        $this->assertSame('high', $task->priority);
        $this->assertSame(270, $task->estimate_minutes);
        $this->assertSame([$bug->id], $task->labels->pluck('id')->all());

        $this->get(route('tasks.show', $task))->assertOk()
            ->assertSee('value="4h 30m"', false)
            ->assertSee($task->reference());
    }

    public function test_unticking_every_label_and_blanking_the_estimate_clears_them(): void
    {
        $task = $this->card('Card', ['estimate_minutes' => 60, 'priority' => 'low']);
        $task->labels()->attach($this->label('Bug'));

        $this->actingAs($this->admin)->put(route('tasks.update', $task), [
            'title' => 'Card', 'priority' => '', 'estimate' => '', 'label_ids_sent' => 1,
        ])->assertRedirect();

        $task->refresh();
        $this->assertNull($task->priority);
        $this->assertNull($task->estimate_minutes);
        $this->assertCount(0, $task->labels);
    }

    public function test_an_update_without_labels_keeps_them(): void
    {
        $task = $this->card('Card', ['estimate_minutes' => 60]);
        $task->labels()->attach($this->label('Bug'));
        Sanctum::actingAs($this->admin);

        $this->putJson("/api/v1/tasks/{$task->id}", ['title' => 'Renamed'])->assertOk();

        $task->refresh();
        $this->assertCount(1, $task->labels);
        $this->assertSame(60, $task->estimate_minutes);
    }

    public function test_an_empty_list_clears_labels_on_the_api(): void
    {
        $task = $this->card();
        $task->labels()->attach($this->label('Bug'));
        Sanctum::actingAs($this->admin);

        $this->putJson("/api/v1/tasks/{$task->id}", ['title' => 'Card', 'label_ids' => []])
            ->assertOk()
            ->assertJsonCount(0, 'data.labels');
    }

    public function test_the_api_creates_and_returns_the_fields(): void
    {
        $feature = $this->label('Feature');
        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/v1/tasks', [
            'title' => 'New', 'priority' => 'urgent', 'estimate_minutes' => 90, 'label_ids' => [$feature->id],
        ])->assertCreated();

        $id = $response->json('data.id');
        $response->assertJsonPath('data.key', 'PM-'.$id)
            ->assertJsonPath('data.priority', 'urgent')
            ->assertJsonPath('data.priority_label', 'Urgent')
            ->assertJsonPath('data.estimate_minutes', 90)
            ->assertJsonPath('data.labels.0.name', 'Feature');

        $this->getJson("/api/v1/tasks/{$id}")->assertOk()->assertJsonPath('data.labels.0.id', $feature->id);
    }

    public function test_meta_lists_the_priorities(): void
    {
        Sanctum::actingAs($this->admin);

        $values = collect($this->getJson('/api/v1/meta')->assertOk()->json('data.task_priorities'))->pluck('value')->all();

        $this->assertSame(['urgent', 'high', 'medium', 'low'], $values);
    }

    /** @return array<string, array{0: array<string, mixed>, 1: string}> */
    public static function invalidFields(): array
    {
        return [
            'unknown priority' => [['priority' => 'whenever'], 'priority'],
            'unreadable estimate' => [['estimate' => 'soon'], 'estimate_minutes'],
            'zero estimate' => [['estimate_minutes' => 0], 'estimate_minutes'],
            'huge estimate' => [['estimate_minutes' => 125001], 'estimate_minutes'],
            'missing label' => [['label_ids' => [999999]], 'label_ids.0'],
            'repeated label' => [['label_ids' => [1, 1]], 'label_ids.0'],
            'labels not a list' => [['label_ids' => 'Bug'], 'label_ids'],
        ];
    }

    #[DataProvider('invalidFields')]
    public function test_bad_values_are_refused(array $payload, string $field): void
    {
        $task = $this->card();
        Sanctum::actingAs($this->admin);

        $this->putJson("/api/v1/tasks/{$task->id}", ['title' => 'Card'] + $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors($field);
    }

    public function test_the_board_filters_by_priority_and_label_on_both_surfaces(): void
    {
        $bug = $this->label('Bug');
        $both = $this->card('Both', ['priority' => 'urgent']);
        $both->labels()->attach($bug);
        $urgentOnly = $this->card('Urgent only', ['priority' => 'urgent']);
        $bugOnly = $this->card('Bug only', ['priority' => 'low']);
        $bugOnly->labels()->attach($bug);
        $plain = $this->card('Plain');

        $cards = fn ($query) => collect($this->actingAs($this->admin)->get(route('board.index', $query))->viewData('columns'))
            ->flatten()->pluck('id')->sort()->values()->all();

        $this->assertSame([$both->id, $urgentOnly->id], $cards(['priority' => 'urgent']));
        $this->assertSame([$both->id, $bugOnly->id], $cards(['label_id' => $bug->id]));
        $this->assertSame([$both->id], $cards(['priority' => 'urgent', 'label_id' => $bug->id]));
        $this->assertSame([$plain->id], $cards(['priority' => 'none']));
        $this->assertCount(4, $cards(['priority' => 'nonsense']));

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($this->admin);

        $api = $this->getJson('/api/v1/board?priority=urgent&label_id='.$bug->id)->assertOk()
            ->assertJsonPath('data.filters.priority', 'urgent')
            ->assertJsonPath('data.filters.label_id', $bug->id);
        $this->assertSame([$both->id], collect($api->json('data.columns'))->flatMap(fn ($c) => $c['tasks'])->pluck('id')->all());

        $list = $this->getJson('/api/v1/tasks?priority=none')->assertOk();
        $this->assertSame([$plain->id], collect($list->json('data'))->pluck('id')->all());
    }

    public function test_cards_show_the_fields_and_my_tasks_keeps_the_filters(): void
    {
        $bug = $this->label('Bug');
        $card = $this->card('Card', ['priority' => 'urgent']);
        $card->labels()->attach($bug);

        $this->actingAs($this->admin)->get(route('board.index', ['priority' => 'urgent', 'label_id' => $bug->id]))
            ->assertOk()
            ->assertSee($card->reference())
            ->assertSee('Urgent')
            ->assertSee('priority=urgent', false)
            ->assertSee('label_id='.$bug->id, false);
    }

    public function test_select_all_names_the_new_filters(): void
    {
        $bug = $this->label('Bug');

        $this->actingAs($this->admin)->get(route('board.index', ['priority' => 'high', 'label_id' => $bug->id]))
            ->assertOk()
            ->assertSee('data-filter-scope="Priority: High, Label: Bug"', false);
    }

    public function test_search_finds_a_task_by_its_key_within_what_the_viewer_may_see(): void
    {
        $team = Team::create(['name' => 'Theirs', 'color' => '#0891b2']);
        $hidden = $this->card('Hidden', ['team_id' => $team->id]);
        $this->card('Decoy mentioning PM-'.$hidden->id);
        $dev = User::factory()->create(['role' => User::ROLE_DEVELOPER]);

        Sanctum::actingAs($this->admin);
        foreach (['PM-'.$hidden->id, 'pm-'.$hidden->id, (string) $hidden->id] as $query) {
            $response = $this->getJson('/api/v1/search/tasks?q='.$query)->assertOk();
            $this->assertSame($hidden->id, $response->json('data.0.id'), $query);
        }

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($dev);
        $ids = collect($this->getJson('/api/v1/search/tasks?q=PM-'.$hidden->id)->assertOk()->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($hidden->id));
    }

    public function test_the_time_summary_adds_subtask_estimates_and_flags_going_over(): void
    {
        $task = $this->card('Parent', ['estimate_minutes' => 120]);
        $this->card('Part', ['parent_id' => $task->id, 'estimate_minutes' => 60]);
        $this->card('Unestimated', ['parent_id' => $task->id]);
        TaskTimeLog::create(['task_id' => $task->id, 'user_id' => $this->admin->id, 'logged_on' => today(), 'minutes' => 200]);

        Sanctum::actingAs($this->admin);
        $this->getJson("/api/v1/tasks/{$task->id}/time-logs")->assertOk()->assertJsonPath('data.estimate_minutes', 180);

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->admin)->get(route('tasks.show', $task))->assertOk()->assertSee('Estimated 3h');
    }

    public function test_a_task_with_no_estimates_has_none_in_its_summary(): void
    {
        $task = $this->card();
        Sanctum::actingAs($this->admin);

        $this->getJson("/api/v1/tasks/{$task->id}/time-logs")->assertOk()->assertJsonPath('data.estimate_minutes', null);
    }

    public function test_a_lead_manages_labels_on_the_web(): void
    {
        $lead = User::factory()->create(['role' => User::ROLE_TEAM_LEAD]);

        $this->actingAs($lead)->get(route('labels.index'))->assertOk()->assertSee('id="labels"', false)->assertSee('Improvement');

        $this->from(route('labels.index'))->post(route('labels.store'), ['name' => 'Design', 'color' => '#7c3aed'])->assertRedirect(route('labels.index'));
        $design = $this->label('Design');

        $this->from(route('labels.index'))->put(route('labels.update', $design), ['name' => 'UX', 'color' => '#7c3aed'])->assertRedirect(route('labels.index'));
        $this->assertSame('UX', $design->fresh()->name);

        $this->post(route('labels.store'), ['name' => 'UX', 'color' => '#7c3aed'])->assertSessionHasErrors('name');
        $this->post(route('labels.store'), ['name' => 'Bad color', 'color' => 'red'])->assertSessionHasErrors('color');
    }

    public function test_deleting_a_label_keeps_its_tasks(): void
    {
        $task = $this->card();
        $bug = $this->label('Bug');
        $task->labels()->attach($bug);

        $this->actingAs($this->admin)->delete(route('labels.destroy', $bug))->assertRedirect();

        $this->assertDatabaseHas('tasks', ['id' => $task->id]);
        $this->assertDatabaseMissing('labels', ['id' => $bug->id]);
        $this->assertDatabaseMissing('label_task', ['label_id' => $bug->id]);
    }

    /** @return array<string, array{0: string}> */
    public static function nonLeads(): array
    {
        return [
            'developer' => [User::ROLE_DEVELOPER],
            'qa' => [User::ROLE_QA],
            'viewer' => [User::ROLE_VIEWER],
        ];
    }

    #[DataProvider('nonLeads')]
    public function test_others_can_read_labels_but_not_change_them(string $role): void
    {
        $user = User::factory()->create(['role' => $role]);
        $bug = $this->label('Bug');

        $this->actingAs($user)->get(route('labels.index'))->assertForbidden();
        $this->post(route('labels.store'), ['name' => 'X', 'color' => '#000000'])->assertForbidden();
        $this->delete(route('labels.destroy', $bug))->assertForbidden();

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/labels')->assertOk()->assertJsonPath('data.0.name', 'Bug');
        $this->postJson('/api/v1/labels', ['name' => 'X', 'color' => '#000000'])->assertForbidden();
        $this->putJson("/api/v1/labels/{$bug->id}", ['name' => 'X', 'color' => '#000000'])->assertForbidden();
        $this->deleteJson("/api/v1/labels/{$bug->id}")->assertForbidden();

        $this->assertDatabaseHas('labels', ['id' => $bug->id, 'name' => 'Bug']);
    }

    public function test_a_lead_manages_labels_through_the_api(): void
    {
        Sanctum::actingAs($this->admin);

        $id = $this->postJson('/api/v1/labels', ['name' => 'Design', 'color' => '#7c3aed'])->assertCreated()->json('data.id');
        $this->putJson("/api/v1/labels/{$id}", ['name' => 'UX', 'color' => '#7c3aed'])->assertOk()->assertJsonPath('data.name', 'UX');
        $this->deleteJson("/api/v1/labels/{$id}")->assertOk();

        $this->assertDatabaseMissing('labels', ['id' => $id]);
    }

    public function test_a_viewer_does_not_see_the_manage_labels_link(): void
    {
        $task = $this->card();
        $viewer = User::factory()->create(['role' => User::ROLE_VIEWER]);

        $this->actingAs($viewer)->get(route('tasks.show', $task))->assertOk()->assertDontSee(route('labels.index'), false);
        $this->actingAs($this->admin)->get(route('tasks.show', $task))->assertOk()->assertSee(route('labels.index'), false);
    }
}
