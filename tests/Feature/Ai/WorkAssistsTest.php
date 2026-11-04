<?php

namespace Tests\Feature\Ai;

use App\Models\AiUsageLog;
use App\Models\MeetingNote;
use App\Models\Task;
use App\Models\TasksheetEntry;
use App\Models\User;
use App\Services\Ai\AiFeatures;
use Illuminate\Support\Carbon;

/** Tasksheet drafts, release retrospectives and assignee suggestions. */
class WorkAssistsTest extends AiTestCase
{
    private function draft(User $by, User $member, string $kind, array $extra = [])
    {
        return $this->actingAs($by)->postJson(route('ai.tasksheet.draft'), $extra + [
            'team_id' => $this->team->id, 'user_id' => $member->id, 'date' => today()->toDateString(), 'kind' => $kind,
        ]);
    }

    private function task(array $attributes = []): Task
    {
        return Task::create($attributes + ['title' => 'Some task', 'status' => 'todo', 'team_id' => $this->team->id]);
    }

    // -- Tasksheet drafts ------------------------------------------------------

    public function test_a_plan_is_drafted_from_open_tasks_and_the_previous_day(): void
    {
        $ana = $this->user(User::ROLE_DEVELOPER, $this->team);
        $this->task(['title' => 'Fix coupon bug', 'assignee_id' => $ana->id, 'status' => 'in_progress', 'due_date' => today()->subDay()]);
        $this->task(['title' => 'Finished thing', 'assignee_id' => $ana->id, 'status' => 'done']);
        $this->task(['title' => 'Other teams work', 'assignee_id' => $ana->id, 'team_id' => $this->otherTeam->id]);
        $this->task(['title' => 'Someone elses work']);

        $entry = new TasksheetEntry(['team_id' => $this->team->id, 'user_id' => $ana->id, 'date' => today()->subDay()->toDateString()]);
        $entry->fill(['plan' => '<div>Yesterday plan</div>', 'result' => '<div>Yesterday result</div>', 'feedback' => '<div>Secret lead note</div>'])->save();

        $this->claude->respond(['items' => ['Fix the coupon bug <b>now</b>']]);

        $out = $this->draft($ana, $ana, 'plan')->assertOk()->json();

        $prompt = $this->claude->prompt();
        $this->assertStringContainsString('Fix coupon bug', $prompt);
        $this->assertStringContainsString('(overdue)', $prompt);
        $this->assertStringContainsString('Yesterday result', $prompt);
        $this->assertStringNotContainsString('Finished thing', $prompt);
        $this->assertStringNotContainsString('Other teams work', $prompt);
        $this->assertStringNotContainsString('Someone elses work', $prompt);
        $this->assertStringNotContainsString('Secret lead note', $prompt);
        $this->assertStringContainsString('<li>', $out['html']);
        $this->assertStringNotContainsString('<b>', $out['html']);
    }

    public function test_a_result_uses_the_days_activity_and_lists_what_it_cant_confirm(): void
    {
        $ana = $this->user(User::ROLE_DEVELOPER, $this->team);
        $this->actingAs($ana);

        $moved = $this->task(['title' => 'Fix coupon bug', 'assignee_id' => $ana->id]);
        $moved->update(['status' => 'in_review']);
        $elsewhere = $this->task(['title' => 'Other teams move', 'assignee_id' => $ana->id, 'team_id' => $this->otherTeam->id]);
        $elsewhere->update(['status' => 'in_review']);
        $moved->comments()->create(['user_id' => $ana->id, 'body' => 'Note']);

        $this->claude->respond(['items' => ['Moved the coupon fix to review'], 'unconfirmed' => ['Call the vendor']]);

        $out = $this->draft($ana, $ana, 'result', ['plan' => '<div>Call the vendor</div>'])->assertOk()->json();

        $prompt = $this->claude->prompt();
        $this->assertStringContainsString('Moved “Fix coupon bug” from', $prompt);
        $this->assertStringContainsString('Commented on “Fix coupon bug”', $prompt);
        $this->assertStringContainsString('Call the vendor', $prompt);
        $this->assertStringNotContainsString('Other teams move', $prompt);
        $this->assertSame(['Call the vendor'], $out['unconfirmed']);
        $this->assertStringNotContainsString('Call the vendor', $out['html']);
    }

