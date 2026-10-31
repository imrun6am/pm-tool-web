<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Comment;
use App\Models\Project;
use App\Models\Release;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CollaborationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function viewer(): User
    {
        return User::factory()->create(['role' => User::ROLE_VIEWER]);
    }

    private function release(): Release
    {
        $project = Project::create(['name' => 'P', 'color' => '#4f46e5']);
        $team = Team::create(['name' => 'T', 'color' => '#0891b2']);

        return Release::create([
            'project_id' => $project->id, 'team_id' => $team->id, 'name' => 'R',
            'year' => 2026, 'quarter' => 3, 'start_date' => '2026-07-10', 'end_date' => '2026-07-30',
        ]);
    }

    public function test_any_user_can_add_task_and_subtask(): void
    {
        $release = $this->release();

        $this->actingAs($this->viewer())
            ->post(route('releases.tasks.store', $release), ['title' => 'Task A'])
            ->assertRedirect();

        $task = Task::first();
        $this->assertSame('Task A', $task->title);
        $this->assertSame('todo', $task->status);
        $this->assertNull($task->parent_id);

        $this->actingAs($this->viewer())
            ->post(route('tasks.subtasks.store', $task), ['title' => 'Sub A'])
            ->assertRedirect();

        $sub = Task::where('parent_id', $task->id)->first();
        $this->assertSame('Sub A', $sub->title);
        $this->assertSame($release->id, $sub->release_id);
    }

    public function test_subtask_cannot_have_its_own_subtask(): void
    {
        $release = $this->release();
        $user = $this->viewer();

        $this->actingAs($user)->post(route('releases.tasks.store', $release), ['title' => 'Task']);
        $task = Task::first();
        $this->actingAs($user)->post(route('tasks.subtasks.store', $task), ['title' => 'Sub']);
        $sub = Task::where('parent_id', $task->id)->first();

        $this->actingAs($user)
            ->post(route('tasks.subtasks.store', $sub), ['title' => 'Nested'])
            ->assertSessionHas('error');

        $this->assertSame(2, Task::count());
    }

    public function test_users_can_comment_on_release_and_task(): void
    {
        $release = $this->release();
        $user = $this->viewer();

        $this->actingAs($user)->post(route('releases.comments.store', $release), ['body' => 'Release note']);
        $this->actingAs($user)->post(route('releases.tasks.store', $release), ['title' => 'T']);
        $task = Task::first();
        $this->actingAs($user)->post(route('tasks.comments.store', $task), ['body' => 'Task note']);

        $this->assertSame(1, $release->comments()->count());
        $this->assertSame(1, $task->comments()->count());
    }

    public function test_only_author_or_admin_can_delete_a_comment(): void
    {
        $release = $this->release();
        $author = $this->viewer();
        $other = $this->viewer();

        $this->actingAs($author)->post(route('releases.comments.store', $release), ['body' => 'Mine']);
        $comment = Comment::first();

        // A different non-admin user cannot delete it.
        $this->actingAs($other)->delete(route('comments.destroy', $comment))->assertForbidden();
        $this->assertDatabaseHas('comments', ['id' => $comment->id]);

        // The author can.
        $this->actingAs($author)->delete(route('comments.destroy', $comment))->assertRedirect();
        $this->assertDatabaseMissing('comments', ['id' => $comment->id]);

        // An admin can delete anyone's comment.
        $this->actingAs($author)->post(route('releases.comments.store', $release), ['body' => 'Second']);
        $second = Comment::first();
        $this->actingAs($this->admin())->delete(route('comments.destroy', $second))->assertRedirect();
        $this->assertDatabaseMissing('comments', ['id' => $second->id]);
    }

    public function test_off_day_rules_and_permissions(): void
    {
        $release = $this->release();
        $admin = $this->admin();

        // Within window → ok.
        $this->actingAs($admin)->post(route('releases.offdays.store', $release), ['date' => '2026-07-15', 'reason' => 'Holiday'])->assertRedirect();
        $this->assertSame(1, $release->offDays()->count());

        // Duplicate → rejected.
        $this->actingAs($admin)->post(route('releases.offdays.store', $release), ['date' => '2026-07-15'])->assertSessionHasErrors('date');

        // Outside window → rejected.
        $this->actingAs($admin)->post(route('releases.offdays.store', $release), ['date' => '2026-08-15'])->assertSessionHasErrors('date');

        // Viewer cannot mark off-days.
        $this->actingAs($this->viewer())->post(route('releases.offdays.store', $release), ['date' => '2026-07-16'])->assertForbidden();

        $this->assertSame(1, $release->fresh()->offDays()->count());
    }

    public function test_mark_weekends_helper_marks_only_weekends(): void
    {
        $release = $this->release();

        $this->actingAs($this->admin())->post(route('releases.offdays.weekends', $release))->assertRedirect();

        $expected = 0;
        $cursor = Carbon::parse('2026-07-10');
        $end = Carbon::parse('2026-07-30');
        while ($cursor->lte($end)) {
            if ($cursor->isWeekend()) {
                $expected++;
            }
            $cursor->addDay();
        }

        $this->assertSame($expected, $release->offDays()->count());
        $this->assertTrue($release->offDays->every(fn ($o) => $o->date->isWeekend()));
    }

    public function test_activity_records_causer_and_old_new_values(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $release = $this->release(); // created → activity with causer
        $release->update(['name' => 'Renamed Release']);

        $updated = Activity::where('event', 'updated')
            ->where('subject_type', Release::class)
            ->where('subject_id', $release->id)
            ->latest()->first();

        $this->assertNotNull($updated);
        $this->assertSame($admin->id, $updated->causer_id);

        $changes = $updated->changes();
        $this->assertArrayHasKey('name', $changes);
        $this->assertSame('R', $changes['name']['old']);
        $this->assertSame('Renamed Release', $changes['name']['new']);
    }

    public function test_assignee_options_are_team_members_only(): void
    {
        $release = $this->release();
        $member = User::factory()->create(['name' => 'Inteam Ivy', 'role' => User::ROLE_DEVELOPER]);
        User::factory()->create(['name' => 'Outsider Oz', 'role' => User::ROLE_DEVELOPER]);
        $release->team->members()->attach($member);

        $task = Task::create(['release_id' => $release->id, 'title' => 'T1', 'status' => 'todo']);

        $this->actingAs($this->admin())->get(route('tasks.show', $task))
            ->assertOk()
            ->assertSee('Inteam Ivy')
            ->assertDontSee('Outsider Oz');

        $this->actingAs($this->admin())->get(route('releases.show', $release))
            ->assertOk()
            ->assertSee('Inteam Ivy')
            ->assertDontSee('Outsider Oz');
    }

    public function test_assignee_outside_team_is_rejected_but_member_accepted(): void
    {
        $release = $this->release();
        $member = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $outsider = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $release->team->members()->attach($member);

        $task = Task::create(['release_id' => $release->id, 'title' => 'T1', 'status' => 'todo']);

        $this->actingAs($this->admin())->put(route('tasks.update', $task), [
            'title' => 'T1', 'assignee_id' => $outsider->id,
        ])->assertSessionHasErrors('assignee_id');
        $this->assertNull($task->refresh()->assignee_id);

        $this->actingAs($this->admin())->put(route('tasks.update', $task), [
            'title' => 'T1', 'assignee_id' => $member->id,
        ])->assertRedirect();
        $this->assertSame($member->id, $task->refresh()->assignee_id);
    }

    public function test_former_member_assignee_is_kept_and_still_listed(): void
    {
        $release = $this->release();
        $member = User::factory()->create(['name' => 'Departed Dana', 'role' => User::ROLE_DEVELOPER]);
        $release->team->members()->attach($member);

        $task = Task::create(['release_id' => $release->id, 'title' => 'T1', 'status' => 'todo', 'assignee_id' => $member->id]);

        $release->team->members()->detach($member);

        // Still listed (as current assignee) and keeping them assigned is allowed.
        $this->actingAs($this->admin())->get(route('tasks.show', $task))
            ->assertOk()->assertSee('Departed Dana');

        $this->actingAs($this->admin())->put(route('tasks.update', $task), [
            'title' => 'T1 renamed', 'assignee_id' => $member->id,
        ])->assertRedirect();
        $this->assertSame($member->id, $task->refresh()->assignee_id);
    }
}
