<?php

namespace Tests\Feature\Ai;

use App\Models\Event;
use App\Models\MeetingNote;
use App\Models\Note;
use App\Models\Task;
use App\Models\User;

/** #2 meeting actions, #5 task writer, #6 task catch-up, #9 ask your workspace. */
class WorkAiTest extends AiTestCase
{
    private function meeting(User $author, array $overrides = []): MeetingNote
    {
        return MeetingNote::create($overrides + [
            'created_by' => $author->id, 'title' => 'Sprint sync', 'meeting_date' => '2026-07-06',
            'body' => '<div>Ana will fix the coupon bug by Friday.</div>',
        ]);
    }

    // -- #2 Meeting notes → tasks ----------------------------------------------

    public function test_action_items_are_proposed_with_matched_owners_and_created_on_confirm(): void
    {
        $lead = $this->user(User::ROLE_TEAM_LEAD);
        $ana = User::factory()->create(['name' => 'Ana Lopez', 'role' => User::ROLE_DEVELOPER]);
        $release = $this->release();
        Task::create(['release_id' => $release->id, 'title' => 'Write release checklist', 'status' => 'todo']);
        $note = $this->meeting($lead, ['release_id' => $release->id]);

        $this->claude->respond(['tasks' => [
            ['title' => 'Fix the coupon bug', 'description' => null, 'owner' => 'Ana', 'due_date' => '2026-07-10'],
            ['title' => 'Write release checklist', 'description' => null, 'owner' => null, 'due_date' => null], // already on the board
            ['title' => 'Ask legal', 'description' => null, 'owner' => 'Somebody Unknown', 'due_date' => 'soon'],
        ]]);

        $tasks = $this->actingAs($lead)->postJson(route('ai.meeting-actions', $note))->assertOk()->json('tasks');

        $this->assertCount(2, $tasks);
        $this->assertSame($ana->id, $tasks[0]['assignee_id']);
        $this->assertSame('2026-07-10', $tasks[0]['due_date']);
        $this->assertNull($tasks[1]['assignee_id']);
        $this->assertNull($tasks[1]['due_date']);
        $this->assertStringContainsString('Write release checklist', $this->claude->prompt());

        $this->actingAs($lead)->postJson(route('ai.meeting-actions.store', $note), ['tasks' => [$tasks[0]]])
            ->assertOk()->assertJsonPath('created', 1);
        $task = Task::where('title', 'Fix the coupon bug')->sole();
        $this->assertSame([$release->id, $ana->id, 'todo'], [$task->release_id, $task->assignee_id, $task->status]);
    }

    public function test_a_developer_cannot_assign_outside_their_team_through_suggestions(): void
    {
        $dev = $this->user(User::ROLE_DEVELOPER, $this->team);
        $outsider = $this->user(User::ROLE_DEVELOPER, $this->otherTeam);
        $note = $this->meeting($dev);

        $this->actingAs($dev)->postJson(route('ai.meeting-actions.store', $note), ['tasks' => [
            ['title' => 'Mine', 'assignee_id' => $dev->id],
            ['title' => 'Theirs', 'assignee_id' => $outsider->id],
        ]])->assertOk();

        $this->assertSame($dev->id, Task::where('title', 'Mine')->sole()->assignee_id);
        $theirs = Task::where('title', 'Theirs')->sole();
        $this->assertNull($theirs->assignee_id);
        $this->assertSame($this->team->id, $theirs->team_id); // filed into the developer's own team
    }

    public function test_meeting_notes_the_user_cannot_view_are_off_limits(): void
    {
        $author = $this->user(User::ROLE_DEVELOPER, $this->otherTeam);
        $note = $this->meeting($author, ['visibility' => MeetingNote::VISIBILITY_ATTENDEES]);

        $this->actingAs($this->user(User::ROLE_DEVELOPER, $this->team))->postJson(route('ai.meeting-actions', $note))->assertForbidden();
    }

    // -- #5 Task writer --------------------------------------------------------

    public function test_task_writer_drafts_and_only_flags_visible_duplicates(): void
    {
        $dev = $this->user(User::ROLE_DEVELOPER, $this->team);
        $mine = Task::create(['team_id' => $this->team->id, 'title' => 'Coupon validation on checkout', 'status' => 'todo']);
        $hidden = Task::create(['team_id' => $this->otherTeam->id, 'title' => 'Coupon rounding fix', 'status' => 'todo']);

        $this->claude->respondUsing(fn () => [
            'description' => 'Validate coupons.', 'acceptance_criteria' => ['Invalid coupons are rejected'],
            'subtasks' => ['Add rule', 'Add tests'], 'duplicate_ids' => [$mine->id, $hidden->id],
        ]);

        $out = $this->actingAs($dev)->postJson(route('ai.tasks.draft'), ['title' => 'Coupon validation'])->assertOk()->json();

        $this->assertSame([$mine->id], array_column($out['duplicates'], 'id'));
        $this->assertStringNotContainsString('Coupon rounding fix', $this->claude->prompt());
        $this->assertStringContainsString('Acceptance criteria:', $out['text']);
    }

