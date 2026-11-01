<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Notifications\TaskDueSoon;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The notification inbox (openspec/changes/notification-inbox): who is told
 * about what, the web bell and page, and the API.
 */
class NotificationInboxTest extends TestCase
{
    use RefreshDatabase;

    private Team $core;

    private User $lead;

    private User $dev;

    private User $qa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->core = Team::create(['name' => 'Core', 'color' => '#0891b2']);
        $this->lead = User::factory()->create(['role' => User::ROLE_TEAM_LEAD, 'name' => 'Lee Lead']);
        $this->dev = User::factory()->create(['role' => User::ROLE_DEVELOPER, 'name' => 'Dana Dev']);
        $this->qa = User::factory()->create(['role' => User::ROLE_QA, 'name' => 'Quinn QA']);
        $this->core->members()->attach([$this->dev->id, $this->qa->id]);
    }

    private function task(array $attrs = []): Task
    {
        return Task::create($attrs + [
            'title' => 'Fix login bug', 'status' => 'todo', 'position' => 0,
            'team_id' => $this->core->id, 'created_by' => $this->lead->id,
        ]);
    }

    private function comment(Task $task, User $author, string $body = 'Can you take a look?'): Comment
    {
        $this->actingAs($author);

        return $task->comments()->create(['user_id' => $author->id, 'body' => $body]);
    }

    public function test_assigning_a_task_notifies_the_assignee(): void
    {
        $this->actingAs($this->lead);
        $task = $this->task(['assignee_id' => $this->dev->id]);

        $notification = $this->dev->notifications()->sole();
        $this->assertSame('assigned', $notification->data['kind']);
        $this->assertSame($task->id, $notification->data['task_id']);
        $this->assertSame('Lee Lead assigned you “Fix login bug”', $notification->data['message']);
        $this->assertSame(0, $this->lead->notifications()->count());
    }

    public function test_reassignment_notifies_only_the_new_assignee(): void
    {
        $this->actingAs($this->lead);
        $task = $this->task(['assignee_id' => $this->dev->id]);
        $task->update(['assignee_id' => $this->qa->id]);
        $task->update(['title' => 'Fix login bug (again)']);

        $this->assertSame(1, $this->dev->notifications()->count());
        $this->assertSame(1, $this->qa->notifications()->count());
    }

    public function test_self_assignment_is_silent(): void
    {
        $this->actingAs($this->dev);
        $this->task(['assignee_id' => $this->dev->id]);

        $this->assertSame(0, $this->dev->notifications()->count());
    }

    public function test_form_input_ids_are_handled_like_integers(): void
    {
        // Leads assigning a developer through a form (string ids) still reach them…
        $this->actingAs($this->lead)
            ->post(route('tasks.quick.store'), ['title' => 'From a form', 'assignee_id' => (string) $this->dev->id])
            ->assertRedirect();
        $this->assertSame(1, $this->dev->notifications()->count());

        // …and assigning yourself through a form stays silent.
        $this->actingAs($this->dev)
            ->post(route('tasks.quick.store'), ['title' => 'Mine', 'assignee_id' => (string) $this->dev->id])
            ->assertRedirect();
        $this->assertSame(1, $this->dev->notifications()->count());
    }

    public function test_writes_without_a_signed_in_user_send_nothing(): void
    {
        $this->task(['assignee_id' => $this->dev->id]);

        $this->assertSame(0, $this->dev->notifications()->count());
    }

    public function test_a_comment_notifies_assignee_creator_and_earlier_commenters_but_not_its_author(): void
    {
        $viewer = User::factory()->create(['role' => User::ROLE_VIEWER]);
        $task = $this->task(['assignee_id' => $this->dev->id]);
        $this->comment($task, $viewer, 'First!');
        $this->dev->notifications()->delete();
        $this->lead->notifications()->delete();

        $this->comment($task, $this->qa, '<p>Retested — still <b>failing</b> on Safari.</p>');

        foreach ([$this->dev, $this->lead, $viewer] as $user) {
            $notification = $user->notifications()->sole();
            $this->assertSame('commented', $notification->data['kind']);
            $this->assertSame('Retested — still failing on Safari.', $notification->data['excerpt']);
        }
        $this->assertSame(0, $this->qa->notifications()->count());
    }

    public function test_people_who_cannot_see_the_task_are_not_notified(): void
    {
        $growth = Team::create(['name' => 'Growth', 'color' => '#e11d48']);
        $outsider = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $growth->members()->attach($outsider);
        $task = $this->task(['assignee_id' => $this->dev->id]);
        $this->comment($task, $outsider);   // e.g. before moving teams
        $outsider->notifications()->delete();

        $this->comment($task, $this->qa);

        $this->assertSame(0, $outsider->notifications()->count());
    }

    public function test_deactivated_users_are_not_notified(): void
    {
        $this->dev->update(['deactivated_at' => now()]);
        $this->actingAs($this->lead);
        $this->task(['assignee_id' => $this->dev->id]);

        $this->assertSame(0, $this->dev->notifications()->count());
    }

    public function test_due_reminders_are_sent_once_for_tomorrow_and_the_first_day_late(): void
    {
        $this->travelTo('2026-11-10 08:00');
        $tomorrow = $this->task(['title' => 'Ship docs', 'assignee_id' => $this->dev->id, 'due_date' => '2026-11-11']);
        $late = $this->task(['title' => 'Fix CI', 'assignee_id' => $this->dev->id, 'due_date' => '2026-11-09']);
        $this->task(['title' => 'Long overdue', 'assignee_id' => $this->dev->id, 'due_date' => '2026-11-01']);
        $this->task(['title' => 'Done already', 'assignee_id' => $this->dev->id, 'due_date' => '2026-11-11', 'status' => 'done']);

        $this->artisan('notifications:due-reminders')->assertSuccessful();
        $this->artisan('notifications:due-reminders')->assertSuccessful();

        $reminders = $this->dev->notifications()->where('type', TaskDueSoon::class)->get();
        $this->assertCount(2, $reminders);
        $this->assertEqualsCanonicalizing(
            [[$tomorrow->id, 'due_tomorrow'], [$late->id, 'overdue']],
            $reminders->map(fn ($n) => [$n->data['task_id'], $n->data['kind']])->all(),
        );
        $this->assertSame('“Fix CI” was due yesterday', $reminders->firstWhere('data.kind', 'overdue')->data['message']);
    }

    public function test_the_bell_shows_the_unread_count_and_latest_notifications(): void
    {
        $this->actingAs($this->lead);
        $this->task(['assignee_id' => $this->dev->id]);

        $this->actingAs($this->dev)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('x-data="notificationBell(1,', false)
            ->assertSee('Lee Lead assigned you “Fix login bug”');

        $this->getJson(route('notifications.unread-count'))->assertExactJson(['count' => 1]);
    }

    public function test_opening_a_notification_marks_it_read_and_goes_to_the_task(): void
    {
        $this->actingAs($this->lead);
        $task = $this->task(['assignee_id' => $this->dev->id]);
        $notification = $this->dev->notifications()->sole();

        $this->actingAs($this->dev)->get(route('notifications.open', $notification->id))
            ->assertRedirect(route('tasks.show', $task));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_someone_elses_notification_is_not_found(): void
    {
        $this->actingAs($this->lead);
        $this->task(['assignee_id' => $this->dev->id]);
        $notification = $this->dev->notifications()->sole();

        $this->actingAs($this->qa)->get(route('notifications.open', $notification->id))->assertNotFound();
        Sanctum::actingAs($this->qa);
        $this->postJson(route('api-v1.notifications.read', $notification->id))->assertNotFound();
        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_notifications_page_filters_and_marks_all_read(): void
    {
        $this->actingAs($this->lead);
        $first = $this->task(['assignee_id' => $this->dev->id]);
        $this->task(['title' => 'Second', 'assignee_id' => $this->dev->id]);
        $this->dev->notifications()->where('data->task_id', $first->id)->update(['read_at' => now()]);

        $this->actingAs($this->dev)->get(route('notifications.index', ['unread' => 1]))
            ->assertOk()
            ->assertSee('Lee Lead assigned you “Second”')
            ->assertViewHas('notifications', fn ($page) => $page->total() === 1);

        $this->post(route('notifications.read-all'))->assertRedirect();
        $this->assertSame(0, $this->dev->unreadNotifications()->count());

        $this->get(route('notifications.index', ['unread' => 1]))->assertOk()->assertSee('You’re all caught up');
    }

    public function test_api_lists_reads_and_matches_the_web_count(): void
    {
        $this->actingAs($this->lead);
        $task = $this->task(['assignee_id' => $this->dev->id]);
        $this->travel(1)->minutes();
        $this->task(['title' => 'Second', 'assignee_id' => $this->dev->id]);
        $webCount = app(NotificationService::class)->unreadCount($this->dev);

        Sanctum::actingAs($this->dev);
        $list = $this->getJson(route('api-v1.notifications.index', ['unread' => 1]))
            ->assertOk()
            ->assertJsonPath('meta.unread_count', $webCount)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.1.kind', 'assigned')
            ->assertJsonPath('data.1.task.id', $task->id)
            ->assertJsonPath('data.1.actor.name', 'Lee Lead');

        $this->postJson(route('api-v1.notifications.read', $list->json('data.0.id')))
            ->assertOk()->assertJsonPath('data.is_read', true);
        $this->postJson(route('api-v1.notifications.read-all'))
            ->assertOk()->assertJsonPath('message', '1 notification marked read.');
        $this->getJson(route('api-v1.notifications.index'))->assertJsonPath('meta.unread_count', 0);
    }
}
