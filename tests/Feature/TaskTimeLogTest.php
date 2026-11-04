<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Task;
use App\Models\TaskTimeLog;
use App\Models\Team;
use App\Models\User;
use App\Support\Duration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * People log hours on tasks. Totals include subtasks, people change their own
 * entries for a week, and leads can correct anyone's.
 */
class TaskTimeLogTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    private User $dev;

    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-04 10:00:00');
        $this->team = Team::create(['name' => 'Core', 'color' => '#0891b2']);
        $this->dev = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $this->team->members()->attach($this->dev);
        $this->task = Task::create(['title' => 'Card', 'status' => 'todo', 'position' => 0, 'team_id' => $this->team->id]);
    }

    private function logged(array $attrs = []): TaskTimeLog
    {
        return TaskTimeLog::create($attrs + [
            'task_id' => $this->task->id, 'user_id' => $this->dev->id, 'logged_on' => '2026-11-04', 'minutes' => 60,
        ]);
    }

    /** @return array<string, array{0: string, 1: ?int}> */
    public static function durations(): array
    {
        return [
            'hours and minutes' => ['1h 30m', 90],
            'no space' => ['1h30m', 90],
            'hours' => ['2h', 120],
            'decimal hours' => ['1.5h', 90],
            'words' => ['2 hours 5 mins', 125],
            'minutes' => ['45m', 45],
            'clock' => ['1:30', 90],
            'bare number is hours' => ['1.5', 90],
            'capitals' => ['1H 30M', 90],
            'text' => ['soon', null],
            'empty' => ['', null],
            'bad clock' => ['1:75', null],
        ];
    }

    #[DataProvider('durations')]
    public function test_durations_are_read_as_people_type_them(string $input, ?int $minutes): void
    {
        $this->assertSame($minutes, Duration::parse($input));
    }

    public function test_durations_are_shown_as_people_read_them(): void
    {
        $this->assertSame('1h 30m', Duration::format(90));
        $this->assertSame('2h', Duration::format(120));
        $this->assertSame('45m', Duration::format(45));
        $this->assertSame('0m', Duration::format(0));
    }

    public function test_a_member_logs_time_on_the_web_for_themselves(): void
    {
        $this->actingAs($this->dev)
            ->from(route('tasks.show', $this->task))
            ->post(route('tasks.time-logs.store', $this->task), ['duration' => '1h 30m', 'logged_on' => '2026-11-03', 'note' => 'Fixing it'])
            ->assertRedirect(route('tasks.show', $this->task))
            ->assertSessionHas('success');

        $log = TaskTimeLog::sole();
        $this->assertSame($this->dev->id, $log->user_id);
        $this->assertSame(90, $log->minutes);
        $this->assertSame('2026-11-03', $log->logged_on->toDateString());
        $this->assertSame('Fixing it', $log->note);
    }

    public function test_someone_elses_user_id_is_ignored(): void
    {
        $other = User::factory()->create();

        $this->actingAs($this->dev)
            ->post(route('tasks.time-logs.store', $this->task), ['minutes' => 30, 'logged_on' => '2026-11-04', 'user_id' => $other->id]);

        $this->assertSame($this->dev->id, TaskTimeLog::sole()->user_id);
    }

    public function test_the_task_page_shows_the_total_entries_and_form(): void
    {
        $this->logged(['minutes' => 90, 'note' => 'A clear note']);

        $this->actingAs($this->dev)->get(route('tasks.show', $this->task))
            ->assertOk()
            ->assertSee('id="task-time"', false)
            ->assertSee('1h 30m')
            ->assertSee('A clear note')
            ->assertSee(route('tasks.time-logs.store', $this->task), false);
    }

    public function test_a_viewer_sees_only_the_total_on_the_task_page(): void
    {
        $this->logged(['minutes' => 90, 'note' => 'A clear note']);
        $viewer = User::factory()->create(['role' => User::ROLE_VIEWER]);

        $this->actingAs($viewer)->get(route('tasks.show', $this->task))
            ->assertOk()
            ->assertSee('1h 30m')
            ->assertDontSee('A clear note')
            ->assertDontSee(route('tasks.time-logs.store', $this->task), false);
    }

    public function test_totals_include_subtasks(): void
    {
        $subtask = Task::create(['title' => 'Part', 'status' => 'todo', 'position' => 0, 'parent_id' => $this->task->id, 'team_id' => $this->team->id]);
        $this->logged(['minutes' => 60]);
        $this->logged(['minutes' => 30, 'task_id' => $subtask->id]);

        Sanctum::actingAs($this->dev);

        $this->getJson("/api/v1/tasks/{$this->task->id}/time-logs")->assertOk()
            ->assertJsonPath('data.total_minutes', 90)
            ->assertJsonPath('data.own_minutes', 60)
            ->assertJsonPath('data.subtask_minutes', 30)
            ->assertJsonCount(2, 'data.entries');

        $this->getJson("/api/v1/tasks/{$subtask->id}/time-logs")->assertOk()
            ->assertJsonPath('data.total_minutes', 30);

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->dev)->get(route('tasks.show', $this->task))
            ->assertSee('Subtask: Part');
    }

    public function test_the_api_mirrors_the_web_for_logging_editing_and_deleting(): void
    {
        Sanctum::actingAs($this->dev);

        $id = $this->postJson("/api/v1/tasks/{$this->task->id}/time-logs", ['minutes' => 45, 'logged_on' => '2026-11-04'])
            ->assertCreated()
            ->assertJsonPath('data.minutes', 45)
            ->assertJsonPath('data.user_id', $this->dev->id)
            ->assertJsonPath('data.can_edit', true)
            ->json('data.id');

        $this->putJson("/api/v1/time-logs/{$id}", ['duration' => '2h', 'logged_on' => '2026-11-03', 'note' => 'Longer'])
            ->assertOk()
            ->assertJsonPath('data.minutes', 120)
            ->assertJsonPath('data.logged_on', '2026-11-03');

        $this->deleteJson("/api/v1/time-logs/{$id}")->assertOk();

        $this->assertDatabaseMissing('task_time_logs', ['id' => $id]);
    }

    public function test_a_viewer_gets_totals_without_entries_from_the_api(): void
    {
        $this->logged();
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_VIEWER]));

        $this->getJson("/api/v1/tasks/{$this->task->id}/time-logs")->assertOk()
            ->assertJsonPath('data.total_minutes', 60)
            ->assertJsonMissingPath('data.entries');
    }

    public function test_a_viewer_cannot_log_time(): void
    {
        $viewer = User::factory()->create(['role' => User::ROLE_VIEWER]);

        $this->actingAs($viewer)->post(route('tasks.time-logs.store', $this->task), ['minutes' => 30, 'logged_on' => '2026-11-04'])->assertForbidden();

        $this->assertSame(0, TaskTimeLog::count());
    }

    public function test_a_developer_on_another_team_is_kept_out(): void
    {
        $outsider = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $log = $this->logged();

        $this->actingAs($outsider)->post(route('tasks.time-logs.store', $this->task), ['minutes' => 30, 'logged_on' => '2026-11-04'])->assertForbidden();
        $this->put(route('time-logs.update', $log), ['minutes' => 30, 'logged_on' => '2026-11-04'])->assertForbidden();
        $this->delete(route('time-logs.destroy', $log))->assertForbidden();

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($outsider);
        $this->getJson("/api/v1/tasks/{$this->task->id}/time-logs")->assertForbidden();
        $this->postJson("/api/v1/tasks/{$this->task->id}/time-logs", ['minutes' => 30, 'logged_on' => '2026-11-04'])->assertForbidden();

        $this->assertSame(1, TaskTimeLog::count());
    }

    public function test_a_teammate_cannot_change_someone_elses_entry(): void
    {
        $teammate = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $this->team->members()->attach($teammate);
        $log = $this->logged();

        $this->actingAs($teammate)->put(route('time-logs.update', $log), ['minutes' => 30, 'logged_on' => '2026-11-04'])->assertForbidden();
        $this->delete(route('time-logs.destroy', $log))->assertForbidden();

        $this->assertSame(60, $log->fresh()->minutes);
    }

    public function test_people_change_their_own_entries_only_for_a_week(): void
    {
        $log = $this->logged();

        $this->travel(7)->days();
        $this->actingAs($this->dev)->put(route('time-logs.update', $log), ['minutes' => 30, 'logged_on' => '2026-11-04'])->assertRedirect();
        $this->assertSame(30, $log->fresh()->minutes);

        $this->travel(1)->days();
        $this->put(route('time-logs.update', $log), ['minutes' => 15, 'logged_on' => '2026-11-04'])->assertForbidden();
        $this->delete(route('time-logs.destroy', $log))->assertForbidden();
        $this->assertSame(30, $log->fresh()->minutes);
    }

    /** @return array<string, array{0: string}> */
    public static function orgLeads(): array
    {
        return [
            'admin' => [User::ROLE_ADMIN],
            'cto' => [User::ROLE_CTO],
            'tech lead' => [User::ROLE_TECH_LEAD],
        ];
    }

    #[DataProvider('orgLeads')]
    public function test_org_leads_can_correct_any_entry_at_any_age(string $role): void
    {
        $log = $this->logged();
        $this->travel(60)->days();

        $this->actingAs(User::factory()->create(['role' => $role]))
            ->put(route('time-logs.update', $log), ['minutes' => 30, 'logged_on' => '2026-11-04'])
            ->assertRedirect();

        $this->assertSame(30, $log->fresh()->minutes);
        $this->assertSame($this->dev->id, $log->fresh()->user_id);
    }

    public function test_a_team_lead_can_correct_only_their_own_teams_entries(): void
    {
        $lead = User::factory()->create(['role' => User::ROLE_TEAM_LEAD]);
        $otherLead = User::factory()->create(['role' => User::ROLE_TEAM_LEAD]);
        $this->team->update(['team_lead_id' => $lead->id]);
        $log = $this->logged();

        $this->actingAs($otherLead)->put(route('time-logs.update', $log), ['minutes' => 30, 'logged_on' => '2026-11-04'])->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->actingAs($lead)->put(route('time-logs.update', $log), ['minutes' => 30, 'logged_on' => '2026-11-04'])->assertRedirect();

        $this->assertSame(30, $log->fresh()->minutes);
    }

    /** @return array<string, array{0: array<string, mixed>, 1: string}> */
    public static function invalidEntries(): array
    {
        return [
            'unreadable time' => [['duration' => 'soon', 'logged_on' => '2026-11-04'], 'minutes'],
            'no time' => [['logged_on' => '2026-11-04'], 'minutes'],
            'zero' => [['minutes' => 0, 'logged_on' => '2026-11-04'], 'minutes'],
            'over a day' => [['minutes' => 1441, 'logged_on' => '2026-11-04'], 'minutes'],
            'future date' => [['minutes' => 30, 'logged_on' => '2026-11-05'], 'logged_on'],
            'no date' => [['minutes' => 30], 'logged_on'],
            'older than the window' => [['minutes' => 30, 'logged_on' => '2026-10-27'], 'logged_on'],
            'note too long' => [['minutes' => 30, 'logged_on' => '2026-11-04', 'note' => null], 'note'],
        ];
    }

    #[DataProvider('invalidEntries')]
    public function test_bad_entries_are_refused(array $payload, string $field): void
    {
        if (array_key_exists('note', $payload)) {
            $payload['note'] = str_repeat('x', 501);
        }

        Sanctum::actingAs($this->dev);

        $this->postJson("/api/v1/tasks/{$this->task->id}/time-logs", $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors($field);

        $this->assertSame(0, TaskTimeLog::count());
    }

    public function test_the_edge_of_the_backdating_window_is_allowed(): void
    {
        Sanctum::actingAs($this->dev);

        $this->postJson("/api/v1/tasks/{$this->task->id}/time-logs", ['minutes' => 30, 'logged_on' => '2026-10-28'])->assertCreated();
    }

    public function test_a_lead_can_backdate_further(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

        $this->postJson("/api/v1/tasks/{$this->task->id}/time-logs", ['minutes' => 30, 'logged_on' => '2026-09-01'])->assertCreated();
    }

    public function test_a_day_cannot_total_more_than_24_hours(): void
    {
        $this->logged(['minutes' => 1400]);
        Sanctum::actingAs($this->dev);

        $this->postJson("/api/v1/tasks/{$this->task->id}/time-logs", ['minutes' => 41, 'logged_on' => '2026-11-04'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('minutes');

        $this->postJson("/api/v1/tasks/{$this->task->id}/time-logs", ['minutes' => 40, 'logged_on' => '2026-11-04'])->assertCreated();
    }

    public function test_editing_an_entry_does_not_count_it_twice_towards_the_day(): void
    {
        $log = $this->logged(['minutes' => 1000]);
        Sanctum::actingAs($this->dev);

        $this->putJson("/api/v1/time-logs/{$log->id}", ['minutes' => 1200, 'logged_on' => '2026-11-04'])->assertOk();
    }

    public function test_entries_are_written_to_the_activity_log(): void
    {
        $this->actingAs($this->dev)->post(route('tasks.time-logs.store', $this->task), ['minutes' => 90, 'logged_on' => '2026-11-04']);

        $activity = Activity::where('subject_type', (new TaskTimeLog)->getMorphClass())->sole();
        $this->assertSame($this->dev->id, $activity->causer_id);
        $this->assertStringContainsString('1h 30m on Card', $activity->description);
    }

    public function test_purging_a_task_removes_its_time(): void
    {
        $log = $this->logged();

        $this->task->forceDelete();

        $this->assertDatabaseMissing('task_time_logs', ['id' => $log->id]);
    }

    public function test_guests_are_refused(): void
    {
        $log = $this->logged();

        $this->post(route('tasks.time-logs.store', $this->task), [])->assertRedirect(route('login'));
        $this->deleteJson("/api/v1/time-logs/{$log->id}")->assertUnauthorized();
    }
}