    public function test_suggested_subtasks_are_created_under_the_task(): void
    {
        $lead = $this->user(User::ROLE_ADMIN);
        $task = Task::create(['team_id' => $this->team->id, 'title' => 'Coupons', 'status' => 'todo']);

        $this->actingAs($lead)->postJson(route('ai.tasks.subtasks', $task), ['tasks' => [['title' => 'Add rule'], ['title' => 'Add tests']]])
            ->assertOk()->assertJsonPath('created', 2);
        $this->assertSame(2, $task->subtasks()->count());

        $sub = $task->subtasks()->first();
        $this->actingAs($lead)->postJson(route('ai.tasks.subtasks', $sub), ['tasks' => [['title' => 'Nested']]])->assertStatus(422);
    }

    public function test_task_page_shows_the_task_writer(): void
    {
        $task = Task::create(['team_id' => $this->team->id, 'title' => 'Coupons', 'status' => 'todo']);

        $this->actingAs($this->user(User::ROLE_ADMIN))->get(route('tasks.show', $task))->assertOk()->assertSee('Task writer');
    }

    // -- #6 Catch me up (task) -------------------------------------------------

    public function test_task_catch_up_respects_task_visibility(): void
    {
        $task = Task::create(['team_id' => $this->otherTeam->id, 'title' => 'Hidden', 'status' => 'todo']);
        $this->actingAs($this->user(User::ROLE_DEVELOPER, $this->team))->postJson(route('ai.catch-up.task', $task))->assertForbidden();
    }

    // -- #9 Ask your workspace -------------------------------------------------

    public function test_ask_only_shows_claude_records_the_asker_may_see_and_links_citations(): void
    {
        $dev = $this->user(User::ROLE_DEVELOPER, $this->team);
        $mine = Task::create(['team_id' => $this->team->id, 'title' => 'Coupon checkout bug', 'status' => 'in_progress']);
        Task::create(['team_id' => $this->otherTeam->id, 'title' => 'Coupon secret project', 'status' => 'todo']);
        Event::create(['title' => 'Coupon launch', 'type' => 'release', 'starts_at' => now()->addDay(), 'created_by' => $dev->id]);

        $this->claude->respondUsing(fn () => [
            'answer' => "The coupon bug is in progress [T{$mine->id}].\n\n- Another fact [T999999]",
            'citations' => ["T{$mine->id}", 'T999999'],
            'follow_ups' => ['Who owns it?'],
        ]);

        $out = $this->actingAs($dev)->postJson(route('ai.ask'), ['question' => 'What is happening with coupons?'])->assertOk()->json();

        $this->assertStringContainsString('Coupon checkout bug', $this->claude->prompt());
        $this->assertStringNotContainsString('secret project', $this->claude->prompt());
        $this->assertStringContainsString(route('tasks.show', $mine), $out['html']);
        $this->assertStringNotContainsString('T999999', $out['html']); // unknown refs are dropped
        $this->assertSame(["T{$mine->id}"], array_column($out['sources'], 'ref'));
        $this->assertStringContainsString('<ul>', $out['html']);
    }

    public function test_asking_about_a_team_by_name_brings_in_its_work_and_notes(): void
    {
        $lead = $this->user(User::ROLE_ADMIN);
        $this->team->update(['name' => 'Stackmart']);
        $release = $this->release();
        Task::create(['release_id' => $release->id, 'title' => 'Payment retries', 'status' => 'in_progress']);
        Task::create(['team_id' => $this->team->id, 'title' => 'Old idea', 'status' => 'archive']); // never counted
        Note::create(['user_id' => $lead->id, 'date' => today(), 'body' => '<div>Stackmart demo went well</div>', 'visibility' => 'shared']);

        $this->claude->respondUsing(fn () => ['answer' => "Stackmart is busy [G{$this->team->id}].", 'citations' => ["G{$this->team->id}"], 'follow_ups' => []]);

        $out = $this->actingAs($lead)->postJson(route('ai.ask'), ['question' => 'what is the update of team stackmart?'])->assertOk()->json();

        $prompt = $this->claude->prompt();
        $this->assertStringContainsString('Team Stackmart', $prompt);
        $this->assertStringContainsString('Payment retries', $prompt);
        $this->assertStringContainsString('Checkout v2.4', $prompt);
        $this->assertStringContainsString('demo went well', $prompt);
        $this->assertStringNotContainsString('Old idea', $prompt);
        $this->assertStringContainsString(route('teams.show', $this->team), $out['html']);
    }

    public function test_developers_cannot_ask_about_another_teams_work(): void
    {
        $this->otherTeam->update(['name' => 'Stackmart']);
        Task::create(['team_id' => $this->otherTeam->id, 'title' => 'Secret roadmap', 'status' => 'todo']);
        $this->claude->respondUsing(fn () => ['answer' => 'Nothing.', 'citations' => [], 'follow_ups' => []]);

        $this->actingAs($this->user(User::ROLE_DEVELOPER, $this->team))
            ->postJson(route('ai.ask'), ['question' => 'update of team stackmart?'])->assertOk();

        $this->assertStringNotContainsString('Stackmart', $this->claude->prompt());
        $this->assertStringNotContainsString('Secret roadmap', $this->claude->prompt());
    }

    public function test_ask_page_renders_for_every_role(): void
    {
        foreach ([User::ROLE_ADMIN, User::ROLE_DEVELOPER, User::ROLE_VIEWER] as $role) {
            $this->actingAs($this->user($role, $this->team))->get(route('ai.ask.page'))->assertOk()->assertSee('Ask your workspace');
        }
    }
}
