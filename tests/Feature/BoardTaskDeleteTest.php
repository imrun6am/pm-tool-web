<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Comment;
use App\Models\Project;
use App\Models\Release;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Services\BoardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Admins delete cards from the board, one at a time or in bulk. Every other
 * role gets no controls and a 403, on the web and through the API.
 */
class BoardTaskDeleteTest extends TestCase
{
    use RefreshDatabase;

    private const BULK_API = '/api/v1/board/tasks/bulk-delete';

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function card(string $title = 'Card', array $attrs = []): Task
    {
        return Task::create($attrs + ['title' => $title, 'status' => 'todo', 'position' => 0]);
    }

    private function subtaskOf(Task $parent, string $title = 'Subtask'): Task
    {
        return Task::create(['parent_id' => $parent->id, 'title' => $title, 'status' => 'todo', 'position' => 0]);
    }

    /** @return array<string, array{string}> */
    public static function nonAdminRoles(): array
    {
        return collect(User::ROLES)->except(User::ROLE_ADMIN)->keys()
            ->mapWithKeys(fn (string $role) => [$role => [$role]])
            ->all();
    }

    public function test_an_admin_deletes_one_card_with_its_subtasks_and_comments(): void
    {
        $admin = $this->admin();
        $card = $this->card('Doomed');
        $subtask = $this->subtaskOf($card);
        $card->comments()->create(['user_id' => $admin->id, 'body' => 'On the card']);
        $subtask->comments()->create(['user_id' => $admin->id, 'body' => 'On the subtask']);
        $other = $this->card('Kept');
        $other->comments()->create(['user_id' => $admin->id, 'body' => 'Stays']);

        $this->actingAs($admin)->deleteJson(route('board.tasks.destroy', $card))
            ->assertOk()
            ->assertExactJson(['ok' => true, 'deleted_ids' => [$card->id], 'subtasks_deleted' => 1]);

        $this->assertDatabaseMissing('tasks', ['id' => $card->id]);
        $this->assertDatabaseMissing('tasks', ['id' => $subtask->id]);
        $this->assertDatabaseHas('tasks', ['id' => $other->id]);
        $this->assertSame(['Stays'], Comment::pluck('body')->all());
    }

    public function test_every_deleted_task_is_logged_against_the_admin(): void
    {
        $admin = $this->admin();
        $card = $this->card();
        $subtask = $this->subtaskOf($card);

        $this->actingAs($admin)->deleteJson(route('board.tasks.destroy', $card))->assertOk();

        foreach ([$card, $subtask] as $task) {
            $this->assertTrue(
                Activity::where('event', 'deleted')
                    ->where('subject_type', $task->getMorphClass())
                    ->where('subject_id', $task->id)
                    ->where('causer_id', $admin->id)
                    ->exists(),
                "No deleted activity entry for task {$task->id}."
            );
        }
    }

    public function test_the_api_deletes_one_card(): void
    {
        $card = $this->card();
        $this->subtaskOf($card);

        Sanctum::actingAs($this->admin());

        $this->deleteJson("/api/v1/board/tasks/{$card->id}")
            ->assertOk()
            ->assertJsonPath('data.deleted_ids', [$card->id])
            ->assertJsonPath('data.subtasks_deleted', 1)
            ->assertJsonPath('message', '1 task deleted.');

        $this->assertDatabaseMissing('tasks', ['id' => $card->id]);
    }

    public function test_the_single_endpoint_refuses_a_subtask(): void
    {
        $card = $this->card();
        $subtask = $this->subtaskOf($card);
        $admin = $this->admin();

        $this->actingAs($admin)->deleteJson(route('board.tasks.destroy', $subtask))->assertForbidden();

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($admin);
        $this->deleteJson("/api/v1/board/tasks/{$subtask->id}")->assertForbidden();

        $this->assertDatabaseHas('tasks', ['id' => $subtask->id]);
    }

    public function test_an_admin_deletes_cards_in_bulk(): void
    {
        $admin = $this->admin();
        $first = $this->card('One');
        $second = $this->card('Two');
        $subtask = $this->subtaskOf($second);
        $kept = $this->card('Kept');

        $this->actingAs($admin)
            ->postJson(route('board.tasks.bulk-destroy'), ['ids' => [$first->id, $second->id]])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('subtasks_deleted', 1)
            ->assertJsonCount(2, 'deleted_ids');

        $this->assertDatabaseMissing('tasks', ['id' => $first->id]);
        $this->assertDatabaseMissing('tasks', ['id' => $second->id]);
        $this->assertDatabaseMissing('tasks', ['id' => $subtask->id]);
        $this->assertDatabaseHas('tasks', ['id' => $kept->id]);
        $this->assertSame(3, Activity::where('event', 'deleted')->where('causer_id', $admin->id)->count());
    }