    public function test_activity_from_another_day_is_not_used(): void
    {
        $ana = $this->user(User::ROLE_DEVELOPER, $this->team);
        $this->actingAs($ana);
        Carbon::setTestNow(now()->subDays(2));
        $this->task(['title' => 'Old move', 'assignee_id' => $ana->id])->update(['status' => 'in_review']);
        Carbon::setTestNow();

        $this->draft($ana, $ana, 'result')->assertStatus(409);
        $this->assertCount(0, $this->claude->requests);
    }

    public function test_nothing_to_draft_from_answers_without_calling_claude(): void
    {
        $ana = $this->user(User::ROLE_DEVELOPER, $this->team);

        $this->draft($ana, $ana, 'plan')->assertStatus(409);
        $this->draft($ana, $ana, 'result')->assertStatus(409);

        $this->assertCount(0, $this->claude->requests);
    }

    public function test_only_someone_who_may_edit_the_row_can_draft_it(): void
    {
        $ana = $this->user(User::ROLE_DEVELOPER, $this->team);
        $ben = $this->user(User::ROLE_DEVELOPER, $this->team);
        $outsider = $this->user(User::ROLE_DEVELOPER, $this->otherTeam);
        $this->task(['assignee_id' => $ana->id]);

        $this->draft($ben, $ana, 'plan')->assertForbidden();
        $this->draft($outsider, $outsider, 'plan')->assertForbidden();

        $this->claude->respond(['items' => ['Do the thing']]);
        $this->draft($this->user(User::ROLE_TEAM_LEAD), $ana, 'plan')->assertOk();
    }

    // -- Release retrospective -------------------------------------------------

    public function test_a_retrospective_states_the_apps_own_numbers(): void
    {
        $lead = $this->user(User::ROLE_ADMIN);
        $this->actingAs($lead);
        $release = $this->release();

        $late = Task::create(['release_id' => $release->id, 'title' => 'Late task', 'status' => 'in_progress', 'due_date' => today()->subDays(3)]);
        $late->update(['status' => 'done']);
        $reworked = Task::create(['release_id' => $release->id, 'title' => 'Reworked task', 'status' => 'recheck']);
        $reworked->update(['status' => 'done']);
        Task::create(['release_id' => $release->id, 'title' => 'Open task', 'status' => 'todo']);
        Task::create(['release_id' => $release->id, 'title' => 'Archived task', 'status' => 'archive']);

        $this->claude->respond(['summary' => 'It went fine.', 'went_well' => ['Shipped checkout'], 'slipped' => [], 'next_time' => ['Plan QA earlier']]);

        $markdown = $this->postJson(route('ai.release-retro', $release))->assertOk()->json('markdown');

        $this->assertStringContainsString('2 of 3 tasks finished, 1 finished late, 1 sent back for rework', $markdown);
        $this->assertStringContainsString('### What went well', $markdown);
        $this->assertStringNotContainsString('What slipped', $markdown);
        $this->assertStringContainsString('Open task', $this->claude->prompt());
        $this->assertStringNotContainsString('Archived task', $this->claude->prompt());
    }

