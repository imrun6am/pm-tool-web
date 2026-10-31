<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Project;
use App\Models\Release;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Board, tasksheet drawer, settings and detail-page layouts
 * (changes vibrant-kanban-board, tasksheet-entry-editor,
 * polish-detail-pages-and-settings).
 */
class WorkspacePagesTest extends TestCase
{
    use RefreshDatabase;

    private function release(?Team $team = null): Release
    {
        $project = Project::create(['name' => 'Checkout', 'color' => '#4f46e5']);
        $team ??= Team::create(['name' => 'Alpha', 'color' => '#0891b2']);

        return Release::create([
            'project_id' => $project->id, 'team_id' => $team->id, 'name' => 'Checkout v9',
            'year' => 2026, 'quarter' => 3, 'start_date' => '2026-07-10', 'end_date' => '2026-07-30',
        ]);
    }

    // ---------------------------------------------------------------- board

    public function test_board_summary_counts_tasks_and_done_share(): void
    {
        $release = $this->release();
        foreach (['todo', 'in_progress', 'in_review', 'done'] as $i => $status) {
            Task::create(['release_id' => $release->id, 'title' => "Card {$i}", 'status' => $status]);
        }

        $html = $this->actingAs(User::factory()->create())->get(route('board.index'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#class="board-total[^"]*">4</span>#', $html);
        $this->assertMatchesRegularExpression('#class="board-done-pct[^"]*">25%</span>#', $html);
    }

    public function test_my_tasks_toggle_builds_url_and_reflects_state(): void
    {
        $user = User::factory()->create();
        $release = $this->release();

        $this->actingAs($user)->get(route('board.index', ['release_id' => $release->id]))
            ->assertOk()
            ->assertSee('href="'.e(route('board.index', ['release_id' => $release->id, 'assignee_id' => $user->id])).'" role="button" aria-pressed="false"', false);

        $this->actingAs($user)->get(route('board.index', ['release_id' => $release->id, 'assignee_id' => $user->id]))
            ->assertOk()
            ->assertSee('href="'.e(route('board.index', ['release_id' => $release->id])).'" role="button" aria-pressed="true"', false);
    }

    public function test_cards_offer_a_move_menu_and_flag_overdue_work(): void
    {
        $release = $this->release();
        $task = Task::create([
            'release_id' => $release->id, 'title' => 'Late card', 'status' => 'in_progress',
            'due_date' => today()->subDays(3)->toDateString(),
        ]);

        $html = $this->actingAs(User::factory()->create())->get(route('board.index'))->assertOk()
            ->assertSee('Move “Late card” to')
            ->assertSee('Overdue · '.today()->subDays(3)->format('M j'))
            ->getContent();

        // One option per status, with the card's current status preselected.
        preg_match('#<select id="move-'.$task->id.'"[^>]*>(.*?)</select>#s', $html, $menu);
        $this->assertSame(count(Task::STATUSES), substr_count($menu[1], '<option '));
        $this->assertStringContainsString('<option value="in_progress" selected>', $menu[1]);
    }

    // ------------------------------------------------------------ tasksheet

    public function test_tasksheet_rows_offer_fill_in_then_edit_in_a_drawer(): void
    {
        $team = Team::create(['name' => 'Core', 'color' => '#0891b2']);
        $dev = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $team->members()->attach($dev);
        $lead = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($lead)->get(route('tasksheet.index', ['team' => $team->id]))
            ->assertOk()
            ->assertSee('Fill in')
            ->assertSee('x-teleport="body"', false)
            ->assertSee('aria-labelledby="ts-'.$dev->id.'-title"', false)
            ->assertSee('Casual leave')
            ->assertSee('Save row');

        $this->actingAs($lead)->put(route('tasksheet.entries.upsert'), [
            'team_id' => $team->id, 'user_id' => $dev->id, 'date' => today()->toDateString(),
            'plan' => '<div>Ship it</div>', 'work_points' => 5,
        ])->assertSessionHasNoErrors();

        $this->actingAs($lead)->get(route('tasksheet.index', ['team' => $team->id]))
            ->assertOk()
            ->assertDontSee('Fill in')
            ->assertSee('Edit');
    }

    public function test_drawer_reopens_with_input_after_a_failed_save(): void
    {
        $team = Team::create(['name' => 'Core', 'color' => '#0891b2']);
        $dev = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $team->members()->attach($dev);

        $this->actingAs($dev)
            ->from(route('tasksheet.index', ['team' => $team->id]))
            ->followingRedirects()
            ->put(route('tasksheet.entries.upsert'), [
                'team_id' => $team->id, 'user_id' => $dev->id, 'date' => today()->toDateString(),
                'plan' => 'Keep this plan text', 'work_points' => -4,
            ])
            ->assertOk()
            ->assertSee('editing: true,', false)
            ->assertSee('value="Keep this plan text"', false)
            ->assertSee('value="-4"', false);
    }

    public function test_member_drawer_has_no_feedback_panel(): void
    {
        $team = Team::create(['name' => 'Core', 'color' => '#0891b2']);
        $dev = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $team->members()->attach($dev);

        $this->actingAs($dev)->get(route('tasksheet.index', ['team' => $team->id]))
            ->assertOk()
            ->assertSee('Save row')
            ->assertDontSee('Feedback')
            ->assertDontSee('name="feedback"', false);
    }

    // ------------------------------------------------------------- settings

    public function test_profile_is_a_sectioned_settings_page(): void
    {
        $user = User::factory()->create(['name' => 'Ada Lovelace', 'role' => User::ROLE_DEVELOPER]);
        Team::create(['name' => 'Core', 'color' => '#0891b2'])->members()->attach($user);

        $this->actingAs($user)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('href="#profile"', false)
            ->assertSee('href="#password"', false)
            ->assertSee('href="#danger-zone"', false)
            ->assertSee('id="danger-zone"', false)
            ->assertSee('Ada Lovelace')
            ->assertSee('Member since')
            ->assertSee('Core')
            ->assertSee('At least 8 characters');
    }

    // --------------------------------------------------------- detail pages

    public function test_detail_pages_render_breadcrumbs(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $release = $this->release();
        $task = Task::create(['release_id' => $release->id, 'title' => 'Wire payments', 'status' => 'todo']);
        $event = Event::create([
            'title' => 'Retro', 'type' => 'meeting',
            'starts_at' => '2026-07-15 10:00:00', 'ends_at' => '2026-07-15 11:00:00', 'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->get(route('tasks.show', $task))->assertOk()
            ->assertSee('aria-label="Breadcrumb"', false)
            ->assertSee('href="'.route('releases.show', $release).'"', false)
            ->assertSeeInOrder(['Checkout v9', 'Wire payments']);

        $this->actingAs($admin)->get(route('events.show', $event))->assertOk()
            ->assertSee('aria-label="Breadcrumb"', false)
            ->assertSeeInOrder(['Calendar', 'Retro']);

        $this->actingAs($admin)->get(route('releases.show', $release))->assertOk()
            ->assertSee('href="'.route('releases.index').'"', false)
            ->assertSeeInOrder(['Releases', 'Checkout v9']);
    }

    public function test_team_page_uses_the_kpi_strip(): void
    {
        $team = Team::create(['name' => 'Alpha', 'color' => '#0891b2']);

        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))->get(route('teams.show', $team))
            ->assertOk()
            ->assertSee('class="stat-strip', false)
            ->assertSee('class="stat-cell', false);
    }
}