    public function test_select_all_under_a_team_filter_leaves_other_teams_alone(): void
    {
        $core = Team::create(['name' => 'Core', 'color' => '#0891b2']);
        $growth = Team::create(['name' => 'Growth', 'color' => '#e11d48']);
        $coreTodo = $this->card('Core todo', ['team_id' => $core->id]);
        $coreDone = $this->card('Core done', ['team_id' => $core->id, 'status' => 'done']);
        $growthCard = $this->card('Growth card', ['team_id' => $growth->id]);
        $teamless = $this->card('No team');

        // What select all sends: the ids of the cards the filtered board shows.
        $board = $this->actingAs($this->admin())->get(route('board.index', ['team_id' => $core->id]))
            ->assertOk()
            ->assertSee('Only Team: Core');
        $shown = collect($board->viewData('columns'))->flatten()->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$coreTodo->id, $coreDone->id], $shown);

        $this->postJson(route('board.tasks.bulk-destroy'), ['ids' => $shown])->assertOk();

        $this->assertDatabaseMissing('tasks', ['id' => $coreTodo->id]);
        $this->assertDatabaseMissing('tasks', ['id' => $coreDone->id]);
        $this->assertDatabaseHas('tasks', ['id' => $growthCard->id]);
        $this->assertDatabaseHas('tasks', ['id' => $teamless->id]);
    }

    #[DataProvider('nonAdminRoles')]
    public function test_other_roles_are_refused_on_every_endpoint(string $role): void
    {
        $team = Team::create(['name' => 'Theirs', 'color' => '#0891b2']);
        $user = User::factory()->create(['role' => $role]);
        $team->members()->attach($user);
        // On their own team, so being kept out of it isn't why they're refused.
        $card = $this->card('Theirs', ['team_id' => $team->id, 'assignee_id' => $user->id]);

        $this->actingAs($user)->deleteJson(route('board.tasks.destroy', $card))->assertForbidden();
        $this->postJson(route('board.tasks.bulk-destroy'), ['ids' => [$card->id]])->assertForbidden();

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($user);

        $this->deleteJson("/api/v1/board/tasks/{$card->id}")->assertForbidden();
        $this->postJson(self::BULK_API, ['ids' => [$card->id]])->assertForbidden();

        $this->assertDatabaseHas('tasks', ['id' => $card->id]);
    }

    #[DataProvider('nonAdminRoles')]
    public function test_other_roles_get_403_before_their_ids_are_looked_at(string $role): void
    {
        $user = User::factory()->create(['role' => $role]);

        $this->actingAs($user)->postJson(route('board.tasks.bulk-destroy'), ['ids' => [999999]])->assertForbidden();
        $this->postJson(route('board.tasks.bulk-destroy'), [])->assertForbidden();

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($user);

        $this->postJson(self::BULK_API, ['ids' => ['nonsense']])->assertForbidden();
    }

    #[DataProvider('nonAdminRoles')]
    public function test_other_roles_cannot_use_the_general_task_delete_route(string $role): void
    {
        $team = Team::create(['name' => 'Theirs', 'color' => '#0891b2']);
        $user = User::factory()->create(['role' => $role]);
        $team->members()->attach($user);
        // Their own task on their own team, so being kept out of it isn't why they're refused.
        $task = $this->card('Theirs', ['team_id' => $team->id, 'assignee_id' => $user->id, 'created_by' => $user->id]);
        $subtask = $this->subtaskOf($task);

        $this->actingAs($user)->delete(route('tasks.destroy', $task))->assertForbidden();
        $this->delete(route('tasks.destroy', $subtask))->assertForbidden();

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($user);

        $this->deleteJson("/api/v1/tasks/{$task->id}")->assertForbidden();
        $this->deleteJson("/api/v1/tasks/{$subtask->id}")->assertForbidden();

        $this->assertDatabaseHas('tasks', ['id' => $task->id]);
        $this->assertDatabaseHas('tasks', ['id' => $subtask->id]);
    }

    public function test_an_admin_can_use_the_general_task_delete_route_for_a_subtask(): void
    {
        $task = $this->card();
        $web = $this->subtaskOf($task, 'Web');
        $api = $this->subtaskOf($task, 'Api');
        $admin = $this->admin();

        $this->actingAs($admin)->delete(route('tasks.destroy', $web))->assertRedirect();

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($admin);
        $this->deleteJson("/api/v1/tasks/{$api->id}")->assertOk();

        $this->assertDatabaseMissing('tasks', ['id' => $web->id]);
        $this->assertDatabaseMissing('tasks', ['id' => $api->id]);
        $this->assertDatabaseHas('tasks', ['id' => $task->id]);
    }

    public function test_subtask_delete_buttons_on_the_task_page_are_for_admins_only(): void
    {
        $task = $this->card();
        $this->subtaskOf($task);
        // The delete URL is also the subtask's link, so look for the form's prompt.
        $button = 'Delete this subtask?';

        $this->actingAs($this->admin())->get(route('tasks.show', $task))->assertOk()->assertSee($button, false);

        $viewer = User::factory()->create(['role' => User::ROLE_VIEWER]);
        $this->actingAs($viewer)->get(route('tasks.show', $task))->assertOk()->assertDontSee($button, false);
    }

    public function test_release_panel_delete_buttons_are_for_admins_only(): void
    {
        $project = Project::create(['name' => 'Checkout', 'color' => '#4f46e5']);
        $team = Team::create(['name' => 'Core', 'color' => '#0891b2']);
        $release = Release::create([
            'project_id' => $project->id, 'team_id' => $team->id, 'name' => 'R1',
            'year' => 2026, 'quarter' => 3, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31',
        ]);
        $task = $this->card('Linked', ['release_id' => $release->id]);
        $this->subtaskOf($task);
        // The delete URLs are also the tasks' links, so look for the forms' prompts.
        $buttons = ['Delete this task and its subtasks?', 'Delete this subtask?'];

        $admin = $this->actingAs($this->admin())->get(route('releases.show', $release))->assertOk();
        foreach ($buttons as $button) {
            $admin->assertSee($button, false);
        }

        $lead = User::factory()->create(['role' => User::ROLE_CTO]);
        $other = $this->actingAs($lead)->get(route('releases.show', $release))->assertOk();
        foreach ($buttons as $button) {
            $other->assertDontSee($button, false);
        }
    }

    #[DataProvider('nonAdminRoles')]
    public function test_other_roles_see_no_controls(string $role): void
    {
        $this->card('Visible');

        $this->actingAs(User::factory()->create(['role' => $role]))
            ->get(route('board.index'))
            ->assertOk()
            ->assertDontSee('board-select', false)
            ->assertDontSee('board-delete', false)
            ->assertDontSee('board-bulk', false)
            ->assertDontSee('Select all');
    }

    public function test_an_admin_sees_the_controls_and_the_whole_board_scope(): void
    {
        $card = $this->card('Visible');
        $this->subtaskOf($card);
        $this->subtaskOf($card, 'Another');

        $this->actingAs($this->admin())
            ->get(route('board.index'))
            ->assertOk()
            ->assertSee('board-select', false)
            ->assertSee(route('board.tasks.destroy', $card), false)
            ->assertSee(route('board.tasks.bulk-destroy'), false)
            ->assertSee('data-subtasks="2"', false)
            ->assertSee('its 2 subtasks and all comments')
            ->assertSee('Select all')
            ->assertSee('Whole board, no filters');
    }

    /** @return array<string, array{\Closure(Task, Task): array<string, mixed>}> */
    public static function invalidPayloads(): array
    {
        return [
            'an id that does not exist' => [fn (Task $card) => ['ids' => [$card->id, 999999]]],
            'a subtask id' => [fn (Task $card, Task $subtask) => ['ids' => [$card->id, $subtask->id]]],
            'a repeated id' => [fn (Task $card) => ['ids' => [$card->id, $card->id]]],
            'a non-integer id' => [fn (Task $card) => ['ids' => [$card->id, 'abc']]],
            'a nested array' => [fn (Task $card) => ['ids' => [$card->id, [$card->id]]]],
            'an empty list' => [fn () => ['ids' => []]],
            'no list at all' => [fn () => []],
            'ids that are not a list' => [fn (Task $card) => ['ids' => $card->id]],
        ];
    }

    #[DataProvider('invalidPayloads')]
    public function test_a_bad_payload_is_rejected_and_deletes_nothing(\Closure $payload): void
    {
        $admin = $this->admin();
        $card = $this->card();
        $subtask = $this->subtaskOf($card);
        $body = $payload($card, $subtask);

        $this->actingAs($admin)->postJson(route('board.tasks.bulk-destroy'), $body)->assertUnprocessable();

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($admin);
        $this->postJson(self::BULK_API, $body)->assertUnprocessable();

        $this->assertDatabaseHas('tasks', ['id' => $card->id]);
        $this->assertDatabaseHas('tasks', ['id' => $subtask->id]);
    }

    public function test_a_bulk_delete_over_the_cap_is_rejected(): void
    {
        $card = $this->card();
        $ids = range($card->id, $card->id + BoardService::MAX_BULK_DELETE);

        $this->actingAs($this->admin())
            ->postJson(route('board.tasks.bulk-destroy'), ['ids' => $ids])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ids');

        $this->assertDatabaseHas('tasks', ['id' => $card->id]);
    }

    public function test_guests_are_turned_away(): void
    {
        $card = $this->card();

        $this->delete(route('board.tasks.destroy', $card))->assertRedirect(route('login'));
        $this->post(route('board.tasks.bulk-destroy'), ['ids' => [$card->id]])->assertRedirect(route('login'));
        $this->deleteJson("/api/v1/board/tasks/{$card->id}")->assertUnauthorized();
        $this->postJson(self::BULK_API, ['ids' => [$card->id]])->assertUnauthorized();

        $this->assertDatabaseHas('tasks', ['id' => $card->id]);
    }
}
