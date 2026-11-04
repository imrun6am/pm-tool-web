<?php

namespace Tests\Feature;

use App\Models\Label;
use App\Models\Project;
use App\Models\Release;
use App\Models\Task;
use App\Models\Team;
use App\Models\TriageItem;
use App\Models\User;
use App\Notifications\TriageNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Requests wait outside the task system until a lead accepts them.
 */
class TriageTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    private User $lead;

    private User $dev;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lead = User::factory()->create(['role' => User::ROLE_TEAM_LEAD]);
        $this->team = Team::create(['name' => 'Core', 'color' => '#0891b2', 'team_lead_id' => $this->lead->id]);
        $this->dev = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $this->team->members()->attach($this->dev);
        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function item(array $attrs = []): TriageItem
    {
        return TriageItem::create($attrs + ['title' => 'Login is broken', 'reporter_id' => $this->dev->id, 'team_id' => $this->team->id]);
    }

    public function test_anyone_can_send_a_request_and_see_it(): void
    {
        $viewer = User::factory()->create(['role' => User::ROLE_VIEWER]);

        $this->actingAs($viewer)->from(route('triage.index'))->post(route('triage.store'), ['title' => 'Export is slow', 'description' => 'Takes minutes', 'priority' => 'high'])
            ->assertRedirect(route('triage.index'));

        $item = TriageItem::sole();
        $this->assertSame($viewer->id, $item->reporter_id);

        $this->get(route('triage.index'))->assertOk()
            ->assertSee('id="triage"', false)
            ->assertSee('Export is slow')
            ->assertDontSee('Waiting for a decision');
    }

    public function test_a_request_is_not_a_task_until_accepted(): void
    {
        $this->item();

        $this->assertSame(0, Task::count());
        $this->actingAs($this->admin)->get(route('board.index'))->assertDontSee('Login is broken');
    }

    public function test_a_developer_sends_to_their_own_team(): void
    {
        $other = Team::create(['name' => 'Other', 'color' => '#e11d48']);

        $this->actingAs($this->dev)->post(route('triage.store'), ['title' => 'A']);
        $this->assertSame($this->team->id, TriageItem::sole()->team_id);

        $this->post(route('triage.store'), ['title' => 'B', 'team_id' => $other->id])->assertSessionHasErrors('team_id');
    }

    public function test_the_team_lead_or_org_leads_hear_about_a_new_request_never_the_sender(): void
    {
        Notification::fake();
        $cto = User::factory()->create(['role' => User::ROLE_CTO]);

        $this->actingAs($this->dev)->post(route('triage.store'), ['title' => 'For the team']);
        Notification::assertSentTo($this->lead, TriageNotification::class);
        Notification::assertNotSentTo($this->admin, TriageNotification::class);
        Notification::assertNotSentTo($this->dev, TriageNotification::class);

        Notification::fake();
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->admin)->post(route('triage.store'), ['title' => 'No team']);
        Notification::assertSentTo($cto, TriageNotification::class);
        Notification::assertNotSentTo($this->admin, TriageNotification::class);
        Notification::assertNotSentTo($this->lead, TriageNotification::class);
    }

    public function test_leads_see_the_queue_for_what_they_may_decide_oldest_first(): void
    {
        $other = Team::create(['name' => 'Other', 'color' => '#e11d48']);
        $this->travelTo('2026-11-01 09:00:00');
        $this->item(['title' => 'Older mine']);
        $this->travelTo('2026-11-02 09:00:00');
        $this->item(['title' => 'Newer no team', 'team_id' => null]);
        $this->item(['title' => 'Other teams', 'team_id' => $other->id]);

        $this->actingAs($this->lead)->get(route('triage.index'))->assertOk()
            ->assertSeeInOrder(['Older mine', 'Newer no team'])
            ->assertDontSee('Other teams');

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->admin)->get(route('triage.index'))->assertSee('Other teams');
    }

    public function test_accepting_creates_a_task_filed_by_the_reporter(): void
    {
        Notification::fake();
        $bug = Label::where('name', 'Bug')->first();
        $item = $this->item(['description' => 'Details', 'label_id' => $bug->id, 'priority' => 'low']);

        $this->actingAs($this->lead)->from(route('triage.index'))->post(route('triage.accept', $item), [
            'team_id' => $this->team->id, 'assignee_id' => $this->dev->id, 'priority' => 'urgent', 'due_date' => '2026-12-01',
        ])->assertRedirect(route('triage.index'));

        $task = Task::sole();
        $this->assertSame('Login is broken', $task->title);
        $this->assertSame('Details', $task->description);
        $this->assertSame($this->dev->id, $task->created_by);
        $this->assertSame($this->dev->id, $task->assignee_id);
        $this->assertSame('urgent', $task->priority);
        $this->assertSame('todo', $task->status);
        $this->assertSame([$bug->id], $task->labels->pluck('id')->all());

        $item->refresh();
        $this->assertSame(TriageItem::ACCEPTED, $item->status);
        $this->assertSame($task->id, $item->task_id);
        $this->assertSame($this->lead->id, $item->decided_by);
        Notification::assertSentTo($this->dev, TriageNotification::class, fn ($n) => $n->kind === 'request_accepted');
    }

    public function test_the_reporter_sees_the_outcome_and_can_open_the_task(): void
    {
        $item = $this->item();
        $this->actingAs($this->lead)->post(route('triage.accept', $item), []);

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->dev)->get(route('triage.index'))->assertOk()
            ->assertSee('Accepted')
            ->assertSee(route('tasks.show', Task::sole()), false);
    }

    public function test_declining_needs_a_reason_and_the_reporter_hears_it(): void
    {
        Notification::fake();
        $item = $this->item();

        $this->actingAs($this->lead)->post(route('triage.decline', $item), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->assertTrue($item->fresh()->isPending());

        $this->post(route('triage.decline', $item), ['reason' => 'Duplicate of an existing bug'])->assertRedirect();

        $this->assertSame(TriageItem::DECLINED, $item->fresh()->status);
        $this->assertSame(0, Task::count());
        Notification::assertSentTo($this->dev, TriageNotification::class, fn ($n) => $n->kind === 'request_declined');
    }

    public function test_a_decision_is_final(): void
    {
        $item = $this->item();
        $this->actingAs($this->lead)->post(route('triage.decline', $item), ['reason' => 'No']);

        $this->post(route('triage.accept', $item), [])->assertStatus(409);
        $this->post(route('triage.decline', $item), ['reason' => 'Again'])->assertStatus(409);

        $this->assertSame(0, Task::count());
    }

    /** @return array<string, array{0: string}> */
    public static function nonLeads(): array
    {
        return ['developer' => [User::ROLE_DEVELOPER], 'qa' => [User::ROLE_QA], 'viewer' => [User::ROLE_VIEWER]];
    }

    #[DataProvider('nonLeads')]
    public function test_only_leads_decide(string $role): void
    {
        $user = User::factory()->create(['role' => $role]);
        $item = $this->item();

        $this->actingAs($user)->post(route('triage.accept', $item), [])->assertForbidden();
        $this->post(route('triage.decline', $item), ['reason' => 'No'])->assertForbidden();

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($user);
        $this->postJson("/api/v1/requests/{$item->id}/accept", [])->assertForbidden();
        $this->getJson('/api/v1/requests')->assertOk()->assertJsonCount(0, 'data.queue');

        $this->assertTrue($item->fresh()->isPending());
    }

    public function test_another_teams_lead_cannot_decide(): void
    {
        $item = $this->item();
        $otherLead = User::factory()->create(['role' => User::ROLE_TEAM_LEAD]);

        $this->actingAs($otherLead)->post(route('triage.accept', $item), [])->assertForbidden();
    }

    public function test_the_assignee_must_suit_the_release_and_be_active(): void
    {
        $project = Project::create(['name' => 'P', 'color' => '#4f46e5']);
        $release = Release::create(['project_id' => $project->id, 'team_id' => $this->team->id, 'name' => 'R', 'year' => 2026, 'quarter' => 4, 'start_date' => '2026-11-01', 'end_date' => '2026-12-31']);
        $outsider = User::factory()->create();
        $gone = User::factory()->create(['deactivated_at' => now()]);
        $item = $this->item();

        $this->actingAs($this->admin);
        $this->post(route('triage.accept', $item), ['release_id' => $release->id, 'assignee_id' => $outsider->id])->assertSessionHasErrors('assignee_id');
        $this->post(route('triage.accept', $item), ['assignee_id' => $gone->id])->assertSessionHasErrors('assignee_id');
        $this->assertSame(0, Task::count());

        $this->post(route('triage.accept', $item), ['release_id' => $release->id, 'assignee_id' => $this->dev->id])->assertSessionHasNoErrors();
        $this->assertSame($release->id, Task::sole()->release_id);
    }

    public function test_long_waits_are_flagged(): void
    {
        $this->travelTo('2026-11-01 09:00:00');
        $item = $this->item();
        $this->travelTo('2026-11-02 09:00:00');
        $this->assertFalse($item->isWaitingTooLong());

        $this->travelTo('2026-11-04 09:00:00');
        $this->assertTrue($item->isWaitingTooLong());
        $this->actingAs($this->lead)->get(route('triage.index'))->assertSee('Waiting 3 days');

        $item->forceFill(['status' => TriageItem::DECLINED])->save();
        $this->assertFalse($item->isWaitingTooLong());
    }

    public function test_opening_a_request_notification_lands_on_requests(): void
    {
        $this->actingAs($this->dev)->post(route('triage.store'), ['title' => 'Hello lead']);

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->lead);
        $id = $this->lead->notifications()->first()->id;

        $this->get(route('notifications.index'))->assertOk()->assertSee('sent a request', false);
        $this->get(route('notifications.open', $id))->assertRedirect(route('triage.index'));
    }

    public function test_the_api_mirrors_the_web(): void
    {
        Sanctum::actingAs($this->dev);
        $id = $this->postJson('/api/v1/requests', ['title' => 'From a client', 'priority' => 'low'])->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.team_id', $this->team->id)
            ->json('data.id');
        $this->getJson('/api/v1/requests')->assertOk()->assertJsonPath('data.mine.0.id', $id);
        $this->postJson('/api/v1/requests', [])->assertStatus(422);

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($this->lead);
        $this->getJson('/api/v1/requests')->assertOk()->assertJsonPath('data.queue.0.id', $id);
        $task = $this->postJson("/api/v1/requests/{$id}/accept", ['priority' => 'high'])->assertOk()
            ->assertJsonPath('data.status', 'accepted')
            ->json('data.task_id');
        $this->assertSame('high', Task::find($task)->priority);
        $this->postJson("/api/v1/requests/{$id}/decline", ['reason' => 'x'])->assertStatus(409);

        $second = $this->item();
        $this->postJson("/api/v1/requests/{$second->id}/decline", ['reason' => 'Not now'])->assertOk()
            ->assertJsonPath('data.decline_reason', 'Not now');
    }
}
