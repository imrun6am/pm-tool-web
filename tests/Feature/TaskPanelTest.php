<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\TaskTimeLog;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The side panel loads a task into a Turbo Frame, and its forms answer back to
 * the panel so it stays open.
 */
class TaskPanelTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Task $task;

    private const FRAME = ['Turbo-Frame' => 'task-panel'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->task = Task::create(['title' => 'Paneled task', 'status' => 'todo', 'position' => 0]);
    }

    public function test_the_panel_renders_its_frame_and_regions(): void
    {
        $sub = Task::create(['title' => 'A part of it', 'status' => 'todo', 'position' => 0, 'parent_id' => $this->task->id]);

        $this->actingAs($this->admin)->withHeaders(self::FRAME)->get(route('tasks.panel', $this->task))->assertOk()
            ->assertSee('<turbo-frame id="task-panel"', false)
            ->assertSee('id="panel-details"', false)
            ->assertSee('id="task-blocking"', false)
            ->assertSee('id="task-time"', false)
            ->assertSee('id="panel-comments"', false)
            ->assertSee('value="Paneled task"', false)
            ->assertSee($this->task->reference())
            ->assertSee('A part of it')
            ->assertSee('id="flash-toasts"', false)
            ->assertDontSee('<html', false);
    }

    public function test_opening_the_panel_address_directly_goes_to_the_task_page(): void
    {
        $this->actingAs($this->admin)->get(route('tasks.panel', $this->task))->assertRedirect(route('tasks.show', $this->task));
    }

    public function test_the_panel_respects_team_scoping(): void
    {
        $team = Team::create(['name' => 'Other', 'color' => '#e11d48']);
        $this->task->update(['team_id' => $team->id]);
        $dev = User::factory()->create(['role' => User::ROLE_DEVELOPER]);

        $this->actingAs($dev)->withHeaders(self::FRAME)->get(route('tasks.panel', $this->task))->assertForbidden();
    }

    public function test_a_panel_form_answers_back_to_the_panel(): void
    {
        $this->actingAs($this->admin)->from(route('board.index'))
            ->put(route('tasks.update', $this->task), ['title' => 'Renamed', 'status' => 'in_review', 'priority' => 'high', '_panel' => $this->task->id])
            ->assertRedirect(route('tasks.panel', $this->task));

        $fresh = $this->task->fresh();
        $this->assertSame('Renamed', $fresh->title);
        $this->assertSame('in_review', $fresh->status);
    }

    public function test_the_flash_reaches_the_panel(): void
    {
        $this->actingAs($this->admin)->from(route('board.index'))
            ->put(route('tasks.update', $this->task), ['title' => 'Renamed', 'status' => 'todo', '_panel' => $this->task->id]);

        $this->withHeaders(self::FRAME)->get(route('tasks.panel', $this->task))->assertOk()->assertSee('Task updated.');
    }

    public function test_a_validation_failure_comes_back_to_the_panel_with_its_errors(): void
    {
        $this->actingAs($this->admin)->from(route('board.index'))
            ->post(route('tasks.time-logs.store', $this->task), ['duration' => 'soon', 'logged_on' => today()->toDateString(), '_panel' => $this->task->id])
            ->assertRedirect(route('tasks.panel', $this->task));

        $this->withHeaders(self::FRAME)->get(route('tasks.panel', $this->task))->assertOk()
            ->assertSee('Enter the time spent, like 1h 30m.');
    }

    public function test_time_comments_and_blocking_all_work_from_the_panel(): void
    {
        $this->actingAs($this->admin)->from(route('board.index'));

        $this->post(route('tasks.time-logs.store', $this->task), ['duration' => '1h', 'logged_on' => today()->toDateString(), '_panel' => $this->task->id])
            ->assertRedirect(route('tasks.panel', $this->task));
        $this->post(route('tasks.comments.store', $this->task), ['body' => 'Hello', '_panel' => $this->task->id])
            ->assertRedirect(route('tasks.panel', $this->task));
        $this->post(route('tasks.block', $this->task), ['reason' => 'Stuck', '_panel' => $this->task->id])
            ->assertRedirect(route('tasks.panel', $this->task));

        $this->assertSame(1, TaskTimeLog::count());
        $this->assertCount(1, $this->task->comments);
        $this->assertTrue($this->task->fresh()->isBlocked());
    }

    public function test_forms_outside_the_panel_are_untouched(): void
    {
        $this->actingAs($this->admin)->from(route('board.index'))
            ->put(route('tasks.update', $this->task), ['title' => 'Renamed', 'status' => 'todo'])
            ->assertRedirect(route('board.index'));
    }

    public function test_a_malformed_panel_marker_is_ignored(): void
    {
        $this->actingAs($this->admin)->from(route('board.index'))
            ->put(route('tasks.update', $this->task), ['title' => 'Renamed', 'status' => 'todo', '_panel' => 'https://example.com'])
            ->assertRedirect(route('board.index'));
    }

    public function test_the_marker_cannot_open_a_task_out_of_reach(): void
    {
        $team = Team::create(['name' => 'Other', 'color' => '#e11d48']);
        $hidden = Task::create(['title' => 'Hidden', 'status' => 'todo', 'position' => 0, 'team_id' => $team->id]);
        $dev = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $own = Task::create(['title' => 'Mine', 'status' => 'todo', 'position' => 0, 'assignee_id' => $dev->id]);

        $redirect = $this->actingAs($dev)->from(route('board.index'))
            ->post(route('tasks.comments.store', $own), ['body' => 'Hi', '_panel' => $hidden->id]);

        $redirect->assertRedirect(route('tasks.panel', $hidden));
        $this->withHeaders(self::FRAME)->get(route('tasks.panel', $hidden))->assertForbidden();
    }

    public function test_board_cards_and_the_task_list_link_to_the_panel(): void
    {
        $panel = route('tasks.panel', $this->task);

        $this->actingAs($this->admin)->get(route('board.index'))->assertOk()
            ->assertSee('data-task-panel="'.$panel.'"', false)
            ->assertSee('id="task-panel"', false);
        $this->get(route('tasks.index'))->assertOk()
            ->assertSee('data-task-panel="'.$panel.'"', false)
            ->assertSee('id="task-panel"', false);
    }
}