    public function test_a_note_the_planner_may_not_read_stays_out_of_the_retrospective(): void
    {
        $lead = $this->user(User::ROLE_TEAM_LEAD);
        $release = $this->release();
        MeetingNote::create(['release_id' => $release->id, 'created_by' => $lead->id, 'title' => 'Open sync', 'meeting_date' => '2026-07-06', 'body' => '<div>Public detail</div>', 'visibility' => MeetingNote::VISIBILITY_EVERYONE]);
        MeetingNote::create(['release_id' => $release->id, 'created_by' => $this->user(User::ROLE_TEAM_LEAD)->id, 'title' => 'Private sync', 'meeting_date' => '2026-07-07', 'body' => '<div>Secret detail</div>', 'visibility' => MeetingNote::VISIBILITY_ATTENDEES]);

        $this->claude->respond(['summary' => 'Fine.', 'went_well' => [], 'slipped' => [], 'next_time' => []]);

        $this->actingAs($lead)->postJson(route('ai.release-retro', $release))->assertOk();

        $this->assertStringContainsString('Public detail', $this->claude->prompt());
        $this->assertStringNotContainsString('Secret detail', $this->claude->prompt());
    }

    public function test_only_release_planners_can_draft_a_retrospective(): void
    {
        $release = $this->release();

        $this->actingAs($this->user(User::ROLE_DEVELOPER, $this->team))
            ->postJson(route('ai.release-retro', $release))->assertForbidden();
        $this->assertCount(0, $this->claude->requests);
    }

    public function test_an_empty_release_answers_without_calling_claude(): void
    {
        $this->actingAs($this->user(User::ROLE_ADMIN))
            ->postJson(route('ai.release-retro', $this->release()))->assertStatus(409);

        $this->assertCount(0, $this->claude->requests);
    }

    // -- Assignee suggestions --------------------------------------------------

    public function test_suggestions_are_limited_to_people_the_viewer_may_pick(): void
    {
        $lead = $this->user(User::ROLE_ADMIN);
        $ana = $this->user(User::ROLE_DEVELOPER, $this->team);
        $ben = $this->user(User::ROLE_DEVELOPER, $this->team);
        $outsider = $this->user(User::ROLE_DEVELOPER, $this->otherTeam);
        $task = $this->task(['title' => 'Fix checkout coupon']);
        $this->task(['title' => 'Coupon rounding', 'assignee_id' => $ana->id, 'status' => 'done']);
        $this->task(['title' => 'Open work', 'assignee_id' => $ben->id, 'due_date' => today()->subDay()]);

        $this->claude->respond(['suggestions' => [
            ['user_id' => $outsider->id, 'reason' => 'Not on the team'],
            ['user_id' => $ana->id, 'reason' => 'Worked on coupons'],
            ['user_id' => 999999, 'reason' => 'Made up'],
            ['user_id' => $ben->id, 'reason' => 'Has context'],
        ]]);

        $out = $this->actingAs($lead)->postJson(route('ai.tasks.suggest-assignee', $task))->assertOk()->json('suggestions');

        $this->assertSame([$ana->id, $ben->id], array_column($out, 'id'));
        $this->assertSame(1, $out[1]['open_tasks']);
        $this->assertSame(1, $out[1]['overdue_tasks']);
        $this->assertStringContainsString('Coupon rounding', $this->claude->prompt());
        $this->assertStringNotContainsString($outsider->name, $this->claude->prompt());
        $this->assertNull($task->fresh()->assignee_id);
    }

    public function test_private_data_stays_out_of_the_prompt(): void
    {
        $ana = $this->user(User::ROLE_DEVELOPER, $this->team);
        $ben = $this->user(User::ROLE_DEVELOPER, $this->team);
        $entry = new TasksheetEntry(['team_id' => $this->team->id, 'user_id' => $ana->id, 'date' => today()->toDateString()]);
        $entry->fill(['leave_type' => 'sick', 'feedback' => '<div>Secret lead note</div>'])->save();

        $this->claude->respond(['suggestions' => [['user_id' => $ben->id, 'reason' => 'Lighter week']]]);

        $this->actingAs($this->user(User::ROLE_ADMIN))
            ->postJson(route('ai.tasks.suggest-assignee', $this->task()))->assertOk();

        $prompt = $this->claude->prompt();
        $this->assertStringContainsString('on leave '.today()->toDateString(), $prompt);
        $this->assertStringNotContainsString('sick', strtolower($prompt));
        $this->assertStringNotContainsString('Secret lead note', $prompt);
    }

