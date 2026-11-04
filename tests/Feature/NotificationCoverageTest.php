<?php

namespace Tests\Feature;

use App\Jobs\BuildKpiReport;
use App\Jobs\ExtractCalendarEvents;
use App\Jobs\GenerateAiInsight;
use App\Models\AiInsight;
use App\Models\Event;
use App\Models\EventImport;
use App\Models\KpiReport;
use App\Models\MeetingNote;
use App\Models\Note;
use App\Models\PerformanceCompetency;
use App\Models\Project;
use App\Models\RecurringTask;
use App\Models\Release;
use App\Models\ReleaseTemplate;
use App\Models\Task;
use App\Models\TasksheetExport;
use App\Models\Team;
use App\Models\TriageItem;
use App\Models\User;
use App\Notifications\InboxNotification;
use App\Notifications\TriageNotification;
use App\Services\Ai\SuggestedTaskStore;
use App\Services\Board\PlanTaskExtractor;
use App\Services\Board\PlanToBoardService;
use App\Services\BoardService;
use App\Services\Calendar\EventImportService;
use App\Services\EventService;
use App\Services\Kpi\KpiReportExporter;
use App\Services\MeetingNoteService;
use App\Services\NoteService;
use App\Services\NotificationService;
use App\Services\PerformanceEvaluationService;
use App\Services\RecurringTaskService;
use App\Services\ReleaseService;
use App\Services\ReleaseTemplateService;
use App\Services\TaskService;
use App\Services\TasksheetExportService;
use App\Services\TasksheetService;
use App\Services\TeamService;
use App\Services\TriageService;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

/**
 * Everything openspec/changes/notification-coverage adds: each kind, who hears
 * it, who is left out, grouped rows, and where opening one goes.
 */
class NotificationCoverageTest extends TestCase
{
    use RefreshDatabase;

    private Team $core;

    private Team $other;

    private User $admin;

    private User $lead;

    private User $dev;

    private User $qa;

