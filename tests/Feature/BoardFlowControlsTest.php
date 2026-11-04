<?php

namespace Tests\Feature;

use App\Models\SavedView;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Models\WipLimit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * WIP limits per column, how long a card has sat in its status, and grouping
 * the board's cards by person or priority.
 */
class BoardFlowControlsTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->team = Team::create(['name' => 'Core', 'color' => '#0891b2']);
        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function card(string $title = 'Card', array $attrs = []): Task
    {
        return Task::create($attrs + ['title' => $title, 'status' => 'in_progress', 'position' => 0, 'team_id' => $this->team->id]);
    }

    public function test_a_lead_sets_and_clears_a_limit_on_the_web(): void
    {
        $this->actingAs($this->admin)->from(route('board.index', ['team_id' => $this->team->id]))
            ->put(route('board.wip-limits'), ['team_id' => $this->team->id, 'status' => 'in_progress', 'max_tasks' => 3])
            ->assertRedirect(route('board.index', ['team_id' => $this->team->id]));

        $this->assertSame(3, WipLimit::sole()->max_tasks);

        $this->put(route('board.wip-limits'), ['team_id' => $this->team->id, 'status' => 'in_progress', 'max_tasks' => 5]);
        $this->assertSame(5, WipLimit::sole()->max_tasks);

        $this->put(route('board.wip-limits'), ['team_id' => $this->team->id, 'status' => 'in_progress', 'max_tasks' => '']);
        $this->assertSame(0, WipLimit::count());
    }

    public function test_the_column_shows_its_limit_and_when_it_is_over(): void
    {
        WipLimit::create(['team_id' => $this->team->id, 'status' => 'in_progress', 'max_tasks' => 1]);
        $this->card('One');
        $this->card('Two');

        $this->actingAs($this->admin)->get(route('board.index', ['team_id' => $this->team->id]))->assertOk()
            ->assertSee('data-limit="1"', false)
            ->assertSee('Over the limit of 1', false);
    }

    public function test_limits_stay_hidden_without_a_single_teams_whole_column(): void
    {
        WipLimit::create(['team_id' => $this->team->id, 'status' => 'in_progress', 'max_tasks' => 1]);
        $this->card();

        $this->actingAs($this->admin)->get(route('board.index'))->assertOk()->assertDontSee('data-limit=', false);
        $this->get(route('board.index', ['team_id' => $this->team->id, 'priority' => 'high']))->assertOk()->assertDontSee('data-limit=', false);
    }

    public function test_a_limit_never_stops_a_move(): void
    {
        WipLimit::create(['team_id' => $this->team->id, 'status' => 'in_review', 'max_tasks' => 1]);
        $this->card('Already there', ['status' => 'in_review']);
        $moving = $this->card('Moving');

        $this->actingAs($this->admin)->patchJson(route('board.move', $moving), ['status' => 'in_review'])->assertOk();

        $this->assertSame('in_review', $moving->fresh()->status);
    }

    public function test_finished_columns_cannot_have_a_limit(): void
    {
        Sanctum::actingAs($this->admin);

        foreach (['done', 'archive', 'nonsense'] as $status) {
            $this->putJson('/api/v1/board/wip-limits', ['team_id' => $this->team->id, 'status' => $status, 'max_tasks' => 3])
                ->assertStatus(422)->assertJsonValidationErrors('status');
        }
    }

    public function test_bad_limits_are_refused(): void
    {
        Sanctum::actingAs($this->admin);

        $this->putJson('/api/v1/board/wip-limits', ['team_id' => $this->team->id, 'status' => 'todo', 'max_tasks' => 0])->assertStatus(422);
        $this->putJson('/api/v1/board/wip-limits', ['team_id' => 999999, 'status' => 'todo', 'max_tasks' => 3])->assertStatus(422);
    }

    /** @return array<string, array{0: string}> */
    public static function others(): array
    {
        return ['developer' => [User::ROLE_DEVELOPER], 'qa' => [User::ROLE_QA], 'viewer' => [User::ROLE_VIEWER], 'another team lead' => [User::ROLE_TEAM_LEAD]];
    }

    #[DataProvider('others')]
    public function test_only_this_teams_lead_or_an_org_lead_can_set_limits(string $role): void
    {
        $user = User::factory()->create(['role' => $role]);
        $payload = ['team_id' => $this->team->id, 'status' => 'todo', 'max_tasks' => 3];

        $this->actingAs($user)->put(route('board.wip-limits'), $payload)->assertForbidden();
        $this->get(route('board.index', ['team_id' => $this->team->id]))->assertOk()->assertDontSee(route('board.wip-limits'), false);

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($user);
        $this->putJson('/api/v1/board/wip-limits', $payload)->assertForbidden();

        $this->assertSame(0, WipLimit::count());
    }

    public function test_a_teams_own_lead_can_set_limits(): void
    {
        $lead = User::factory()->create(['role' => User::ROLE_TEAM_LEAD]);
        $this->team->update(['team_lead_id' => $lead->id]);

        $this->actingAs($lead)->put(route('board.wip-limits'), ['team_id' => $this->team->id, 'status' => 'todo', 'max_tasks' => 3])->assertRedirect();

        $this->assertSame(1, WipLimit::count());
    }

    public function test_the_api_returns_limits_and_days_in_status(): void
    {
        WipLimit::create(['team_id' => $this->team->id, 'status' => 'in_progress', 'max_tasks' => 2]);
        $this->travelTo('2026-11-01 09:00:00');
        $this->card();
        $this->travelTo('2026-11-11 09:00:00');
        Sanctum::actingAs($this->admin);

        $column = collect($this->getJson('/api/v1/board?team_id='.$this->team->id)->assertOk()->json('data.columns'))->firstWhere('status', 'in_progress');

        $this->assertSame(2, $column['limit']);
        $this->assertSame(10, $column['tasks'][0]['days_in_status']);

        $this->putJson('/api/v1/board/wip-limits', ['team_id' => $this->team->id, 'status' => 'in_progress', 'max_tasks' => null])->assertOk()
            ->assertJsonPath('data.limits', []);
    }

    public function test_the_age_marker_follows_the_status_not_other_edits(): void
    {
        $this->travelTo('2026-11-01 09:00:00');
        $task = $this->card('Aging');
        $this->travelTo('2026-11-06 09:00:00');
        $task->update(['title' => 'Renamed']); // not a status change

        $cards = $this->actingAs($this->admin)->get(route('board.index'))->assertOk()->assertSee('5d in this status', false);
        $this->assertSame(5, $cards->viewData('columns')['in_progress']->first()->daysInStatus());

        $task->update(['status' => 'in_review']);
        $this->assertSame(0, $this->get(route('board.index'))->viewData('columns')['in_review']->first()->daysInStatus());
    }

    public function test_new_cards_and_finished_cards_show_no_age(): void
    {
        $this->travelTo('2026-11-01 09:00:00');
        $this->card('Done long ago', ['status' => 'done']);
        $this->travelTo('2026-11-21 09:00:00');
        $this->card('Brand new');

        $this->actingAs($this->admin)->get(route('board.index'))->assertOk()->assertDontSee('in this status', false);
    }

    public function test_grouping_by_assignee_orders_and_labels_cards(): void
    {
        $ann = User::factory()->create(['name' => 'Ann Able']);
        $zed = User::factory()->create(['name' => 'Zed Zane']);
        $this->card('Zed work', ['assignee_id' => $zed->id]);
        $this->card('Nobody work');
        $this->card('Ann work', ['assignee_id' => $ann->id]);

        $response = $this->actingAs($this->admin)->get(route('board.index', ['group' => 'assignee']))->assertOk();

        $this->assertSame(['Ann work', 'Zed work', 'Nobody work'], $response->viewData('columns')['in_progress']->pluck('title')->all());
        $response->assertSeeInOrder(['board-lane', 'Ann Able', 'Ann work', 'board-lane', 'Zed Zane', 'Zed work', 'board-lane', 'Unassigned', 'Nobody work'], false);
    }

    public function test_grouping_by_priority_uses_its_own_order(): void
    {
        $this->card('Low', ['priority' => 'low']);
        $this->card('None');
        $this->card('Urgent', ['priority' => 'urgent']);

        $response = $this->actingAs($this->admin)->get(route('board.index', ['group' => 'priority']))->assertOk();

        $this->assertSame(['Urgent', 'Low', 'None'], $response->viewData('columns')['in_progress']->pluck('title')->all());
    }

    public function test_an_unknown_grouping_is_ignored_and_no_grouping_has_no_labels(): void
    {
        $this->card();

        $this->actingAs($this->admin)->get(route('board.index', ['group' => 'nonsense']))->assertOk()
            ->assertDontSee('class="board-lane', false);
    }

    public function test_a_saved_view_can_keep_the_grouping(): void
    {
        $this->actingAs($this->admin)->post(route('saved-views.store'), ['page' => 'board', 'name' => 'By person', 'filters' => ['group' => 'assignee']]);

        $this->assertSame(['group' => 'assignee'], SavedView::sole()->filters);
    }
}