    public function test_a_developer_only_sees_teammates_and_visible_work(): void
    {
        $dev = $this->user(User::ROLE_DEVELOPER, $this->team);
        $mate = $this->user(User::ROLE_DEVELOPER, $this->team);
        $other = $this->user(User::ROLE_DEVELOPER, $this->otherTeam);
        $this->otherTeam->members()->attach($mate);
        $this->task(['title' => 'Secret other team job', 'assignee_id' => $mate->id, 'status' => 'done', 'team_id' => $this->otherTeam->id]);

        $task = Task::create(['title' => 'Unlinked job', 'status' => 'todo', 'team_id' => $this->team->id]);
        $this->claude->respond(['suggestions' => [['user_id' => $mate->id, 'reason' => 'Free this week']]]);

        $this->actingAs($dev)->postJson(route('ai.tasks.suggest-assignee', $task))->assertOk();

        $prompt = $this->claude->prompt();
        $this->assertStringContainsString($mate->name, $prompt);
        $this->assertStringNotContainsString($other->name, $prompt);
        $this->assertStringNotContainsString('Secret other team job', $prompt);
    }

    public function test_one_candidate_needs_no_model_call_and_none_is_explained(): void
    {
        $ana = $this->user(User::ROLE_DEVELOPER, $this->team);
        $release = $this->release();
        $task = Task::create(['release_id' => $release->id, 'team_id' => $this->team->id, 'title' => 'Solo', 'status' => 'todo']);
        $admin = $this->user(User::ROLE_ADMIN);

        $out = $this->actingAs($admin)->postJson(route('ai.tasks.suggest-assignee', $task))->assertOk()->json('suggestions');
        $this->assertSame([$ana->id], array_column($out, 'id'));
        $this->assertCount(0, $this->claude->requests);

        $task->update(['assignee_id' => $ana->id]);
        $this->actingAs($admin)->postJson(route('ai.tasks.suggest-assignee', $task))->assertStatus(409);
    }

    public function test_someone_who_cant_edit_the_task_gets_a_403(): void
    {
        $task = $this->task();

        $this->actingAs($this->user(User::ROLE_DEVELOPER, $this->otherTeam))
            ->postJson(route('ai.tasks.suggest-assignee', $task))->assertForbidden();
    }

    // -- Switches --------------------------------------------------------------

    public function test_a_switched_off_feature_answers_409_and_hides_its_button(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $features = app(AiFeatures::class);
        $release = $this->release();
        Task::create(['release_id' => $release->id, 'title' => 'Something', 'status' => 'todo']);

        $this->actingAs($admin)->get(route('releases.show', $release))->assertSee('Draft a retrospective');

        $features->set('release_retro', false, $admin);

        $this->actingAs($admin)->get(route('releases.show', $release))->assertDontSee('Draft a retrospective');
        $this->actingAs($admin)->postJson(route('ai.release-retro', $release))->assertStatus(409);
    }

    public function test_calls_are_logged_under_their_feature(): void
    {
        $ana = $this->user(User::ROLE_DEVELOPER, $this->team);
        $this->task(['assignee_id' => $ana->id]);
        $this->claude->respond(['items' => ['Do the thing']]);

        $this->draft($ana, $ana, 'plan')->assertOk();

        $this->assertSame('tasksheet_draft', AiUsageLog::latest('id')->value('feature'));
    }

    public function test_the_task_page_offers_suggestions_and_the_tasksheet_offers_drafts(): void
    {
        $lead = $this->user(User::ROLE_ADMIN);
        $this->user(User::ROLE_DEVELOPER, $this->team);

        $this->actingAs($lead)->get(route('tasks.show', $this->task()))->assertOk()->assertSee('Suggest someone');
        $this->actingAs($lead)->get(route('tasksheet.index', ['team' => $this->team->id]))->assertOk()->assertSee('Draft from open tasks');
        $this->actingAs($lead)->get(route('board.index'))->assertOk()->assertSee('board-suggest', false)->assertSee('Suggested assignees');
    }
}