    private User $outsider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'name' => 'Ada Admin']);
        $this->lead = User::factory()->create(['role' => User::ROLE_TEAM_LEAD, 'name' => 'Lee Lead']);
        $this->dev = User::factory()->create(['role' => User::ROLE_DEVELOPER, 'name' => 'Dana Dev']);
        $this->qa = User::factory()->create(['role' => User::ROLE_QA, 'name' => 'Quinn QA']);
        $this->outsider = User::factory()->create(['role' => User::ROLE_DEVELOPER, 'name' => 'Otto Other']);

        $this->core = Team::create(['name' => 'Core', 'color' => '#0891b2', 'team_lead_id' => $this->lead->id]);
        $this->other = Team::create(['name' => 'Other', 'color' => '#7c3aed']);
        $this->core->members()->attach([$this->dev->id, $this->qa->id]);
        $this->other->members()->attach($this->outsider->id);
    }

    private function task(array $attrs = []): Task
    {
        return Task::create($attrs + [
            'title' => 'Fix login bug', 'status' => 'todo', 'position' => 0,
            'team_id' => $this->core->id, 'created_by' => $this->lead->id,
        ]);
    }

    private function release(array $attrs = []): Release
    {
        $project = Project::create(['name' => 'Portal', 'color' => '#0891b2']);

        return Release::create($attrs + [
            'project_id' => $project->id, 'team_id' => $this->core->id, 'name' => 'Spring launch',
            'year' => 2026, 'quarter' => 4, 'start_date' => '2026-11-01', 'end_date' => '2026-11-30',
        ]);
    }

    /** @return array<string, mixed> */
    private function releaseAttrs(string $name = 'Spring launch'): array
    {
        return [
            'project_id' => Project::create(['name' => 'Portal '.$name, 'color' => '#0891b2'])->id,
            'team_id' => $this->core->id, 'name' => $name,
            'year' => 2026, 'quarter' => 4, 'start_date' => '2026-11-01', 'end_date' => '2026-11-30',
        ];
    }

    /** @return list<string> the kinds this user has, oldest first */
    private function kinds(User $user): array
    {
        return $user->notifications()->orderBy('created_at')->get()->pluck('data.kind')->all();
    }

    private function row(User $user, string $kind): ?array
    {
        return $user->notifications()->get()->pluck('data')->firstWhere('kind', $kind);
    }

    private function clearInboxes(): void
    {
        DB::table('notifications')->delete();
    }

    // -- Tasks -----------------------------------------------------------------

    public function test_being_reassigned_away_names_the_next_person_and_the_actor_stays_out(): void
    {
        $this->actingAs($this->lead);
        $task = $this->task(['assignee_id' => $this->dev->id]);
        $this->clearInboxes();

        $task->update(['assignee_id' => $this->qa->id]);

        $this->assertStringContainsString('Quinn QA', $this->row($this->dev, 'unassigned')['message']);
        $this->assertSame([], $this->kinds($this->lead));

        // Taking a task off yourself isn't news.
        $this->actingAs($this->qa);
        $this->clearInboxes();
        $task->update(['assignee_id' => null]);
        $this->assertSame([], $this->kinds($this->qa));
    }

    public function test_someone_who_cannot_see_the_task_after_reassignment_isnt_told(): void
    {
        $this->actingAs($this->lead);
        $task = $this->task(['assignee_id' => $this->outsider->id]);
        $this->clearInboxes();

        $task->update(['assignee_id' => $this->dev->id]);

        $this->assertSame([], $this->kinds($this->outsider));
    }

    public function test_clearing_a_block_tells_the_assignee_and_team_lead_but_finishing_doesnt(): void
    {
        $task = $this->task(['assignee_id' => $this->dev->id, 'blocked_at' => now(), 'blocked_reason' => 'Waiting']);

        $this->actingAs($this->qa);
        $task->update(['blocked_at' => null, 'blocked_reason' => null]);

        $this->assertSame(['block_cleared'], $this->kinds($this->dev));
        $this->assertSame(['block_cleared'], $this->kinds($this->lead));
        $this->assertSame([], $this->kinds($this->qa));

        $finished = $this->task(['assignee_id' => $this->dev->id, 'blocked_at' => now(), 'blocked_reason' => 'Waiting']);
        $this->clearInboxes();
        $finished->update(['status' => 'done']);
        $this->assertNull($this->row($this->dev, 'block_cleared'));
    }

    public function test_a_blocked_task_with_no_team_lead_reaches_org_level_leads(): void
    {
        $this->actingAs($this->dev);
        $this->task(['team_id' => $this->other->id, 'assignee_id' => $this->dev->id])->update(['blocked_at' => now()]);

        $this->assertSame(['blocked'], $this->kinds($this->admin));
        $this->assertSame([], $this->kinds($this->lead));
    }

    public function test_a_blocked_task_with_a_team_lead_doesnt_reach_org_level_leads(): void
    {
        $this->actingAs($this->dev);
        $this->task(['assignee_id' => $this->dev->id])->update(['blocked_at' => now()]);

        $this->assertSame([], $this->kinds($this->admin));
        $this->assertSame(['blocked'], $this->kinds($this->lead));
    }

    public function test_sending_work_back_tells_the_assignee_unless_they_did_it(): void
    {
        $task = $this->task(['assignee_id' => $this->dev->id, 'status' => 'in_review']);

        $this->actingAs($this->qa);
        $task->update(['status' => 'recheck']);
        $this->assertStringContainsString('back for a recheck', $this->row($this->dev, 'sent_back')['message']);

        $this->clearInboxes();
        $task->update(['status' => 'done']);
        $task->update(['status' => 'in_progress']);
        $this->assertStringContainsString('reopened', $this->row($this->dev, 'sent_back')['message']);

        $this->clearInboxes();
        $this->actingAs($this->dev);
        $task->update(['status' => 'recheck']);
        $this->assertSame([], $this->kinds($this->dev));
    }

    public function test_due_date_and_urgent_changes_tell_the_assignee(): void
    {
        $task = $this->task(['assignee_id' => $this->dev->id, 'due_date' => '2026-11-10']);

        $this->actingAs($this->lead);
        $task->update(['due_date' => '2026-11-12']);
        $this->assertStringContainsString('Nov 12', $this->row($this->dev, 'due_changed')['message']);

        $this->clearInboxes();
        $task->update(['priority' => 'high']);
        $this->assertSame([], $this->kinds($this->dev));

        $task->update(['priority' => 'urgent']);
        $this->assertSame(['urgent'], $this->kinds($this->dev));
    }

    public function test_due_date_changes_on_finished_or_newly_assigned_tasks_are_quiet(): void
    {
        $this->actingAs($this->lead);
        $finished = $this->task(['assignee_id' => $this->dev->id, 'status' => 'done', 'due_date' => '2026-11-10']);
        $this->clearInboxes();
        $finished->update(['due_date' => '2026-11-12']);
        $this->assertSame([], $this->kinds($this->dev));

        $task = $this->task(['due_date' => '2026-11-10']);
        $task->update(['assignee_id' => $this->dev->id, 'due_date' => '2026-11-12']);
        $this->assertSame(['assigned'], $this->kinds($this->dev));
    }

    public function test_deleting_a_task_tells_each_assignee_once_including_subtasks(): void
    {
        $task = $this->task(['assignee_id' => $this->dev->id]);
        $this->task(['title' => 'A', 'parent_id' => $task->id, 'assignee_id' => $this->dev->id]);
        $this->task(['title' => 'B', 'parent_id' => $task->id, 'assignee_id' => $this->dev->id]);
        $this->task(['title' => 'C', 'parent_id' => $task->id, 'assignee_id' => $this->qa->id]);

        $this->actingAs($this->admin);
        $task->delete();

        $this->assertSame(['task_deleted'], $this->kinds($this->dev));
        $this->assertSame(['task_deleted'], $this->kinds($this->qa));
        $this->assertSame([], $this->kinds($this->admin));

        // Purging from the trash later says nothing new.
        $this->clearInboxes();
        Task::withTrashed()->find($task->id)->forceDelete();
        $this->assertSame([], $this->kinds($this->dev));
    }

    public function test_recurring_tasks_reach_their_assignee_grouped_with_no_signed_in_user(): void
    {
        foreach (['Backup check', 'Log review'] as $title) {
            RecurringTask::create(['title' => $title, 'frequency' => 'daily', 'next_run_on' => today()->toDateString(), 'created_by' => $this->lead->id, 'assignee_id' => $this->dev->id, 'team_id' => $this->core->id]);
        }
        RecurringTask::create(['title' => 'Solo', 'frequency' => 'daily', 'next_run_on' => today()->toDateString(), 'created_by' => $this->lead->id, 'assignee_id' => $this->qa->id, 'team_id' => $this->core->id]);

        $this->assertFalse(Auth::check());
        app(RecurringTaskService::class)->runDue();

        $this->assertSame('2 recurring tasks were created for you', $this->row($this->dev, 'assigned')['message']);
        $this->assertStringContainsString('Solo', $this->row($this->qa, 'assigned')['message']);
        $this->assertSame(1, $this->dev->notifications()->count());
    }

    public function test_writes_with_no_signed_in_user_stay_silent(): void
    {
        $task = $this->task(['assignee_id' => $this->dev->id]);
        $task->comments()->create(['user_id' => $this->qa->id, 'body' => 'From a seeder']);
        $task->update(['assignee_id' => $this->qa->id, 'status' => 'recheck', 'priority' => 'urgent', 'due_date' => '2026-11-12', 'blocked_at' => now()]);
        $task->delete();

        $release = app(ReleaseService::class)->create($this->releaseAttrs(), [], [], [$this->dev->id]);
        $release->comments()->create(['user_id' => $this->qa->id, 'body' => 'From a seeder']);
        app(TeamService::class)->addMember($this->core, $this->outsider->id);
        $this->core->update(['team_lead_id' => $this->admin->id]);

        $this->assertSame(0, DB::table('notifications')->count());
    }

    // -- Grouping --------------------------------------------------------------

    public function test_bulk_edit_sends_one_grouped_row_per_person_and_kind(): void
    {
        $this->actingAs($this->lead);
        $tasks = collect(range(1, 12))->map(fn ($i) => $this->task(['title' => "Task {$i}"]));

        app(TaskService::class)->bulkUpdate($tasks, ['attributes' => ['assignee_id' => $this->dev->id]]);

        $this->assertSame(1, $this->dev->notifications()->count());
        $row = $this->row($this->dev, 'assigned');
        $this->assertSame('Lee Lead assigned you 12 tasks', $row['message']);
        $this->assertSame(12, $row['count']);
        $this->assertSame('tasks', $row['target_type']);

        $this->clearInboxes();
        app(TaskService::class)->bulkUpdate($tasks->map->fresh(), ['attributes' => ['assignee_id' => $this->qa->id]]);
        $this->assertSame('Lee Lead took you off 12 tasks', $this->row($this->dev, 'unassigned')['message']);
        $this->assertSame('Lee Lead assigned you 12 tasks', $this->row($this->qa, 'assigned')['message']);
    }

    public function test_a_bulk_write_of_one_keeps_the_ordinary_row(): void
    {
        $this->actingAs($this->lead);
        $task = $this->task();

        app(TaskService::class)->bulkUpdate([$task], ['attributes' => ['assignee_id' => $this->dev->id]]);

        $this->assertStringContainsString('Fix login bug', $this->row($this->dev, 'assigned')['message']);
    }

    public function test_bulk_finishing_groups_the_tasks_it_unblocks(): void
    {
        $this->actingAs($this->lead);
        $blockers = collect(['One', 'Two'])->map(fn ($t) => $this->task(['title' => $t]));
        foreach ($blockers as $i => $blocker) {
            $this->task(['title' => "Waiting {$i}", 'assignee_id' => $this->dev->id])->blockers()->attach($blocker);
        }
        $this->clearInboxes();

        app(TaskService::class)->bulkUpdate($blockers, ['attributes' => ['status' => 'done']]);

        $this->assertSame('2 of your tasks are ready to start', $this->row($this->dev, 'unblocked')['message']);
    }

    public function test_bulk_delete_groups_per_assignee(): void
    {
        $tasks = collect(range(1, 3))->map(fn ($i) => $this->task(['title' => "Task {$i}", 'assignee_id' => $this->dev->id]));

        $this->actingAs($this->admin);
        app(BoardService::class)->deleteCards($tasks);

        $this->assertSame(1, $this->dev->notifications()->count());
        $this->assertSame('Ada Admin deleted 3 of your tasks', $this->row($this->dev, 'task_deleted')['message']);
    }

    public function test_claude_suggestions_group_for_the_assignee(): void
    {
        $this->actingAs($this->lead);

        app(SuggestedTaskStore::class)->create([
            ['title' => 'One', 'assignee_id' => $this->dev->id],
            ['title' => 'Two', 'assignee_id' => $this->dev->id],
        ], $this->lead);

        $this->assertSame('Lee Lead assigned you 2 tasks', $this->row($this->dev, 'assigned')['message']);
    }

    public function test_a_release_template_notifies_nobody(): void
    {
        $this->actingAs($this->lead);
        $release = $this->release();
        $template = ReleaseTemplate::create(['name' => 'Standard', 'created_by' => $this->lead->id]);
        $template->tasks()->createMany([['title' => 'One'], ['title' => 'Two']]);

        $created = app(ReleaseTemplateService::class)->apply($template->load('tasks'), $release, $this->lead);

        $this->assertSame(2, $created);
        $this->assertSame(0, DB::table('notifications')->count());
    }

    public function test_a_failed_batch_sends_nothing(): void
    {
        $this->actingAs($this->lead);
        $task = $this->task();
        $notifications = app(NotificationService::class);

        try {
            $notifications->batch(function () use ($task) {
                $task->update(['assignee_id' => $this->dev->id]);

                throw new RuntimeException('Rolled back');
            });
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame([], $this->kinds($this->dev));

        // The buffer is gone and the service carries on sending.
        $this->task(['assignee_id' => $this->qa->id]);
        $this->assertSame(['assigned'], $this->kinds($this->qa));
    }

    // -- Requests --------------------------------------------------------------

    public function test_a_request_goes_to_a_team_lead_who_can_decide_it(): void
    {
        app(TriageService::class)->submit($this->dev, ['title' => 'Broken', 'team_id' => $this->core->id]);

        $this->assertSame(['request_new'], $this->kinds($this->lead));
        $this->assertSame([], $this->kinds($this->admin));
    }

    public function test_a_request_skips_an_assigned_lead_whose_role_cant_decide_it(): void
    {
        $this->other->update(['team_lead_id' => $this->outsider->id]);
        $this->clearInboxes();

        app(TriageService::class)->submit($this->qa, ['title' => 'Broken', 'team_id' => $this->other->id]);

        $this->assertSame([], $this->kinds($this->outsider));
        $this->assertSame(['request_new'], $this->kinds($this->admin));
    }

    public function test_batched_requests_group_for_the_decider(): void
    {
        $service = app(TriageService::class);

        app(NotificationService::class)->batch(function () use ($service) {
            $service->submit($this->dev, ['title' => 'One', 'team_id' => $this->core->id]);
            $service->submit($this->dev, ['title' => 'Two', 'team_id' => $this->core->id]);
        });

        $this->assertSame('Dana Dev sent 2 requests', $this->row($this->lead, 'request_new')['message']);
    }

    public function test_opening_an_accepted_request_on_a_team_the_reporter_isnt_on_lands_on_requests(): void
    {
        $this->actingAs($this->lead);
        $item = TriageItem::create(['title' => 'Broken', 'reporter_id' => $this->dev->id, 'team_id' => $this->core->id, 'priority' => 'medium']);

        app(TriageService::class)->accept($item, $this->lead, ['team_id' => $this->other->id]);

        $this->assertNotNull($item->fresh()->task_id);
        $this->actingAs($this->dev)
            ->get(route('notifications.open', $this->dev->notifications()->first()->id))
            ->assertRedirect(route('triage.index'));
    }

    // -- Releases --------------------------------------------------------------

    public function test_creating_a_release_tells_the_lead_once_and_the_members(): void
    {
        $this->actingAs($this->admin);

        app(ReleaseService::class)->create($this->releaseAttrs(), [], [], [$this->dev->id, $this->lead->id]);

        $this->assertSame(['release_created'], $this->kinds($this->lead));
        $this->assertSame(['release_member_added'], $this->kinds($this->dev));
        $this->assertSame([], $this->kinds($this->qa));
    }

    public function test_a_release_member_on_another_team_hears_nothing(): void
    {
        $this->actingAs($this->admin);

        app(ReleaseService::class)->create($this->releaseAttrs(), [], [], [$this->outsider->id]);

        $this->assertSame([], $this->kinds($this->outsider));
    }

    public function test_updating_a_release_tells_only_new_members_and_flags_real_date_changes(): void
    {
        $this->actingAs($this->admin);
        $service = app(ReleaseService::class);
        $attrs = $this->releaseAttrs();
        $phases = ['development' => ['start' => '2026-11-01', 'end' => '2026-11-10']];
        $release = $service->create($attrs, $phases, [], [$this->dev->id]);
        $this->clearInboxes();

        // Saving unchanged says nothing.
        $service->update($release, $attrs, $phases, [], [$this->dev->id]);
        $this->assertSame(0, DB::table('notifications')->count());

        $service->update($release, $attrs, ['development' => ['start' => '2026-11-02', 'end' => '2026-11-12']], [], [$this->dev->id, $this->qa->id]);

        $this->assertSame(['release_member_added'], $this->kinds($this->qa));
        $this->assertSame(['release_dates_changed'], $this->kinds($this->dev));
        $this->assertSame(['release_dates_changed'], $this->kinds($this->lead));
    }

    public function test_completing_tells_the_team_and_reopening_tells_lead_and_members(): void
    {
        $this->actingAs($this->admin);
        $release = $this->release();
        $release->members()->attach($this->dev);
        $service = app(ReleaseService::class);

        $service->complete($release, $this->admin);

        foreach ([$this->lead, $this->dev, $this->qa] as $person) {
            $this->assertSame(['release_completed'], $this->kinds($person));
        }
        $this->assertSame([], $this->kinds($this->admin));
        $this->assertSame([], $this->kinds($this->outsider));

        $this->clearInboxes();
        $service->reopen($release);

        $this->assertSame(['release_reopened'], $this->kinds($this->lead));
        $this->assertSame(['release_reopened'], $this->kinds($this->dev));
        $this->assertSame([], $this->kinds($this->qa));
    }

    public function test_a_release_comment_reaches_lead_members_and_earlier_commenters_only(): void
    {
        $release = $this->release();
        $release->members()->attach($this->dev);

        $this->actingAs($this->qa);
        $release->comments()->create(['user_id' => $this->qa->id, 'body' => 'First']);
        $this->clearInboxes();

        $this->actingAs($this->admin);
        $release->comments()->create(['user_id' => $this->admin->id, 'body' => '<p>Ready to ship</p>']);

        $this->assertSame('Ready to ship', $this->row($this->dev, 'release_commented')['excerpt']);
        $this->assertSame(['release_commented'], $this->kinds($this->lead));
        $this->assertSame(['release_commented'], $this->kinds($this->qa));
        $this->assertSame([], $this->kinds($this->admin));
        $this->assertSame([], $this->kinds($this->outsider));
    }

    // -- Meetings, events, notes -----------------------------------------------

    public function test_only_newly_added_meeting_attendees_hear(): void
    {
        $this->actingAs($this->lead);
        $service = app(MeetingNoteService::class);
        $attrs = ['title' => 'Planning', 'type' => 'general', 'meeting_date' => '2026-11-05', 'body' => '<p>Secret plan</p>', 'visibility' => MeetingNote::VISIBILITY_ATTENDEES];

        $note = $service->create($attrs, [$this->dev->id, $this->lead->id], $this->lead);
        $this->assertSame(['meeting_attendee'], $this->kinds($this->dev));
        $this->assertSame([], $this->kinds($this->lead));
        $this->assertStringNotContainsString('Secret', json_encode($this->row($this->dev, 'meeting_attendee')));

        $this->clearInboxes();
        $service->update($note, $attrs, [$this->dev->id, $this->qa->id]);
        $this->assertSame([], $this->kinds($this->dev));
        $this->assertSame(['meeting_attendee'], $this->kinds($this->qa));
    }

    public function test_event_invite_change_and_cancel(): void
    {
        $this->actingAs($this->lead);
        $service = app(EventService::class);
        $attrs = ['title' => 'Demo', 'type' => 'meeting', 'starts_at' => '2026-11-10 10:00:00', 'ends_at' => '2026-11-10 11:00:00', 'location' => 'Room 1', 'visibility' => Event::VISIBILITY_PRIVATE];

        $event = $service->create($attrs, [$this->dev->id], $this->lead);
        $this->assertSame(['event_invited'], $this->kinds($this->dev));
        $this->assertSame([], $this->kinds($this->lead));

        // A new title, and a new guest, isn't a change for those invited before.
        $this->clearInboxes();
        $service->update($event, ['title' => 'Demo 2'] + $attrs, [$this->dev->id, $this->qa->id]);
        $this->assertSame([], $this->kinds($this->dev));
        $this->assertSame(['event_invited'], $this->kinds($this->qa));

        $this->clearInboxes();
        $service->update($event, ['starts_at' => '2026-11-11 10:00:00', 'ends_at' => '2026-11-11 11:00:00', 'location' => 'Room 2'] + $attrs, [$this->dev->id, $this->qa->id]);
        $this->assertStringContainsString('time and place', $this->row($this->dev, 'event_changed')['message']);
        $this->assertSame(['event_changed'], $this->kinds($this->qa));

        $this->clearInboxes();
        $service->update($event, ['starts_at' => '2026-11-11 10:00:00', 'ends_at' => '2026-11-11 11:00:00', 'location' => 'Room 3'] + $attrs, [$this->dev->id, $this->qa->id]);
        $this->assertStringContainsString('the place of', $this->row($this->dev, 'event_changed')['message']);

        $this->clearInboxes();
        $this->delete(route('events.destroy', $event))->assertRedirect();
        $this->assertSame(['event_cancelled'], $this->kinds($this->dev));
        $this->assertSame([], $this->kinds($this->lead));
        $this->assertSame(0, DB::table('notifications')->whereNotIn('notifiable_id', [$this->dev->id, $this->qa->id])->count());
    }

    public function test_an_admin_cancelling_a_shared_event_tells_its_creator_too(): void
    {
        $this->actingAs($this->lead);
        $event = app(EventService::class)->create(['title' => 'All hands', 'type' => 'meeting', 'starts_at' => '2026-11-10 10:00:00'], [], $this->lead);

        $this->actingAs($this->admin);
        $event->delete();

        $this->assertSame(['event_cancelled'], $this->kinds($this->lead));
    }

    public function test_event_reminder_goes_once_and_again_after_a_move(): void
    {
        $this->travelTo('2026-11-09 08:00:00');
        $event = Event::create(['title' => 'Demo', 'type' => 'meeting', 'starts_at' => '2026-11-10 10:00:00', 'created_by' => $this->lead->id]);
        $event->attendees()->attach($this->dev);
        Event::create(['title' => 'Later', 'type' => 'meeting', 'starts_at' => '2026-11-20 10:00:00', 'created_by' => $this->lead->id]);
        $service = app(NotificationService::class);

        $this->assertSame(2, $service->sendEventReminders());
        $this->assertSame(0, $service->sendEventReminders());
        $this->assertStringContainsString('starts tomorrow at 10:00 AM', $this->row($this->dev, 'event_tomorrow')['message']);
        $this->assertSame([], $this->kinds($this->qa));

        $event->update(['starts_at' => '2026-11-11 10:00:00']);
        $this->travelTo('2026-11-10 08:00:00');
        $this->assertSame(2, $service->sendEventReminders());
    }

    public function test_due_reminders_command_sends_event_reminders_too(): void
    {
        $this->travelTo('2026-11-09 08:00:00');
        Event::create(['title' => 'Demo', 'type' => 'meeting', 'starts_at' => '2026-11-10 10:00:00', 'created_by' => $this->lead->id]);

        $this->artisan('notifications:due-reminders')->assertSuccessful();

        $this->assertSame(['event_tomorrow'], $this->kinds($this->lead));
    }

    public function test_private_and_shared_notes_are_silent_and_specific_tells_new_recipients_with_no_text(): void
    {
        $this->actingAs($this->lead);
        $service = app(NoteService::class);

        $service->create(['date' => '2026-11-04', 'body' => 'Private', 'visibility' => Note::VISIBILITY_PRIVATE], [], $this->lead);
        $service->create(['date' => '2026-11-04', 'body' => 'Shared', 'visibility' => Note::VISIBILITY_SHARED], [], $this->lead);
        $this->assertSame(0, DB::table('notifications')->count());

        $attrs = ['date' => '2026-11-04', 'body' => 'Confidential idea', 'visibility' => Note::VISIBILITY_SPECIFIC];
        $note = $service->create($attrs, [$this->dev->id], $this->lead);
        $this->assertSame(['note_shared'], $this->kinds($this->dev));
        $this->assertStringNotContainsString('Confidential', json_encode($this->row($this->dev, 'note_shared')));

        $this->clearInboxes();
        $service->update($note, $attrs, [$this->dev->id, $this->qa->id]);
        $this->assertSame([], $this->kinds($this->dev));
        $this->assertSame(['note_shared'], $this->kinds($this->qa));
    }

    // -- Background jobs -------------------------------------------------------

    public function test_export_results_go_to_the_requester_only(): void
    {
        $export = TasksheetExport::create(['requested_by' => $this->lead->id, 'team_id' => $this->core->id, 'member_id' => $this->dev->id, 'date_from' => '2026-11-01', 'date_to' => '2026-11-02', 'include_feedback' => true, 'status' => TasksheetExport::STATUS_PROCESSING]);

        app(TasksheetExportService::class)->fail($export->id, 'Disk full');

        $this->assertSame(['export_failed'], $this->kinds($this->lead));
        $this->assertStringNotContainsString('Disk full', json_encode($this->row($this->lead, 'export_failed')));
        $this->assertSame([], $this->kinds($this->dev));

        // A failure on an export no longer running says nothing more.
        app(TasksheetExportService::class)->fail($export->id, 'Again');
        $this->assertCount(1, $this->kinds($this->lead));

        $this->clearInboxes();
        app(NotificationService::class)->exportFinished($export);
        $this->assertSame(['export_ready'], $this->kinds($this->lead));
    }

    public function test_kpi_report_results_go_to_the_requesting_admin_only_without_names(): void
    {
        $report = KpiReport::create(['requested_by' => $this->admin->id, 'scope' => KpiReport::SCOPE_MEMBER, 'member_id' => $this->dev->id, 'period_start' => '2026-10-01', 'period_end' => '2026-10-31', 'format' => 'xlsx', 'status' => KpiReport::STATUS_PROCESSING, 'members_total' => 1, 'members_done' => 0]);

        (new BuildKpiReport($report->id))->handle(app(KpiReportExporter::class));

        $row = $this->row($this->admin, 'kpi_failed');
        $this->assertSame('Your KPI report for Oct 1 to Oct 31, 2026 couldn’t be built', $row['message']);
        $this->assertStringNotContainsString('Dana', json_encode($row));
        $this->assertSame([], $this->kinds($this->dev));
        $this->assertSame([], $this->kinds($this->lead));

        $this->clearInboxes();
        app(NotificationService::class)->kpiFinished($report);
        $this->assertSame(['kpi_ready'], $this->kinds($this->admin));

        // An admin who has since been demoted hears nothing about it.
        $this->clearInboxes();
        $this->admin->update(['role' => User::ROLE_DEVELOPER]);
        app(NotificationService::class)->kpiFinished($report);
        $this->assertSame([], $this->kinds($this->admin));
    }

    public function test_calendar_import_results_go_to_the_uploader_only(): void
    {
        $import = EventImport::create(['user_id' => $this->dev->id, 'original_name' => 'plan.pdf', 'file_path' => 'x', 'extension' => 'pdf', 'status' => EventImport::STATUS_PROCESSING, 'timezone' => 'UTC', 'proposals' => [['title' => 'A']]]);

        (new ExtractCalendarEvents($import->id))->failed(new RuntimeException('API key secret'));

        $this->assertSame(['import_failed'], $this->kinds($this->dev));
        $this->assertStringNotContainsString('secret', json_encode($this->row($this->dev, 'import_failed')));
        $this->assertSame(0, DB::table('notifications')->where('notifiable_id', '!=', $this->dev->id)->count());

        // Failing a finished import again changes nothing.
        (new ExtractCalendarEvents($import->id))->failed(new RuntimeException('Again'));
        $this->assertCount(1, $this->kinds($this->dev));

        $this->clearInboxes();
        app(NotificationService::class)->importFinished($import);
        $this->assertStringContainsString('1 event found', $this->row($this->dev, 'import_ready')['message']);
    }

    public function test_confirming_an_import_groups_invitations_per_attendee(): void
    {
        $this->actingAs($this->lead);
        $titles = ['One', 'Two', 'Three'];
        $imports = app(EventImportService::class);
        $proposal = fn (string $title) => ['title' => $title, 'type' => 'meeting', 'date' => '2026-11-10', 'start_time' => '10:00', 'end_time' => '11:00'];
        $import = EventImport::create([
            'user_id' => $this->lead->id, 'original_name' => 'plan.pdf', 'file_path' => 'x', 'extension' => 'pdf', 'status' => EventImport::STATUS_NEEDS_REVIEW, 'timezone' => 'UTC',
            'proposals' => $imports->normalize(array_map(fn (string $title) => ['title' => $title, 'start_date' => '2026-11-10', 'start_time' => '10:00'], $titles)),
        ]);
        $rows = array_map(fn (string $title) => $proposal($title) + ['include' => true, 'attendees' => [$this->dev->id]], $titles);

        $imports->confirm($import, $rows, $this->lead);

        $this->assertSame(3, Event::count());
        $this->assertSame(1, $this->dev->notifications()->count());
        $this->assertSame('Lee Lead added you to 3 events', $this->row($this->dev, 'event_invited')['message']);
        $this->assertSame([], $this->kinds($this->lead));
    }

    public function test_splitting_someones_plan_onto_the_board_groups_for_them(): void
    {
        $this->app->instance(PlanTaskExtractor::class, new class implements PlanTaskExtractor
        {
            public function extract(string $plan, ?string $tickets = null, array $existing = []): array
            {
                return ['tasks' => [['title' => 'One', 'description' => null], ['title' => 'Two', 'description' => null]], 'covered' => 0];
            }
        });
        $this->actingAs($this->lead);
        $tasksheet = app(TasksheetService::class);
        $entry = $tasksheet->save($tasksheet->resolveEntry(['team_id' => $this->core->id, 'user_id' => $this->dev->id, 'date' => '2026-11-04']), ['plan' => 'Do things'], $this->lead);

        app(PlanToBoardService::class)->addPlanTasks($entry, null, $this->lead);

        $this->assertSame(1, $this->dev->notifications()->count());
        $this->assertSame('Lee Lead assigned you 2 tasks', $this->row($this->dev, 'assigned')['message']);
    }

    public function test_requested_claude_results_reach_the_requester_and_scheduled_ones_nobody(): void
    {
        $release = $this->release();
        $requested = AiInsight::create(['kind' => AiInsight::KIND_RELEASE_RISK, 'subject_type' => $release->getMorphClass(), 'subject_id' => $release->id, 'status' => AiInsight::STATUS_PROCESSING, 'requested_by' => $this->lead->id]);

        (new GenerateAiInsight($requested->id))->failed(new RuntimeException('Overloaded'));
        $this->assertStringContainsString('risk brief', $this->row($this->lead, 'insight_failed')['message']);
        $this->assertSame([], $this->kinds($this->dev));

        $this->clearInboxes();
        app(NotificationService::class)->insightFinished($requested, true);
        $this->assertSame(['insight_ready'], $this->kinds($this->lead));

        $this->clearInboxes();
        $digest = AiInsight::create(['kind' => AiInsight::KIND_STANDUP_DIGEST, 'subject_type' => $this->core->getMorphClass(), 'subject_id' => $this->core->id, 'status' => AiInsight::STATUS_READY]);
        app(NotificationService::class)->insightFinished($digest, true);
        $this->assertSame(0, DB::table('notifications')->count());

        // Someone who can't read the result isn't told about it.
        $hidden = AiInsight::create(['kind' => AiInsight::KIND_STANDUP_DIGEST, 'subject_type' => $this->core->getMorphClass(), 'subject_id' => $this->core->id, 'status' => AiInsight::STATUS_READY, 'requested_by' => $this->dev->id]);
        app(NotificationService::class)->insightFinished($hidden, true);
        $this->assertSame([], $this->kinds($this->dev));
    }

    // -- Teams and accounts ----------------------------------------------------

    public function test_joining_and_leaving_a_team_tell_that_person_once(): void
    {
        $this->actingAs($this->lead);
        $service = app(TeamService::class);

        $service->addMember($this->core, $this->outsider->id);
        $service->addMember($this->core, $this->outsider->id);
        $this->assertSame(['team_added'], $this->kinds($this->outsider));

        $this->clearInboxes();
        $service->removeMember($this->core, $this->outsider);
        $service->removeMember($this->core, $this->outsider);
        $this->assertSame(['team_removed'], $this->kinds($this->outsider));
        $this->assertSame([], $this->kinds($this->lead));
    }

    public function test_changing_the_team_lead_tells_both_people(): void
    {
        $this->actingAs($this->admin);

        app(TeamService::class)->updateLead($this->core, $this->dev->id);

        $this->assertSame(['team_lead_assigned'], $this->kinds($this->dev));
        $this->assertSame(['team_lead_removed'], $this->kinds($this->lead));

        // The team settings form saves the same column.
        $this->clearInboxes();
        $this->core->fresh()->update(['team_lead_id' => $this->admin->id]);
        $this->assertSame(['team_lead_removed'], $this->kinds($this->dev));
        $this->assertSame([], $this->kinds($this->admin));
    }

    public function test_a_role_change_tells_the_person(): void
    {
        $this->actingAs($this->admin);

        app(UserService::class)->update($this->dev, ['name' => 'Dana D']);
        $this->assertSame([], $this->kinds($this->dev));

        app(UserService::class)->update($this->dev, ['role' => User::ROLE_TECH_LEAD]);
        $this->assertStringContainsString($this->dev->roleLabel(), $this->row($this->dev, 'role_changed')['message']);
    }

    public function test_deactivated_people_are_skipped_everywhere(): void
    {
        $this->dev->update(['deactivated_at' => now()]);
        $this->actingAs($this->lead);

        $task = $this->task(['assignee_id' => $this->dev->id, 'due_date' => '2026-11-10']);
        $task->update(['due_date' => '2026-11-12']);
        $task->update(['assignee_id' => null]);
        app(EventService::class)->create(['title' => 'Demo', 'type' => 'meeting', 'starts_at' => '2026-11-10 10:00:00'], [$this->dev->id], $this->lead);

        $this->assertSame([], $this->kinds($this->dev));
    }

    // -- Sensitive data --------------------------------------------------------

    public function test_lead_feedback_and_standup_marks_never_notify(): void
    {
        $this->actingAs($this->lead);
        $tasksheet = app(TasksheetService::class);
        $target = ['team_id' => $this->core->id, 'user_id' => $this->dev->id, 'date' => '2026-11-04'];

        $tasksheet->save($tasksheet->resolveEntry($target), ['plan' => 'Ship it', 'feedback' => 'Needs coaching'], $this->lead, true);
        $tasksheet->setStandupAttendance($target, false, $this->lead);

        $this->assertSame(0, DB::table('notifications')->count());
    }

    public function test_saving_performance_scores_never_notifies(): void
    {
        $this->actingAs($this->lead);
        $competency = PerformanceCompetency::create(['name' => 'Delivery', 'key' => 'delivery', 'category' => 'delivery', 'role_scope' => 'both', 'cadence' => 'monthly', 'active' => true]);
        $service = app(PerformanceEvaluationService::class);
        $period = ['type' => 'monthly', 'start' => now()->startOfMonth(), 'end' => now()->endOfMonth()];

        $service->saveScore($service->resolveScore($this->core->id, $this->dev->id, $competency->id, $period), 2, 'Low', $this->lead, $period);

        $this->assertSame(0, DB::table('notifications')->count());
    }

    // -- Opening and showing ---------------------------------------------------

    public function test_opening_goes_to_the_record_or_falls_back_with_a_message(): void
    {
        $this->actingAs($this->lead);
        $note = app(MeetingNoteService::class)->create(['title' => 'Planning', 'type' => 'general', 'meeting_date' => '2026-11-05', 'body' => 'x', 'visibility' => MeetingNote::VISIBILITY_ATTENDEES], [$this->dev->id], $this->lead);

        $this->actingAs($this->dev);
        $id = $this->dev->notifications()->first()->id;
        $this->get(route('notifications.open', $id))->assertRedirect(route('meeting-notes.show', $note));

        // Taken off the attendee list, the note is out of reach.
        $note->attendees()->detach($this->dev);
        $this->get(route('notifications.open', $id))
            ->assertRedirect(route('notifications.index'))
            ->assertSessionHas('error');
    }

    public function test_a_task_the_viewer_lost_access_to_doesnt_end_in_a_403(): void
    {
        $this->actingAs($this->lead);
        $task = $this->task(['assignee_id' => $this->dev->id]);
        $id = $this->dev->notifications()->first()->id;
        $this->core->memberRecords()->updateExistingPivot($this->dev->id, ['left_at' => now()]);
        $task->update(['assignee_id' => $this->lead->id]);

        $this->actingAs($this->dev)->get(route('notifications.open', $id))->assertRedirect(route('notifications.index'));
    }

    public function test_a_deleted_task_says_so_and_a_restored_one_opens(): void
    {
        $task = $this->task(['assignee_id' => $this->dev->id]);
        $this->actingAs($this->admin);
        $task->delete();
        $id = $this->dev->notifications()->first()->id;

        $this->actingAs($this->dev)->get(route('notifications.open', $id))
            ->assertRedirect(route('notifications.index'))
            ->assertSessionHas('error');

        $task->restore();
        $this->get(route('notifications.open', $id))->assertRedirect(route('tasks.show', $task));
    }

    public function test_targets_open_the_right_page_for_each_type(): void
    {
        $release = $this->release();
        $event = Event::create(['title' => 'Demo', 'type' => 'meeting', 'starts_at' => '2026-11-10 10:00:00', 'created_by' => $this->lead->id]);
        $import = EventImport::create(['user_id' => $this->lead->id, 'original_name' => 'a.pdf', 'file_path' => 'x', 'extension' => 'pdf', 'status' => EventImport::STATUS_NEEDS_REVIEW, 'timezone' => 'UTC']);

        $cases = [
            ['tasks', null, route('tasks.index', ['assignee_id' => $this->lead->id])],
            ['release', $release->id, route('releases.show', $release)],
            ['event', $event->id, route('events.show', $event)],
            ['calendar', null, route('calendar.index')],
            ['event_import', $import->id, route('calendar.imports.show', $import)],
            ['tasksheet', $this->core->id, route('tasksheet.index', ['team' => $this->core->id])],
            ['tasksheet_exports', null, route('tasksheet.exports.index')],
            ['team', $this->core->id, route('teams.show', $this->core)],
            ['dashboard', null, route('dashboard')],
            ['profile', null, route('profile.edit')],
            ['inbox', null, route('notifications.index')],
        ];

        foreach ($cases as [$type, $id, $url]) {
            $this->lead->notify(new InboxNotification('role_changed', 'x', $type, $id));
            $row = $this->lead->notifications()->first();
            $this->actingAs($this->lead)->get(route('notifications.open', $row->id))->assertRedirect($url);
            $row->delete();
        }
    }

    public function test_a_limited_role_is_never_sent_to_pages_it_cannot_open(): void
    {
        $this->dev->notify(new InboxNotification('team_added', 'x', 'team', $this->core->id));
        $this->actingAs($this->dev)->get(route('notifications.open', $this->dev->notifications()->first()->id))->assertRedirect(route('dashboard'));

        DB::table('notifications')->delete();
        $this->dev->notify(new InboxNotification('kpi_ready', 'x', 'kpi_reports'));
        $this->actingAs($this->dev)->get(route('notifications.open', $this->dev->notifications()->first()->id))->assertRedirect(route('notifications.index'));
    }

    public function test_every_kind_renders_in_the_list_and_the_bell(): void
    {
        foreach (NotificationService::KINDS as $kind) {
            $this->lead->notify(new InboxNotification($kind, "Sentence for {$kind}", 'dashboard'));
        }

        $page = $this->actingAs($this->lead)->get(route('notifications.index', ['page' => 1]))->assertOk();
        $page->assertSee('Sentence for', false);

        // Every kind has its own icon entry rather than the fallback.
        $view = file_get_contents(resource_path('views/notifications/_item.blade.php'));
        foreach (NotificationService::KINDS as $kind) {
            $this->assertStringContainsString("'{$kind}' =>", $view, "{$kind} has no style");
        }

        foreach (NotificationService::KINDS as $kind) {
            $html = view('notifications._item', [
                'notification' => $this->lead->notifications()->get()->firstWhere('data.kind', $kind),
                'compact' => true,
            ])->render();
            $this->assertStringContainsString("Sentence for {$kind}", $html);
        }

        // The bell, in the layout of any page.
        $this->get(route('dashboard'))->assertOk()->assertSee('Sentence for', false);
    }

    public function test_the_api_exposes_target_and_count_for_old_and_new_rows(): void
    {
        $this->actingAs($this->lead);
        $tasks = collect(['A', 'B'])->map(fn ($t) => $this->task(['title' => $t]));
        app(TaskService::class)->bulkUpdate($tasks, ['attributes' => ['assignee_id' => $this->dev->id]]);
        $this->dev->notify(new InboxNotification('role_changed', 'Changed', 'profile'));
        $item = TriageItem::create(['title' => 'Old row', 'reporter_id' => $this->dev->id, 'priority' => 'medium']);
        $this->dev->notify(new TriageNotification($item, 'request_declined', $this->lead));

        Sanctum::actingAs($this->dev);
        $rows = collect($this->getJson('/api/v1/notifications')->assertOk()->json('data'));

        $grouped = $rows->firstWhere('kind', 'assigned');
        $this->assertSame(2, $grouped['count']);
        $this->assertSame(['type' => 'tasks', 'id' => null], $grouped['target']);
        $this->assertSame(['type' => 'profile', 'id' => null], $rows->firstWhere('kind', 'role_changed')['target']);
        $this->assertSame(['type' => 'request', 'id' => $item->id], $rows->firstWhere('kind', 'request_declined')['target']);
    }

    public function test_web_and_api_writes_notify_alike(): void
    {
        $this->actingAs($this->lead)->post(route('teams.members.store', $this->other), ['user_id' => $this->qa->id])->assertRedirect();
        $this->assertSame(['team_added'], $this->kinds($this->qa));

        $this->clearInboxes();
        Auth::forgetGuards();
        Sanctum::actingAs($this->lead);
        $this->postJson("/api/v1/teams/{$this->other->id}/members", ['user_id' => $this->dev->id])->assertSuccessful();
        $this->assertSame(['team_added'], $this->kinds($this->dev));
    }
}
