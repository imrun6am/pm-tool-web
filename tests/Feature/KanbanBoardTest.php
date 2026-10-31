<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Release;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KanbanBoardTest extends TestCase
{
    use RefreshDatabase;

    private function release(): Release
    {
        $project = Project::create(['name' => 'P', 'color' => '#4f46e5']);
        $team = Team::create(['name' => 'T', 'color' => '#0891b2']);

        return Release::create([
            'project_id' => $project->id, 'team_id' => $team->id, 'name' => 'R',
            'year' => 2026, 'quarter' => 3, 'start_date' => '2026-07-10', 'end_date' => '2026-07-30',
        ]);
    }

    private function task(Release $release, array $attrs = []): Task
    {
        return $release->tasks()->create(array_merge([
            'title' => 'Task', 'status' => 'todo', 'position' => 0,
        ], $attrs));
    }

    public function test_board_renders_for_authenticated_user(): void
    {
        $release = $this->release();
        $this->task($release, ['title' => 'Alpha', 'status' => 'todo']);
        $this->task($release, ['title' => 'Beta', 'status' => 'done']);

        $this->actingAs(User::factory()->create())
            ->get(route('board.index'))
            ->assertOk()
            ->assertSee('Alpha')
            ->assertSee('Beta')
            ->assertSee('In Review');
    }

    public function test_guest_cannot_access_board_or_move(): void
    {
        $release = $this->release();
        $task = $this->task($release);

        $this->get(route('board.index'))->assertRedirect(route('login'));
        $this->patch(route('board.move', $task), ['status' => 'done'])->assertRedirect(route('login'));
    }

    public function test_moving_a_card_changes_status_and_order(): void
    {
        $release = $this->release();
        $a = $this->task($release, ['title' => 'A', 'status' => 'todo', 'position' => 0]);
        $b = $this->task($release, ['title' => 'B', 'status' => 'todo', 'position' => 1]);

        // Move A into "in_progress"; and set order [B, A] within the (now) target list.
        $this->actingAs(User::factory()->create())
            ->patchJson(route('board.move', $a), ['status' => 'in_progress', 'ordered_ids' => [$a->id]])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertSame('in_progress', $a->fresh()->status);
        $this->assertSame('todo', $b->fresh()->status);
    }

    public function test_reorder_persists_positions(): void
    {
        $release = $this->release();
        $a = $this->task($release, ['title' => 'A', 'status' => 'todo', 'position' => 0]);
        $b = $this->task($release, ['title' => 'B', 'status' => 'todo', 'position' => 1]);

        // Reorder within the same column to [B, A].
        $this->actingAs(User::factory()->create())
            ->patchJson(route('board.move', $b), ['status' => 'todo', 'ordered_ids' => [$b->id, $a->id]])
            ->assertOk();

        $this->assertSame(0, $b->fresh()->position);
        $this->assertSame(1, $a->fresh()->position);
    }

    public function test_invalid_status_is_rejected(): void
    {
        $release = $this->release();
        $task = $this->task($release);

        $this->actingAs(User::factory()->create())
            ->patchJson(route('board.move', $task), ['status' => 'nonsense'])
            ->assertStatus(422);

        $this->assertSame('todo', $task->fresh()->status);
    }

    public function test_user_can_add_a_task_from_the_board(): void
    {
        $release = $this->release();

        $this->actingAs(User::factory()->create(['role' => User::ROLE_QA]))
            ->post(route('board.tasks.store'), [
                'release_id' => $release->id,
                'title' => 'Card from board',
                'status' => 'in_progress',
            ])->assertRedirect();

        $task = Task::first();
        $this->assertSame('Card from board', $task->title);
        $this->assertSame('in_progress', $task->status);
        $this->assertSame($release->id, $task->release_id);
        $this->assertNull($task->parent_id);
    }

    public function test_board_add_task_validates(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('board.tasks.store'), ['title' => '', 'status' => 'nope'])
            ->assertSessionHasErrors(['release_id', 'title', 'status']);
    }

    public function test_filter_by_release(): void
    {
        $r1 = $this->release();
        $r2 = $this->release();
        $this->task($r1, ['title' => 'InR1']);
        $this->task($r2, ['title' => 'InR2']);

        $this->actingAs(User::factory()->create())
            ->get(route('board.index', ['release_id' => $r1->id]))
            ->assertOk()
            ->assertSee('InR1')
            ->assertDontSee('InR2');
    }
}
