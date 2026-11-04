<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\TasksheetEntry;
use App\Models\Team;
use App\Models\User;
use App\Services\Ai\AiTransport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FakeAiTransport;
use Tests\TestCase;

/** A task moved to Done is added to its assignee's day-end result for today. */
class TaskDoneTasksheetTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    private User $ana;

    private User $lead;

    protected function setUp(): void
    {
        parent::setUp();

        $this->team = Team::create(['name' => 'Alpha', 'color' => '#4f46e5']);
        $this->ana = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $this->team->members()->attach($this->ana);
        $this->lead = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($this->lead);
    }

    private function task(array $attributes = []): Task
    {
        return Task::create($attributes + ['title' => 'Fix coupon bug', 'status' => 'in_review', 'team_id' => $this->team->id, 'assignee_id' => $this->ana->id]);
    }

    private function row(?User $user = null, ?Team $team = null): ?TasksheetEntry
    {
        return TasksheetEntry::where('team_id', ($team ?? $this->team)->id)->where('user_id', ($user ?? $this->ana)->id)
            ->whereDate('date', today()->toDateString())->first();
    }

    private function seedRow(array $attributes): TasksheetEntry
    {
        $entry = new TasksheetEntry(['team_id' => $this->team->id, 'user_id' => $this->ana->id, 'date' => today()->toDateString()]);
        $entry->fill($attributes)->save();

        return $entry;
    }

    public function test_moving_a_card_to_done_on_the_board_adds_a_line_to_a_new_row(): void
    {
        $task = $this->task();

        $this->patchJson(route('board.move', $task), ['status' => 'done'])->assertOk();

        $this->assertStringContainsString('Completed: Fix coupon bug', $this->row()->result);
    }

    public function test_the_api_and_the_task_page_add_it_too(): void
    {
        $viaApi = $this->task(['title' => 'Via API']);
        $viaModel = $this->task(['title' => 'Via update']);

        Sanctum::actingAs($this->lead, ['*']);
        $this->patchJson('/api/v1/board/tasks/'.$viaApi->id, ['status' => 'done'])->assertOk();
        $viaModel->update(['status' => 'done']);

        $this->assertStringContainsString('Via API', $this->row()->result);
        $this->assertStringContainsString('Via update', $this->row()->result);
    }

    public function test_existing_text_and_feedback_are_kept(): void
    {
        $this->seedRow(['result' => '<div>Met the vendor</div>', 'feedback' => '<div>Lead note</div>', 'plan' => '<div>My plan</div>']);

        $this->task()->update(['status' => 'done']);
        $this->task(['title' => 'Second task'])->update(['status' => 'done']);

        $row = $this->row();
        $this->assertStringContainsString('Met the vendor', $row->result);
        $this->assertSame(1, substr_count($row->result, '<ul>')); // both lines share one list
        $this->assertSame(2, substr_count($row->result, '<li>'));
        $this->assertStringContainsString('Lead note', $row->feedback);
        $this->assertStringContainsString('My plan', $row->plan);
    }

    public function test_finishing_twice_lists_it_once_even_with_special_characters(): void
    {
        $task = $this->task(['title' => "Fix \"A\" & B's\n  <checkout>"]);

        $task->update(['status' => 'done']);
        $task->update(['status' => 'in_review']);
        $this->assertNull($this->row()->result);

        $task->update(['status' => 'done']);
        $task->update(['status' => 'recheck']);
        $task->update(['status' => 'done']);

        $this->assertSame(1, substr_count($this->row()->result, '<li>'));
        $this->assertStringNotContainsString('<checkout>', $this->row()->result);
    }

    public function test_reopening_removes_only_its_own_line(): void
    {
        $this->seedRow(['result' => '<div>Met the vendor</div>']);
        $keep = $this->task(['title' => 'Keep me']);
        $keep->update(['status' => 'done']);
        $task = $this->task();
        $task->update(['status' => 'done']);

        $task->update(['status' => 'in_review']);

        $result = $this->row()->result;
        $this->assertStringNotContainsString('Fix coupon bug', $result);
        $this->assertStringContainsString('Keep me', $result);
        $this->assertStringContainsString('Met the vendor', $result);
    }

    public function test_a_line_the_member_rewrote_is_left_alone(): void
    {
        $task = $this->task();
        $task->update(['status' => 'done']);
        $this->row()->update(['result' => '<div>Shipped the coupon fix</div>']);

        $task->update(['status' => 'in_review']);

        $this->assertStringContainsString('Shipped the coupon fix', $this->row()->result);
    }

    public function test_archiving_keeps_the_line_and_restoring_adds_nothing(): void
    {
        $task = $this->task();
        $task->update(['status' => 'done']);
        $task->update(['status' => 'archive']);
        $this->assertStringContainsString('Fix coupon bug', $this->row()->result);

        $archived = $this->task(['title' => 'Old work', 'status' => 'archive']);
        $archived->update(['status' => 'done']);
        $this->assertStringNotContainsString('Old work', $this->row()->result);
    }

    public function test_a_card_created_in_done_counts(): void
    {
        $this->task(['status' => 'done']);

        $this->assertStringContainsString('Fix coupon bug', $this->row()->result);
    }

    public function test_nothing_happens_without_an_assignee_or_on_full_day_leave(): void
    {
        $this->task(['assignee_id' => null, 'title' => 'Nobody'])->update(['status' => 'done']);
        $this->assertNull($this->row());

        $this->seedRow(['leave_type' => 'sick']);
        $this->task()->update(['status' => 'done']);
        $this->assertNull($this->row()->result);
    }

    public function test_half_day_leave_still_gets_the_line(): void
    {
        $this->seedRow(['leave_type' => 'half_day']);

        $this->task()->update(['status' => 'done']);

        $this->assertStringContainsString('Fix coupon bug', $this->row()->result);
    }

    public function test_the_right_teams_sheet_is_used(): void
    {
        $beta = Team::create(['name' => 'Beta', 'color' => '#db2777']);
        $gamma = Team::create(['name' => 'Gamma', 'color' => '#059669']);

        // Their team isn't the task's: their only team's sheet.
        $this->task(['team_id' => $beta->id, 'title' => 'Helped Beta'])->update(['status' => 'done']);
        $this->assertStringContainsString('Helped Beta', $this->row()->result);
        $this->assertNull($this->row(team: $beta));

        // On several teams: the task's team.
        $beta->members()->attach($this->ana);
        $this->task(['team_id' => $beta->id, 'title' => 'Beta work'])->update(['status' => 'done']);
        $this->assertStringContainsString('Beta work', $this->row(team: $beta)->result);

        // On several teams and none is the task's: skipped.
        $this->task(['team_id' => $gamma->id, 'title' => 'Gamma work'])->update(['status' => 'done']);
        $this->assertNull($this->row(team: $gamma));
        $this->assertStringNotContainsString('Gamma work', $this->row()->result);
        $this->assertStringNotContainsString('Gamma work', $this->row(team: $beta)->result);
    }

    public function test_a_subtask_names_its_parent(): void
    {
        $parent = $this->task(['title' => 'Checkout']);
        $this->task(['title' => 'Write tests', 'parent_id' => $parent->id])->update(['status' => 'done']);

        $this->assertStringContainsString('Completed: Checkout: Write tests', $this->row()->result);
    }

    public function test_without_a_signed_in_user_nothing_is_written(): void
    {
        $task = $this->task();
        auth()->logout();

        $task->update(['status' => 'done']);

        $this->assertNull($this->row());
    }

    public function test_the_draft_is_told_what_the_result_already_says(): void
    {
        config(['services.anthropic.key' => 'test-key']);
        $claude = new FakeAiTransport;
        $this->app->instance(AiTransport::class, $claude);
        $task = $this->task();
        $task->update(['status' => 'done']);
        $claude->respond(['items' => [], 'unconfirmed' => []]);

        $this->postJson(route('ai.tasksheet.draft'), [
            'team_id' => $this->team->id, 'user_id' => $this->ana->id, 'date' => today()->toDateString(),
            'kind' => 'result', 'result' => $this->row()->result,
        ])->assertStatus(409)->assertJsonPath('message', 'The result already covers this day’s board activity.');

        $this->assertStringContainsString('Already in the result', $claude->prompt());
    }
}
