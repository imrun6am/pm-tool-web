<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\TasksheetEntry;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamTasksheetTest extends TestCase
{
    use RefreshDatabase;

    private function team(): Team
    {
        return Team::create(['name' => 'Core', 'color' => '#0891b2']);
    }

    private function member(Team $team, string $role = User::ROLE_DEVELOPER): User
    {
        $user = User::factory()->create(['role' => $role]);
        $team->members()->attach($user);

        return $user;
    }

    private function payload(Team $team, User $user, array $overrides = []): array
    {
        return array_merge([
            'team_id' => $team->id, 'user_id' => $user->id, 'date' => today()->toDateString(),
            'plan' => 'Build the widget', 'result' => null, 'comment' => null, 'tickets' => null,
        ], $overrides);
    }

    public function test_member_creates_then_updates_own_row_without_duplicates(): void
    {
        $team = $this->team();
        $dev = $this->member($team);

        $this->actingAs($dev)->put(route('tasksheet.entries.upsert'), $this->payload($team, $dev))
            ->assertRedirect();

        $this->assertSame(1, TasksheetEntry::count());
        $this->assertSame('Build the widget', TasksheetEntry::first()->plan);

        $this->actingAs($dev)->put(route('tasksheet.entries.upsert'), $this->payload($team, $dev, [
            'plan' => 'Build the widget', 'result' => 'Widget built', 'work_points' => 5,
        ]))->assertRedirect();

        $this->assertSame(1, TasksheetEntry::count());
        $entry = TasksheetEntry::first();
        $this->assertSame('Widget built', $entry->result);
        $this->assertSame(5, $entry->work_points);
    }

    public function test_non_lead_cannot_save_another_members_row_but_lead_can(): void
    {
        $team = $this->team();
        $dev = $this->member($team);
        $qa = $this->member($team, User::ROLE_QA);
        $lead = User::factory()->create(['role' => User::ROLE_TEAM_LEAD]);

        $this->actingAs($qa)->put(route('tasksheet.entries.upsert'), $this->payload($team, $dev))
            ->assertForbidden();
        $this->assertSame(0, TasksheetEntry::count());

        $this->actingAs($lead)->put(route('tasksheet.entries.upsert'), $this->payload($team, $dev))
            ->assertRedirect();
        $this->assertSame(1, TasksheetEntry::count());
    }

    public function test_feedback_visible_to_leads_only(): void
    {
        $team = $this->team();
        $dev = $this->member($team);
        $lead = User::factory()->create(['role' => User::ROLE_CTO]);

        $this->actingAs($lead)->put(route('tasksheet.entries.upsert'), $this->payload($team, $dev, [
            'feedback' => 'Great velocity this week',
        ]))->assertRedirect();

        $this->assertSame('Great velocity this week', TasksheetEntry::first()->feedback);

        $sheet = fn () => route('tasksheet.index', ['team' => $team->id, 'date' => today()->toDateString()]);

        $this->actingAs($lead)->get($sheet())
            ->assertOk()->assertSee('Feedback')->assertSee('Great velocity this week');

        // Developers/QA get neither the column nor the content anywhere in the DOM.
        $this->actingAs($dev)->get($sheet())
            ->assertOk()->assertDontSee('Feedback')->assertDontSee('Great velocity this week');
    }

    public function test_non_lead_submitting_feedback_does_not_store_it_and_member_save_preserves_lead_feedback(): void
    {
        $team = $this->team();
        $dev = $this->member($team);
        $lead = User::factory()->create(['role' => User::ROLE_TEAM_LEAD]);

        $this->actingAs($dev)->put(route('tasksheet.entries.upsert'), $this->payload($team, $dev, [
            'feedback' => 'I rate myself excellent',
        ]))->assertRedirect();
        $this->assertNull(TasksheetEntry::first()->feedback);

        $this->actingAs($lead)->put(route('tasksheet.entries.upsert'), $this->payload($team, $dev, [
            'feedback' => 'Needs focus',
        ]))->assertRedirect();
        $this->assertSame('Needs focus', TasksheetEntry::first()->feedback);

        // Member updates their row afterwards — lead feedback must survive.
        $this->actingAs($dev)->put(route('tasksheet.entries.upsert'), $this->payload($team, $dev, [
            'result' => 'Done', 'feedback' => 'wipe attempt',
        ]))->assertRedirect();

        $entry = TasksheetEntry::first();
        $this->assertSame('Done', $entry->result);
        $this->assertSame('Needs focus', $entry->feedback);
    }

    public function test_leave_save_stores_type_clears_task_fields_and_shows_badge(): void
    {
        $team = $this->team();
        $dev = $this->member($team);

        $this->actingAs($dev)->put(route('tasksheet.entries.upsert'), $this->payload($team, $dev, [
            'work_points' => 3,
        ]))->assertRedirect();

        $this->actingAs($dev)->put(route('tasksheet.entries.upsert'), $this->payload($team, $dev, [
            'leave_type' => 'sick',
        ]))->assertRedirect();

        $entry = TasksheetEntry::first();
        $this->assertSame('sick', $entry->leave_type);
        $this->assertNull($entry->plan);
        $this->assertNull($entry->work_points);

        $this->actingAs($dev)->get(route('tasksheet.index', ['team' => $team->id]))
            ->assertOk()->assertSee('Sick leave');
    }

    public function test_half_day_leave_keeps_tasks_and_counts_toward_fill_status(): void
    {
        $team = $this->team();
        $dev = $this->member($team);

        // Half day + a task plan: leave is stored, the plan survives.
        $this->actingAs($dev)->put(route('tasksheet.entries.upsert'), $this->payload($team, $dev, [
            'leave_type' => 'half_day', 'plan' => 'Half day of work',
        ]))->assertRedirect();

        $entry = TasksheetEntry::first();
        $this->assertSame('half_day', $entry->leave_type);
        $this->assertNotNull($entry->plan);
        $this->assertTrue($entry->isHalfDay());
        $this->assertFalse($entry->isFullDayLeave());
        $this->assertTrue($entry->isPartiallyFilled()); // tasks still count

        // The sheet shows the plan alongside a "Half day" marker (not a full leave badge).
        $this->actingAs($dev)->get(route('tasksheet.index', ['team' => $team->id]))
            ->assertOk()->assertSee('Half day of work')->assertSee('Half day');
    }

    public function test_invalid_leave_type_and_negative_points_are_rejected(): void
    {
        $team = $this->team();
        $dev = $this->member($team);

        $this->actingAs($dev)->put(route('tasksheet.entries.upsert'), $this->payload($team, $dev, [
            'leave_type' => 'vacation',
        ]))->assertSessionHasErrors('leave_type');

        $this->actingAs($dev)->put(route('tasksheet.entries.upsert'), $this->payload($team, $dev, [
            'work_points' => -2,
        ]))->assertSessionHasErrors('work_points');

        $this->assertSame(0, TasksheetEntry::count());
    }

    public function test_save_for_non_member_is_rejected(): void
    {
        $team = $this->team();
        $outsider = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $lead = User::factory()->create(['role' => User::ROLE_TEAM_LEAD]);

        $this->actingAs($lead)->put(route('tasksheet.entries.upsert'), $this->payload($team, $outsider))
            ->assertSessionHasErrors('user_id');
        $this->assertSame(0, TasksheetEntry::count());
    }

    public function test_unfilled_past_day_shows_auto_absent(): void
    {
        $team = $this->team();
        $dev = $this->member($team);

        $this->actingAs($dev)
            ->get(route('tasksheet.index', ['team' => $team->id, 'date' => today()->subDay()->toDateString()]))
            ->assertOk()
            ->assertSee('Absent — not filled');

        // Today's empty row is not "absent".
        $this->actingAs($dev)->get(route('tasksheet.index', ['team' => $team->id]))
            ->assertOk()->assertDontSee('Absent — not filled');
    }

    public function test_backfilled_row_replaces_auto_absent_and_shows_late_hint(): void
    {
        $team = $this->team();
        $dev = $this->member($team);
        $yesterday = today()->subDay()->toDateString();

        $this->actingAs($dev)->put(route('tasksheet.entries.upsert'), $this->payload($team, $dev, [
            'date' => $yesterday, 'plan' => 'Backfilled plan',
        ]))->assertRedirect();

        $this->actingAs($dev)
            ->get(route('tasksheet.index', ['team' => $team->id, 'date' => $yesterday]))
            ->assertOk()
            ->assertSee('Backfilled plan')
            ->assertDontSee('Absent — not filled')
            ->assertSee('Not added on the operating day');
    }

    public function test_on_time_entry_edited_later_has_no_late_hint(): void
    {
        $team = $this->team();
        $dev = $this->member($team);
        $yesterday = today()->subDay();

        // Created on its sheet day (simulate by forcing created_at), edited today.
        $entry = TasksheetEntry::create([
            'team_id' => $team->id, 'user_id' => $dev->id, 'date' => $yesterday->toDateString(), 'plan' => 'On time',
        ]);
        $entry->created_at = $yesterday->copy()->setTime(9, 30);
        $entry->save();

        $this->actingAs($dev)->put(route('tasksheet.entries.upsert'), $this->payload($team, $dev, [
            'date' => $yesterday->toDateString(), 'plan' => 'On time', 'result' => 'Edited later',
        ]))->assertRedirect();

        $this->actingAs($dev)
            ->get(route('tasksheet.index', ['team' => $team->id, 'date' => $yesterday->toDateString()]))
            ->assertOk()
            ->assertSee('Edited later')
            ->assertDontSee('Not added on the operating day');
    }

    public function test_former_members_saved_row_still_visible_and_any_past_day_browsable(): void
    {
        $team = $this->team();
        $dev = $this->member($team);
        $pastDate = today()->subDays(10)->toDateString();

        TasksheetEntry::create([
            'team_id' => $team->id, 'user_id' => $dev->id, 'date' => $pastDate, 'plan' => 'Historic work',
        ]);

        $team->members()->detach($dev);

        $viewer = User::factory()->create(['role' => User::ROLE_VIEWER]);
        $this->actingAs($viewer)
            ->get(route('tasksheet.index', ['team' => $team->id, 'date' => $pastDate]))
            ->assertOk()
            ->assertSee($dev->name)
            ->assertSee('Historic work');
    }

    public function test_grid_lists_dev_and_qa_members_only(): void
    {
        $team = $this->team();
        $dev = $this->member($team);
        $qa = $this->member($team, User::ROLE_QA);
        $leadMember = $this->member($team, User::ROLE_TEAM_LEAD);

        $this->actingAs($dev)->get(route('tasksheet.index', ['team' => $team->id]))
            ->assertOk()
            ->assertSee($dev->name)
            ->assertSee($qa->name)
            ->assertDontSee($leadMember->name);
    }

    public function test_member_can_save_own_row_with_string_form_input(): void
    {
        // Real browsers submit every field as a string — the policy must not
        // 403 on '5' vs 5 when authorizing a not-yet-saved row.
        $team = $this->team();
        $dev = $this->member($team);

        $this->actingAs($dev)->put(route('tasksheet.entries.upsert'), [
            'team_id' => (string) $team->id,
            'user_id' => (string) $dev->id,
            'date' => today()->toDateString(),
            'plan' => 'From a real browser',
            'work_points' => '4',
        ])->assertRedirect();

        $entry = TasksheetEntry::first();
        $this->assertSame('From a real browser', $entry->plan);
        $this->assertSame(4, $entry->work_points);
    }

    public function test_row_save_records_activity_without_feedback(): void
    {
        $team = $this->team();
        $dev = $this->member($team);
        $lead = User::factory()->create(['role' => User::ROLE_TEAM_LEAD]);

        $this->actingAs($lead)->put(route('tasksheet.entries.upsert'), $this->payload($team, $dev, [
            'plan' => 'Ship the API', 'feedback' => 'Confidential note',
        ]))->assertRedirect();

        $activity = Activity::where('subject_type', TasksheetEntry::class)->first();
        $this->assertNotNull($activity);
        $this->assertSame($lead->id, $activity->causer_id);
        $this->assertSame('created', $activity->event);
        $this->assertStringContainsString($dev->name, $activity->description);

        // Feedback must never reach the log — in this or any entry.
        $this->assertStringNotContainsString('Confidential note', json_encode($activity->properties));
    }

    public function test_feedback_only_change_records_no_activity(): void
    {
        $team = $this->team();
        $dev = $this->member($team);
        $lead = User::factory()->create(['role' => User::ROLE_TEAM_LEAD]);

        $this->actingAs($dev)->put(route('tasksheet.entries.upsert'), $this->payload($team, $dev))
            ->assertRedirect();
        $countAfterCreate = Activity::where('subject_type', TasksheetEntry::class)->count();

        // Lead changes only the feedback — nothing loggable remains after ignores.
        $this->actingAs($lead)->put(route('tasksheet.entries.upsert'), $this->payload($team, $dev, [
            'feedback' => 'Quiet note',
        ]))->assertRedirect();

        $this->assertSame('Quiet note', TasksheetEntry::first()->feedback);
        $this->assertSame($countAfterCreate, Activity::where('subject_type', TasksheetEntry::class)->count());
    }

    public function test_rich_text_is_sanitized_and_empty_markup_stays_unfilled(): void
    {
        $team = $this->team();
        $dev = $this->member($team);

        $this->actingAs($dev)->put(route('tasksheet.entries.upsert'), $this->payload($team, $dev, [
            'plan' => '<strong>Ship it</strong><script>alert(1)</script>',
            'result' => '<div><br></div>', // visually empty → must not count as filled
        ]))->assertRedirect();

        $entry = TasksheetEntry::first();
        $this->assertStringContainsString('<strong>Ship it</strong>', $entry->plan);
        $this->assertStringNotContainsString('<script>', $entry->plan);
        $this->assertNull($entry->result);
        $this->assertTrue($entry->isPartiallyFilled());

        // Grid renders the sanitized markup (layout has its own <script> tags,
        // so assert on the payload, not the tag).
        $this->actingAs($dev)->get(route('tasksheet.index', ['team' => $team->id]))
            ->assertOk()
            ->assertSee('<strong>Ship it</strong>', false)
            ->assertDontSee('alert(1)', false);
    }

    public function test_removed_member_history_stays_visible_and_filling_is_locked(): void
    {
        $team = $this->team();
        $dev = $this->member($team);
        $lead = User::factory()->create(['role' => User::ROLE_TEAM_LEAD]);

        // Filled yesterday, missed the day before.
        TasksheetEntry::create([
            'team_id' => $team->id, 'user_id' => $dev->id,
            'date' => today()->subDay()->toDateString(), 'plan' => 'Old work',
        ]);

        // Removed today (via the real route → left_at is stamped, not deleted).
        $this->actingAs($lead)->delete(route('teams.members.destroy', [$team, $dev]))->assertRedirect();

        // Yesterday: filled row still there. Two days ago: auto-absent mark still there.
        $this->actingAs($lead)->get(route('tasksheet.index', ['team' => $team->id, 'date' => today()->subDay()->toDateString()]))
            ->assertOk()->assertSee($dev->name)->assertSee('Old work');
        $this->actingAs($lead)->get(route('tasksheet.index', ['team' => $team->id, 'date' => today()->subDays(2)->toDateString()]))
            ->assertOk()->assertSee($dev->name)->assertSee('Absent — not filled');

        // After the removal date they are no longer listed.
        $this->actingAs($lead)->get(route('tasksheet.index', ['team' => $team->id, 'date' => today()->addDay()->toDateString()]))
            ->assertOk()->assertDontSee($dev->name);

        // The leaver cannot fill a new day (fails team-membership validation)…
        $this->actingAs($dev)->put(route('tasksheet.entries.upsert'), $this->payload($team, $dev))
            ->assertSessionHasErrors('user_id');
        // …nor edit their previously saved row (policy forbids)…
        $this->actingAs($dev)->put(route('tasksheet.entries.upsert'), $this->payload($team, $dev, [
            'date' => today()->subDay()->toDateString(), 'plan' => 'rewrite history',
        ]))->assertForbidden();
        // …but a lead still can.
        $this->actingAs($lead)->put(route('tasksheet.entries.upsert'), $this->payload($team, $dev, [
            'date' => today()->subDay()->toDateString(), 'plan' => 'Lead correction',
        ]))->assertRedirect();
        $this->assertStringContainsString('Lead correction', TasksheetEntry::first()->plan);
    }

    public function test_removal_from_one_team_does_not_affect_the_other(): void
    {
        $teamA = $this->team();
        $teamB = Team::create(['name' => 'Beta', 'color' => '#123456']);
        $dev = $this->member($teamA);
        $teamB->members()->attach($dev);
        $lead = User::factory()->create(['role' => User::ROLE_TEAM_LEAD]);

        $this->actingAs($lead)->delete(route('teams.members.destroy', [$teamA, $dev]))->assertRedirect();

        // Team B still fillable; Team A locked.
        $this->actingAs($dev)->put(route('tasksheet.entries.upsert'), $this->payload($teamB, $dev))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($dev)->put(route('tasksheet.entries.upsert'), $this->payload($teamA, $dev))
            ->assertSessionHasErrors('user_id');
    }

    public function test_readded_member_can_fill_again(): void
    {
        $team = $this->team();
        $dev = $this->member($team);
        $lead = User::factory()->create(['role' => User::ROLE_TEAM_LEAD]);

        $this->actingAs($lead)->delete(route('teams.members.destroy', [$team, $dev]))->assertRedirect();
        $this->actingAs($lead)->post(route('teams.members.store', $team), ['user_id' => $dev->id])->assertRedirect();

        $this->actingAs($dev)->put(route('tasksheet.entries.upsert'), $this->payload($team, $dev))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, TasksheetEntry::count());
    }

    public function test_lead_views_user_history_with_team_and_date_filters(): void
    {
        $teamA = $this->team();
        $teamB = Team::create(['name' => 'Beta', 'color' => '#123456']);
        $dev = $this->member($teamA);
        $teamB->members()->attach($dev);
        $lead = User::factory()->create(['role' => User::ROLE_TEAM_LEAD]);

        TasksheetEntry::create(['team_id' => $teamA->id, 'user_id' => $dev->id, 'date' => '2026-07-20', 'plan' => 'Alpha work', 'feedback' => 'Lead note']);
        TasksheetEntry::create(['team_id' => $teamB->id, 'user_id' => $dev->id, 'date' => '2026-07-21', 'plan' => 'Beta work']);
        TasksheetEntry::create(['team_id' => $teamA->id, 'user_id' => $dev->id, 'date' => '2026-07-01', 'plan' => 'Early work']);

        // Unfiltered: everything, with feedback for the lead.
        $this->actingAs($lead)->get(route('tasksheet.user', $dev))
            ->assertOk()->assertSee('Alpha work')->assertSee('Beta work')->assertSee('Early work')->assertSee('Lead note');

        // Team filter.
        $this->actingAs($lead)->get(route('tasksheet.user', [$dev, 'team' => $teamB->id]))
            ->assertOk()->assertSee('Beta work')->assertDontSee('Alpha work');

        // Date range filter.
        $this->actingAs($lead)->get(route('tasksheet.user', [$dev, 'from' => '2026-07-19', 'to' => '2026-07-22']))
            ->assertOk()->assertSee('Alpha work')->assertSee('Beta work')->assertDontSee('Early work');
    }

    public function test_member_views_own_history_without_feedback_and_others_are_forbidden(): void
    {
        $team = $this->team();
        $dev = $this->member($team);
        $other = $this->member($team, User::ROLE_QA);

        TasksheetEntry::create(['team_id' => $team->id, 'user_id' => $dev->id, 'date' => '2026-07-20', 'plan' => 'My work', 'feedback' => 'Private lead note']);

        $this->actingAs($dev)->get(route('tasksheet.user', $dev))
            ->assertOk()->assertSee('My work')->assertDontSee('Feedback')->assertDontSee('Private lead note');

        $this->actingAs($other)->get(route('tasksheet.user', $dev))->assertForbidden();
    }

    public function test_deleted_members_history_is_still_reachable(): void
    {
        $team = $this->team();
        $dev = $this->member($team);
        $lead = User::factory()->create(['role' => User::ROLE_TEAM_LEAD]);

        TasksheetEntry::create(['team_id' => $team->id, 'user_id' => $dev->id, 'date' => '2026-07-20', 'plan' => 'Before deletion']);

        $dev->delete(); // soft delete

        $this->actingAs($lead)->get(route('tasksheet.user', $dev))
            ->assertOk()->assertSee('Before deletion')->assertSee('Deleted user');
    }

    public function test_tasksheet_requires_auth(): void
    {
        $this->get(route('tasksheet.index'))->assertRedirect(route('login'));
    }
}
