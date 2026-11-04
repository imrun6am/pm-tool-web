<?php

namespace Tests\Feature\Ai;

use App\Models\Event;
use App\Models\Label;
use App\Models\MeetingNote;
use App\Models\Note;
use App\Models\ReleaseStatusUpdate;
use App\Models\Task;
use App\Models\TasksheetEntry;
use App\Models\TaskTimeLog;
use App\Models\Team;
use App\Models\TriageItem;
use App\Models\User;
use App\Notifications\InboxNotification;
use App\Services\Ai\AskQuestion;
use App\Services\Ai\WorkspaceAsk;

/** What "Ask your workspace" can answer from, and who gets which of it. */
class WorkspaceAskDataTest extends AiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->claude->respondUsing(fn () => ['answer' => 'Fine.', 'citations' => [], 'follow_ups' => []]);
    }

    private function prompt(User $user, string $question): string
    {
        $this->actingAs($user)->postJson(route('ai.ask'), ['question' => $question])->assertOk();

        return $this->claude->prompt(count($this->claude->requests) - 1);
    }

    private function task(Team $team, string $title, array $overrides = []): Task
    {
        return Task::create($overrides + ['team_id' => $team->id, 'title' => $title, 'status' => 'todo']);
    }

    /** A task finished some weeks ago, for the team's pace. */
    private function finishedWeeksAgo(Team $team, int $weeks): void
    {
        $task = $this->task($team, "Finished {$weeks} weeks ago", ['status' => 'in_progress']);
        $task->update(['status' => 'done']);
        $task->statusChanges()->update(['changed_at' => today()->startOfWeek()->subWeeks($weeks)->addDay()]);
    }

    // -- Release health --------------------------------------------------------

    public function test_a_release_carries_its_forecast(): void
    {
        foreach ([1, 2, 3, 4] as $weeks) {
            $this->finishedWeeksAgo($this->team, $weeks);
        }
        $release = $this->release();
        $this->task($this->team, 'Still open', ['release_id' => $release->id]);

        $prompt = $this->prompt($this->user(User::ROLE_ADMIN), 'When will it land?');

        $this->assertStringContainsString('1 top-level tasks open. Forecast to finish: 50% by', $prompt);
        $this->assertStringContainsString('85% by', $prompt);
        $this->assertMatchesRegularExpression('/\d+% chance of finishing by the end date/', $prompt);
    }

    public function test_too_little_history_says_so_and_gives_no_dates(): void
    {
        $this->task($this->team, 'Still open', ['release_id' => $this->release()->id]);

        $prompt = $this->prompt($this->user(User::ROLE_ADMIN), 'When will it land?');

        $this->assertStringContainsString('too little finished work on this team to forecast', $prompt);
        $this->assertStringNotContainsString('Forecast to finish', $prompt);
    }

    public function test_a_release_carries_the_leads_latest_status_and_scope_when_asked_about_risk(): void
    {
        $lead = $this->user(User::ROLE_ADMIN);
        $release = $this->release();
        ReleaseStatusUpdate::create(['release_id' => $release->id, 'author_id' => $lead->id, 'status' => 'at_risk', 'note' => 'Payments vendor is late']);

        $prompt = $this->prompt($lead, 'Which releases are at risk?');

        $this->assertStringContainsString('lead status: At risk', $prompt);
        $this->assertStringContainsString('Payments vendor is late', $prompt);
        $this->assertStringContainsString('Scope went from', $prompt);
    }

    public function test_working_days_follow_the_working_week_and_the_releases_off_days(): void
    {
        config(['planning.weekend_days' => ['friday'], 'planning.alternate_off_day' => '2026-08-01']);
        $this->travelTo('2026-11-04 10:00:00'); // a Wednesday

        $release = $this->release(overrides: ['start_date' => '2026-11-01', 'end_date' => '2026-11-14']);
        $release->offDays()->create(['date' => '2026-11-10', 'reason' => 'Public holiday']);

        $prompt = $this->prompt($this->user(User::ROLE_ADMIN), 'How long is left?');

        // Nov 4, 5, 8, 9, 11, 12 and 14: not the Fridays, the alternate Saturday or the off-day.
        $this->assertStringContainsString('7 working days left', $prompt);
        $this->assertStringContainsString('2026-11-10 (Public holiday)', $prompt);
        $this->assertStringContainsString('Today: 2026-11-04 (Wednesday)', $prompt);
        $this->assertStringContainsString('Friday and every second Saturday', $prompt);
        $this->assertStringContainsString('Next alternate days off: 2026-11-07', $prompt);
        $this->assertStringContainsString('Next working day: 2026-11-05', $prompt);
    }

    public function test_developers_get_no_figures_from_another_teams_release(): void
    {
        $lead = $this->user(User::ROLE_ADMIN);
        $theirs = $this->release($this->otherTeam, ['name' => 'Hidden launch']);
        ReleaseStatusUpdate::create(['release_id' => $theirs->id, 'author_id' => $lead->id, 'status' => 'off_track', 'note' => 'Secret slip']);

        $prompt = $this->prompt($this->user(User::ROLE_DEVELOPER, $this->team), 'Which releases are at risk?');

        $this->assertStringNotContainsString('Hidden launch', $prompt);
        $this->assertStringNotContainsString('Secret slip', $prompt);
    }

    public function test_the_askers_live_releases_come_ahead_of_forgotten_ones(): void
    {
        foreach (range(1, 12) as $i) {
            $this->release($this->otherTeam, ['name' => "Forgotten {$i}", 'start_date' => today()->subYear()->toDateString(), 'end_date' => today()->subMonths(6)->toDateString()]);
        }
        $this->release($this->team, ['name' => 'Current one']);

        $prompt = $this->prompt($this->user(User::ROLE_TEAM_LEAD, $this->team), 'How are we doing?');

        $this->assertStringContainsString('Current one', $prompt);
    }

    // -- Blocked, overdue and task detail ---------------------------------------

    public function test_blocked_and_overdue_work_is_there_without_any_keyword_matching(): void
    {
        $this->task($this->team, 'Vendor sync', ['blocked_at' => now(), 'blocked_reason' => 'Waiting on the vendor']);
        $this->task($this->team, 'Late thing', ['due_date' => today()->subDays(3)]);
        $this->task($this->team, 'Critical thing', ['priority' => 'urgent']);
        $this->task($this->team, 'Quiet thing');

        $prompt = $this->prompt($this->user(User::ROLE_ADMIN), 'What needs attention?');

        $this->assertStringContainsString('BLOCKED: Waiting on the vendor', $prompt);
        $this->assertStringContainsString('(overdue)', $prompt);
        $this->assertStringContainsString('priority Urgent', $prompt);
        $this->assertStringNotContainsString('Quiet thing', $prompt);
        $this->assertStringContainsString('4 open, 1 blocked, 1 overdue', $prompt);
    }

    public function test_totals_and_lists_narrow_to_a_named_team(): void
    {
        $this->team->update(['name' => 'Stackmart']);
        $this->task($this->team, 'Ours is stuck', ['blocked_at' => now(), 'blocked_reason' => 'x']);
        $this->task($this->otherTeam, 'Theirs is stuck', ['blocked_at' => now(), 'blocked_reason' => 'y']);

        $prompt = $this->prompt($this->user(User::ROLE_ADMIN), 'What is blocked in stackmart?');

        $this->assertStringContainsString('Ours is stuck', $prompt);
        $this->assertStringNotContainsString('Theirs is stuck', $prompt);
        $this->assertStringContainsString('in team Stackmart: 1 open, 1 blocked', $prompt);
    }

    public function test_a_blocker_on_another_team_is_not_named_to_a_developer(): void
    {
        $dev = $this->user(User::ROLE_DEVELOPER, $this->team);
        $hidden = $this->task($this->otherTeam, 'Secret upstream work');
        $waiting = $this->task($this->team, 'Waiting downstream');
        $waiting->blockers()->attach($hidden);

        $devPrompt = $this->prompt($dev, 'What is blocked?');
        $this->assertStringContainsString('BLOCKED', $devPrompt);
        $this->assertStringContainsString("waiting on 1 task(s) the asker can't see", $devPrompt);
        $this->assertStringNotContainsString('Secret upstream work', $devPrompt);

        $this->assertStringContainsString('Secret upstream work', $this->prompt($this->user(User::ROLE_ADMIN), 'What is blocked?'));
    }

    public function test_a_task_shows_its_estimate_logged_time_labels_and_priority(): void
    {
        $dev = $this->user(User::ROLE_DEVELOPER, $this->team);
        $task = $this->task($this->team, 'Coupon rewrite', ['priority' => 'high', 'estimate_minutes' => 480]);
        $task->labels()->attach(Label::create(['name' => 'backend', 'color' => '#000000']));
        TaskTimeLog::create(['task_id' => $task->id, 'user_id' => $dev->id, 'logged_on' => today(), 'minutes' => 90]);

        $prompt = $this->prompt($dev, 'Tell me about the coupon rewrite');

        $this->assertStringContainsString("PM-{$task->id} Coupon rewrite", $prompt);
        $this->assertStringContainsString('priority High', $prompt);
        $this->assertStringContainsString('estimate 8h, logged 1h 30m', $prompt);
        $this->assertStringContainsString('labels backend', $prompt);
    }

    public function test_a_quoted_task_is_found_only_when_the_asker_may_see_it(): void
    {
        $mine = $this->task($this->team, 'Zebra crossing');
        $theirs = $this->task($this->otherTeam, 'Hidden giraffe');
        $dev = $this->user(User::ROLE_DEVELOPER, $this->team);

        $this->assertStringContainsString('Zebra crossing', $this->prompt($dev, "What's going on with PM-{$mine->id}?"));
        $this->assertStringNotContainsString('Hidden giraffe', $this->prompt($dev, "What's going on with PM-{$theirs->id}?"));
    }

    public function test_a_named_person_or_label_finds_their_work_within_what_the_asker_may_see(): void
    {
        $zoltan = User::factory()->create(['name' => 'Zoltan Kovacs', 'role' => User::ROLE_DEVELOPER]);
        $this->team->members()->attach($zoltan);
        $this->task($this->team, 'Migrate the widget', ['assignee_id' => $zoltan->id]);
        $outsider = User::factory()->create(['name' => 'Yusuf Demir', 'role' => User::ROLE_DEVELOPER]);
        $this->otherTeam->members()->attach($outsider);
        $this->task($this->otherTeam, 'Another teams job', ['assignee_id' => $outsider->id]);
        $tagged = $this->task($this->team, 'Tagged item');
        $tagged->labels()->attach(Label::create(['name' => 'infrastructure', 'color' => '#000000']));

        $dev = $this->user(User::ROLE_DEVELOPER, $this->team);

        $this->assertStringContainsString('Migrate the widget', $this->prompt($dev, 'What is Zoltan doing?'));
        $this->assertStringNotContainsString('Another teams job', $this->prompt($dev, 'What is Yusuf doing?'));
        $this->assertStringContainsString('Tagged item', $this->prompt($dev, 'Anything labelled infrastructure?'));
    }

    // -- Capacity ---------------------------------------------------------------

    public function test_capacity_reaches_org_leads_for_every_team(): void
    {
        $person = User::factory()->create(['name' => 'Ana Lopez', 'role' => User::ROLE_DEVELOPER]);
        $this->team->members()->attach($person);
        $this->task($this->team, 'Estimated work', ['assignee_id' => $person->id, 'estimate_minutes' => 240, 'due_date' => today()->addDay()]);

        $prompt = $this->prompt($this->user(User::ROLE_ADMIN), 'Who has capacity next week?');

        $this->assertStringContainsString('Capacity of team Alpha', $prompt);
        $this->assertStringContainsString('Capacity of team Beta', $prompt);
        $this->assertStringContainsString('Ana Lopez: week of', $prompt);
    }

    public function test_a_team_lead_gets_capacity_only_for_teams_they_lead(): void
    {
        $lead = $this->user(User::ROLE_TEAM_LEAD);
        $this->team->update(['team_lead_id' => $lead->id]);

        $prompt = $this->prompt($lead, 'Who has capacity next week?');
        $this->assertStringContainsString('Capacity of team Alpha', $prompt);
        $this->assertStringNotContainsString('Capacity of team Beta', $prompt);

        $this->assertStringNotContainsString('Capacity of team', $this->prompt($lead, 'Who has capacity in Beta?'));
    }

    public function test_developers_and_viewers_get_no_capacity_and_are_told_so(): void
    {
        foreach ([User::ROLE_DEVELOPER, User::ROLE_QA, User::ROLE_VIEWER] as $role) {
            $prompt = $this->prompt($this->user($role, $this->team), 'Who has capacity next week?');

            $this->assertStringNotContainsString('Capacity of team', $prompt, $role);
            $this->assertMatchesRegularExpression('/Not available to this asker: .*capacity/', $prompt, $role);
        }
    }

    // -- Flow and hours ---------------------------------------------------------

    public function test_flow_reaches_leads_and_viewers_but_not_developers(): void
    {
        $this->finishedWeeksAgo($this->team, 1);

        $this->assertStringContainsString('Flow of all teams', $this->prompt($this->user(User::ROLE_VIEWER), 'What is our cycle time?'));
        $this->assertStringContainsString('Flow of team Alpha', $this->prompt($this->user(User::ROLE_TECH_LEAD, $this->team), 'What is our cycle time?'));
        $this->assertStringNotContainsString('Flow of', $this->prompt($this->user(User::ROLE_DEVELOPER, $this->team), 'What is our cycle time?'));
    }

    public function test_the_hours_report_follows_the_reports_own_reach(): void
    {
        $person = User::factory()->create(['name' => 'Ana Lopez', 'role' => User::ROLE_DEVELOPER]);
        TaskTimeLog::create(['task_id' => $this->task($this->team, 'Ours')->id, 'user_id' => $person->id, 'logged_on' => today()->subDay(), 'minutes' => 120]);
        TaskTimeLog::create(['task_id' => $this->task($this->otherTeam, 'Theirs')->id, 'user_id' => $person->id, 'logged_on' => today()->subDay(), 'minutes' => 300]);

        $this->assertStringContainsString('7h in total', $this->prompt($this->user(User::ROLE_ADMIN), 'How many hours were logged?'));

        $lead = $this->user(User::ROLE_TEAM_LEAD);
        $this->team->update(['team_lead_id' => $lead->id]);
        $prompt = $this->prompt($lead, 'How many hours were logged?');
        $this->assertStringContainsString('2h in total', $prompt);
        $this->assertStringContainsString('Ana Lopez 2h', $prompt);

        // A team they don't lead isn't an empty report: it isn't there at all.
        $this->assertStringNotContainsString('in total', $this->prompt($lead, 'How many hours were logged in Beta?'));
    }

    public function test_a_developer_gets_only_their_own_hours(): void
    {
        $dev = $this->user(User::ROLE_DEVELOPER, $this->team);
        $teammate = $this->user(User::ROLE_DEVELOPER, $this->team);
        $task = $this->task($this->team, 'Shared work');
        TaskTimeLog::create(['task_id' => $task->id, 'user_id' => $dev->id, 'logged_on' => today(), 'minutes' => 60]);
        TaskTimeLog::create(['task_id' => $task->id, 'user_id' => $teammate->id, 'logged_on' => today(), 'minutes' => 300]);

        $prompt = $this->prompt($dev, 'How many hours did I log?');

        $this->assertStringContainsString('The asker logged 1h', $prompt);
        $this->assertStringNotContainsString('in total', $prompt);
        $this->assertStringNotContainsString($teammate->name, $prompt);
    }

    public function test_a_viewer_gets_no_hours_report_or_named_hours(): void
    {
        $person = User::factory()->create(['name' => 'Ana Lopez', 'role' => User::ROLE_DEVELOPER]);
        TaskTimeLog::create(['task_id' => $this->task($this->team, 'Ours')->id, 'user_id' => $person->id, 'logged_on' => today(), 'minutes' => 120]);

        $prompt = $this->prompt($this->user(User::ROLE_VIEWER), 'How many hours were logged?');

        $this->assertStringNotContainsString('Logged hours', $prompt);
        $this->assertStringNotContainsString('Ana Lopez', $prompt);
    }

    // -- Requests and notifications --------------------------------------------

    public function test_requests_are_the_askers_own_and_what_they_may_decide(): void
    {
        $dev = $this->user(User::ROLE_DEVELOPER, $this->team);
        $reporter = $this->user(User::ROLE_DEVELOPER, $this->team);
        $mine = TriageItem::create(['title' => 'My own ask', 'reporter_id' => $dev->id, 'team_id' => $this->team->id]);
        $mine->forceFill(['status' => TriageItem::DECLINED, 'decline_reason' => 'Out of scope for now'])->save();
        TriageItem::create(['title' => 'Alpha team ask', 'reporter_id' => $reporter->id, 'team_id' => $this->team->id]);
        TriageItem::create(['title' => 'Beta team ask', 'reporter_id' => $reporter->id, 'team_id' => $this->otherTeam->id]);
        TriageItem::create(['title' => 'Unassigned ask', 'reporter_id' => $reporter->id]);

        $devPrompt = $this->prompt($dev, 'What requests are there?');
        $this->assertStringContainsString('My own ask', $devPrompt);
        $this->assertStringContainsString('Out of scope for now', $devPrompt);
        $this->assertStringNotContainsString('Alpha team ask', $devPrompt);
        $this->assertStringNotContainsString('Unassigned ask', $devPrompt);

        $lead = $this->user(User::ROLE_TEAM_LEAD);
        $this->team->update(['team_lead_id' => $lead->id]);
        $leadPrompt = $this->prompt($lead, 'What requests are waiting?');
        $this->assertStringContainsString('Alpha team ask', $leadPrompt);
        $this->assertStringContainsString('Unassigned ask', $leadPrompt);
        $this->assertStringNotContainsString('Beta team ask', $leadPrompt);
        $this->assertStringContainsString("Requests waiting for the asker's decision: 2", $leadPrompt);

        $viewerPrompt = $this->prompt($this->user(User::ROLE_VIEWER), 'What requests are there?');
        $this->assertStringNotContainsString('team ask', $viewerPrompt);
    }

    public function test_a_request_is_found_by_keyword_without_asking_about_requests(): void
    {
        $dev = $this->user(User::ROLE_DEVELOPER, $this->team);
        TriageItem::create(['title' => 'Coupon export button', 'reporter_id' => $dev->id, 'team_id' => $this->team->id]);
        TriageItem::create(['title' => 'Something else entirely', 'reporter_id' => $dev->id, 'team_id' => $this->team->id]);

        $prompt = $this->prompt($dev, 'What about the coupon export?');

        $this->assertStringContainsString('Coupon export button', $prompt);
        $this->assertStringNotContainsString('Something else entirely', $prompt);
    }

    public function test_notifications_are_only_the_askers_own(): void
    {
        $dev = $this->user(User::ROLE_DEVELOPER, $this->team);
        $other = $this->user(User::ROLE_DEVELOPER, $this->team);
        $dev->notify(new InboxNotification('urgent', 'Sam marked your task urgent'));
        $other->notify(new InboxNotification('urgent', 'A message for someone else'));

        $prompt = $this->prompt($dev, 'Any unread notifications?');

        $this->assertStringContainsString('Sam marked your task urgent', $prompt);
        $this->assertStringContainsString('unread', $prompt);
        $this->assertStringNotContainsString('A message for someone else', $prompt);
    }

    // -- Tasksheets --------------------------------------------------------------

    private function entry(Team $team, User $person, array $overrides = []): TasksheetEntry
    {
        return TasksheetEntry::create($overrides + [
            'team_id' => $team->id, 'user_id' => $person->id, 'date' => today()->toDateString(),
            'plan' => '<div>Plan the coupon work</div>', 'result' => '<div>Shipped the coupon work</div>',
            'feedback' => '<div>Private lead feedback</div>', 'work_points' => 7771, 'ticket_points' => 7772,
        ]);
    }

    public function test_tasksheet_rows_never_carry_feedback_or_points_even_for_leads(): void
    {
        $this->entry($this->team, $this->user(User::ROLE_DEVELOPER, $this->team));

        foreach ([User::ROLE_ADMIN, User::ROLE_VIEWER] as $role) {
            $prompt = $this->prompt($this->user($role, $this->team), 'What is on the tasksheet today?');

            $this->assertStringContainsString('Plan the coupon work', $prompt, $role);
            $this->assertStringContainsString('Shipped the coupon work', $prompt, $role);
            $this->assertStringNotContainsString('Private lead feedback', $prompt, $role);
            $this->assertStringNotContainsString('777', $prompt, $role);
        }
    }

    public function test_developers_see_only_their_own_teams_tasksheet_and_leave(): void
    {
        $outsider = $this->user(User::ROLE_DEVELOPER, $this->otherTeam);
        $this->entry($this->otherTeam, $outsider, ['plan' => '<div>Beta secret plan</div>']);
        $this->entry($this->otherTeam, $outsider, ['date' => today()->addDay()->toDateString(), 'leave_type' => 'sick', 'plan' => null, 'result' => null]);
        $teammate = $this->user(User::ROLE_DEVELOPER, $this->team);
        $this->entry($this->team, $teammate, ['date' => today()->addDays(2)->toDateString(), 'leave_type' => 'casual', 'plan' => null, 'result' => null]);

        $prompt = $this->prompt($this->user(User::ROLE_DEVELOPER, $this->team), 'Who is on leave soon?');

        $this->assertStringContainsString($teammate->name, $prompt);
        $this->assertStringContainsString('Casual leave', $prompt);
        $this->assertStringNotContainsString('Beta secret plan', $prompt);
        $this->assertStringNotContainsString('Sick leave', $prompt);
    }

    public function test_a_capacity_question_brings_leave_but_not_everyones_plans(): void
    {
        $person = $this->user(User::ROLE_DEVELOPER, $this->team);
        $this->entry($this->team, $person);
        $this->entry($this->team, $person, ['date' => today()->addDay()->toDateString(), 'leave_type' => 'casual', 'plan' => null, 'result' => null]);

        $prompt = $this->prompt($this->user(User::ROLE_ADMIN, $this->team), 'Who has capacity next week?');

        $this->assertStringContainsString('Casual leave', $prompt);
        $this->assertStringNotContainsString('Plan the coupon work', $prompt);
    }

    // -- Recent changes -----------------------------------------------------------

    public function test_changes_list_status_moves_new_tasks_and_status_updates_the_asker_may_see(): void
    {
        $lead = $this->user(User::ROLE_ADMIN);
        $moved = $this->task($this->team, 'Moved along', ['status' => 'in_progress']);
        $moved->update(['status' => 'in_review']);
        $hidden = $this->task($this->otherTeam, 'Beta moved', ['status' => 'in_progress']);
        $hidden->update(['status' => 'done']);
        $ours = $this->release(overrides: ['name' => 'Our release']);
        $theirs = $this->release($this->otherTeam, ['name' => 'Beta release']);
        ReleaseStatusUpdate::create(['release_id' => $ours->id, 'author_id' => $lead->id, 'status' => 'at_risk', 'note' => 'Ours slipping']);
        ReleaseStatusUpdate::create(['release_id' => $theirs->id, 'author_id' => $lead->id, 'status' => 'at_risk', 'note' => 'Beta slipping']);

        $prompt = $this->prompt($this->user(User::ROLE_DEVELOPER, $this->team), 'What changed this week?');

        $this->assertStringContainsString("[T{$moved->id}] PM-{$moved->id}: In Progress to In Review", $prompt);
        $this->assertStringContainsString('Moved along', $prompt); // its task record, for the citation
        $this->assertStringContainsString('Ours slipping', $prompt);
        $this->assertStringNotContainsString('Beta moved', $prompt);
        $this->assertStringNotContainsString("PM-{$hidden->id}", $prompt);
        $this->assertStringNotContainsString('Beta slipping', $prompt);
    }

    public function test_changes_keep_to_the_period_the_question_names(): void
    {
        $old = $this->task($this->team, 'Moved long ago', ['status' => 'in_progress']);
        $old->update(['status' => 'in_review']);
        $old->statusChanges()->whereNotNull('from_status')->update(['changed_at' => today()->subWeeks(3)]);
        $recent = $this->task($this->team, 'Moved last week', ['status' => 'in_progress']);
        $recent->update(['status' => 'in_review']);
        $recent->statusChanges()->whereNotNull('from_status')->update(['changed_at' => today()->subWeek()->startOfWeek()->addDay()]);

        $prompt = $this->prompt($this->user(User::ROLE_ADMIN), 'What changed last week?');

        $this->assertStringContainsString("[T{$recent->id}] PM-{$recent->id}: In Progress to In Review", $prompt);
        $this->assertStringNotContainsString("[T{$old->id}] PM-{$old->id}: In Progress", $prompt);
        $this->assertStringContainsString('1 status moves', $prompt);
    }

    public function test_a_release_completed_in_the_period_is_listed_and_can_be_cited(): void
    {
        $release = $this->release(overrides: ['name' => 'Shipped it', 'completed_at' => now()->subDay()]);

        $this->claude->respondUsing(fn () => ['answer' => "Done [R{$release->id}].", 'citations' => ["R{$release->id}"], 'follow_ups' => []]);
        $out = $this->actingAs($this->user(User::ROLE_ADMIN))->postJson(route('ai.ask'), ['question' => 'What shipped this month?'])->assertOk()->json();

        $this->assertStringContainsString("Completed [R{$release->id}] Shipped it", $this->claude->prompt());
        $this->assertSame(["R{$release->id}"], array_column($out['sources'], 'ref'));
    }

    // -- Notes and meetings -------------------------------------------------------

    public function test_the_latest_meeting_notes_come_with_the_question_not_just_a_keyword(): void
    {
        $teammate = $this->user(User::ROLE_DEVELOPER, $this->team);
        $stranger = $this->user(User::ROLE_DEVELOPER, $this->team);
        MeetingNote::create(['created_by' => $teammate->id, 'title' => 'Monday sync', 'meeting_date' => today(), 'body' => '<div>Go with vendor B</div>', 'type' => 'sync_up']);
        MeetingNote::create(['created_by' => $stranger->id, 'title' => 'Closed session', 'meeting_date' => today(), 'body' => '<div>Attendees only</div>', 'visibility' => MeetingNote::VISIBILITY_ATTENDEES]);

        $prompt = $this->prompt($this->user(User::ROLE_DEVELOPER, $this->team), 'What did we decide in the last standup?');

        $this->assertStringContainsString('Go with vendor B', $prompt);
        $this->assertStringContainsString('Sync Up', $prompt);
        $this->assertStringNotContainsString('Closed session', $prompt);
    }

    public function test_other_peoples_private_notes_and_events_never_reach_the_prompt(): void
    {
        $author = $this->user(User::ROLE_DEVELOPER, $this->team);
        Note::create(['user_id' => $author->id, 'date' => today(), 'body' => '<div>Private diary</div>', 'visibility' => 'private']);
        Note::create(['user_id' => $author->id, 'date' => today(), 'body' => '<div>Shared thought</div>', 'visibility' => 'shared']);
        Event::create(['title' => 'Private dentist', 'type' => 'meeting', 'starts_at' => now()->addDay(), 'created_by' => $author->id, 'visibility' => Event::VISIBILITY_PRIVATE]);

        // Even for an admin, who reads everything else.
        $prompt = $this->prompt($this->user(User::ROLE_ADMIN), 'What are the latest notes and private dentist events?');

        $this->assertStringContainsString('Shared thought', $prompt);
        $this->assertStringNotContainsString('Private diary', $prompt);
        $this->assertStringNotContainsString('Private dentist', $prompt);
    }

    // -- What the asker is told, and rendering -------------------------------------

    public function test_the_asker_block_says_what_this_role_cant_see(): void
    {
        $this->assertMatchesRegularExpression("/Not available to this asker: other teams' work.*never available/", $this->prompt($this->user(User::ROLE_DEVELOPER, $this->team), 'Who is free?'));
        $this->assertMatchesRegularExpression("/Not available to this asker: capacity and logged hours for teams this person doesn't lead/", $this->prompt($this->user(User::ROLE_TEAM_LEAD), 'Who is free?'));
        $this->assertStringContainsString('Not available to this asker: Performance scores', $this->prompt($this->user(User::ROLE_CTO), 'Who is free?'));
    }

    public function test_new_references_become_links_and_other_brackets_stay_as_text(): void
    {
        $lead = $this->user(User::ROLE_ADMIN);

        $this->claude->respondUsing(fn () => [
            'answer' => "Capacity [C{$this->team->id}], flow [F{$this->team->id}], totals [W1], missing [I999999], planned for [Q3].",
            'citations' => ["C{$this->team->id}", "F{$this->team->id}", 'W1', 'I999999'],
            'follow_ups' => [],
        ]);

        $out = $this->actingAs($lead)->postJson(route('ai.ask'), ['question' => 'Who has capacity in Alpha and what is the cycle time?'])->assertOk()->json();

        $this->assertStringContainsString(e(route('capacity.index', ['team_id' => $this->team->id])), $out['html']);
        $this->assertStringContainsString(e(route('reports.flow', ['team_id' => $this->team->id])), $out['html']);
        $this->assertStringContainsString(route('tasks.index'), $out['html']);
        $this->assertStringNotContainsString('I999999', $out['html']);
        $this->assertStringContainsString('[Q3]', $out['html']);
        $this->assertEqualsCanonicalizing(["C{$this->team->id}", "F{$this->team->id}", 'W1'], array_column($out['sources'], 'ref'));
    }

    public function test_a_digest_citing_a_task_also_sends_that_task_so_the_link_works(): void
    {
        $lead = $this->user(User::ROLE_ADMIN);
        $late = $this->task($this->team, 'Way over estimate', ['status' => 'in_progress', 'estimate_minutes' => 60]);
        TaskTimeLog::create(['task_id' => $late->id, 'user_id' => $lead->id, 'logged_on' => today(), 'minutes' => 600]);
        $late->update(['status' => 'done']);

        $records = app(WorkspaceAsk::class)->retrieve('Which estimates were off?', $lead);

        $this->assertStringContainsString("[T{$late->id}]", $records->firstWhere('ref', 'L1')['text']);
        $this->assertNotNull($records->firstWhere('ref', "T{$late->id}"));
    }

    public function test_every_reference_kind_is_unique_across_records(): void
    {
        $this->task($this->team, 'Anything');
        $records = app(WorkspaceAsk::class)->retrieve('What changed, who has capacity, and what requests are there?', $this->user(User::ROLE_ADMIN));

        $this->assertSame($records->count(), $records->pluck('ref')->unique()->count());
    }

    // -- Reading the question --------------------------------------------------------

    public function test_a_question_is_read_for_topics_periods_and_task_references(): void
    {
        $this->assertTrue(AskQuestion::parse('Who has capacity next week?')->asks('capacity'));
        $this->assertTrue(AskQuestion::parse('What is at risk?')->asks('progress'));
        $this->assertTrue(AskQuestion::parse('When will Checkout finish?')->asks('progress'));
        $this->assertTrue(AskQuestion::parse('What changed since Monday?')->asks('changes'));
        $this->assertTrue(AskQuestion::parse('How many hours did we log?')->asks('time'));
        $this->assertTrue(AskQuestion::parse('What did we decide in the standup?')->asks('meetings', 'tasksheet'));
        $this->assertFalse(AskQuestion::parse('Tell me about the coupon bug')->asks('capacity', 'flow', 'time', 'requests', 'notifications', 'tasksheet', 'changes', 'meetings', 'notes', 'progress'));

        $this->assertSame([12, 345], AskQuestion::parse('Compare PM-12 and pm345')->taskIds);
        $this->assertNotContains('release', AskQuestion::parse('What is the current release doing?')->words->all());

        $this->travelTo('2026-11-04 10:00:00'); // a Wednesday
        $period = fn (string $q) => array_map(fn ($d) => $d->toDateString(), AskQuestion::parse($q)->periodOr(7));

        $this->assertSame(['from' => '2026-10-29', 'to' => '2026-11-04'], $period('What changed?'));
        $this->assertSame(['from' => '2026-11-03', 'to' => '2026-11-03'], $period('What happened yesterday?'));
        $this->assertSame(['from' => '2026-10-26', 'to' => '2026-11-01'], $period('What changed last week?'));
        $this->assertSame(['from' => '2026-11-02', 'to' => '2026-11-04'], $period('What changed this week?'));
        $this->assertSame(['from' => '2026-10-01', 'to' => '2026-10-31'], $period('What shipped last month?'));
        $this->assertSame(['from' => '2026-11-01', 'to' => '2026-11-04'], $period('What shipped this month?'));
        $this->assertSame(['from' => '2026-10-31', 'to' => '2026-11-04'], $period('Changes in the last 5 days'));
    }

    public function test_the_ask_page_offers_questions_the_role_can_answer(): void
    {
        $this->actingAs($this->user(User::ROLE_ADMIN))->get(route('ai.ask.page'))->assertOk()->assertSee('Who has capacity next week?');
        $this->actingAs($this->user(User::ROLE_VIEWER))->get(route('ai.ask.page'))->assertOk()->assertDontSee('Who has capacity next week?');
        $this->actingAs($this->user(User::ROLE_DEVELOPER, $this->team))->get(route('ai.ask.page'))->assertOk()
            ->assertDontSee('Who has capacity next week?')->assertSee('Which of my tasks are blocked?');
    }

    public function test_release_figures_are_not_computed_for_a_completed_release(): void
    {
        $release = $this->release(overrides: ['name' => 'Old one', 'completed_at' => now()->subMonth(), 'completion_notes' => 'All went well']);

        $text = app(WorkspaceAsk::class)->retrieve('Tell me about the old one', $this->user(User::ROLE_ADMIN))->firstWhere('ref', "R{$release->id}")['text'];

        $this->assertStringContainsString('completed', $text);
        $this->assertStringContainsString('All went well', $text);
        $this->assertStringNotContainsString('working days left', $text);
    }
}
