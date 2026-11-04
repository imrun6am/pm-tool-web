<?php

namespace Tests\Feature;

use App\Models\RecurringTask;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Services\RecurringTaskService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Rules that create a normal task each time they come round.
 */
class RecurringTaskTest extends TestCase
{
    use RefreshDatabase;

    private User $lead;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-02 06:00:00'); // a Monday
        $this->lead = User::factory()->create(['role' => User::ROLE_TEAM_LEAD]);
    }

    private function rule(array $attrs = []): RecurringTask
    {
        return RecurringTask::create($attrs + ['title' => 'Weekly report', 'frequency' => 'weekly', 'next_run_on' => '2026-11-02', 'created_by' => $this->lead->id]);
    }

    private function run_(): int
    {
        return app(RecurringTaskService::class)->runDue();
    }

    public function test_a_lead_adds_a_rule_on_the_web(): void
    {
        $team = Team::create(['name' => 'Core', 'color' => '#0891b2']);

        $this->actingAs($this->lead)->from(route('recurring-tasks.index'))->post(route('recurring-tasks.store'), [
            'title' => 'Backup check', 'frequency' => 'daily', 'next_run_on' => '2026-11-03', 'team_id' => $team->id,
            'assignee_id' => $this->lead->id, 'priority' => 'low', 'estimate' => '30m', 'due_in_days' => 1,
        ])->assertRedirect(route('recurring-tasks.index'));

        $rule = RecurringTask::sole();
        $this->assertSame(30, $rule->estimate_minutes);
        $this->assertSame($this->lead->id, $rule->created_by);

        $this->get(route('recurring-tasks.index'))->assertOk()
            ->assertSee('id="recurring"', false)
            ->assertSee('Backup check')
            ->assertSee('Every working day');
    }

    public function test_a_run_creates_a_normal_task_and_moves_on(): void
    {
        $team = Team::create(['name' => 'Core', 'color' => '#0891b2']);
        $person = User::factory()->create();
        $rule = $this->rule(['team_id' => $team->id, 'assignee_id' => $person->id, 'priority' => 'high', 'estimate_minutes' => 60, 'due_in_days' => 2, 'description' => 'Details']);

        $this->assertSame(1, $this->run_());

        $task = Task::sole();
        $this->assertSame('Weekly report', $task->title);
        $this->assertSame('Details', $task->description);
        $this->assertSame($team->id, $task->team_id);
        $this->assertSame($person->id, $task->assignee_id);
        $this->assertSame('high', $task->priority);
        $this->assertSame(60, $task->estimate_minutes);
        $this->assertSame('2026-11-04', $task->due_date->toDateString());
        $this->assertSame($this->lead->id, $task->created_by);
        $this->assertSame('2026-11-09', $rule->fresh()->next_run_on->toDateString());
        $this->assertSame('2026-11-02', $rule->fresh()->last_run_on->toDateString());
    }

    public function test_running_twice_in_a_day_makes_one_task(): void
    {
        $this->rule();

        $this->run_();
        $this->assertSame(0, $this->run_());

        $this->assertSame(1, Task::count());
    }

    public function test_a_rule_not_yet_due_or_paused_does_nothing(): void
    {
        $this->rule(['next_run_on' => '2026-11-03']);
        $this->rule(['is_active' => false]);

        $this->assertSame(0, $this->run_());
    }

    public function test_a_long_gap_makes_one_task_not_one_per_missed_run(): void
    {
        $rule = $this->rule(['next_run_on' => '2026-10-05']);

        $this->assertSame(1, $this->run_());
        $this->assertSame('2026-11-09', $rule->fresh()->next_run_on->toDateString());
    }

    public function test_daily_rules_skip_the_weekend(): void
    {
        $this->travelTo('2026-11-06 06:00:00'); // Friday
        $rule = $this->rule(['frequency' => 'daily', 'next_run_on' => '2026-11-06']);

        $this->run_();

        $this->assertSame('2026-11-09', $rule->fresh()->next_run_on->toDateString());
    }

    public function test_the_weekend_follows_config(): void
    {
        config(['planning.weekend_days' => ['friday', 'saturday']]);
        $this->travelTo('2026-11-05 06:00:00'); // Thursday
        $rule = $this->rule(['frequency' => 'daily', 'next_run_on' => '2026-11-05']);

        $this->run_();

        $this->assertSame('2026-11-08', $rule->fresh()->next_run_on->toDateString());
    }

    public function test_monthly_rules_keep_their_day_through_short_months(): void
    {
        $this->travelTo('2027-01-31 06:00:00');
        $rule = $this->rule(['frequency' => 'monthly', 'next_run_on' => '2027-01-31', 'anchor_day' => 31]);

        $this->run_();
        $this->assertSame('2027-02-28', $rule->fresh()->next_run_on->toDateString());

        $this->travelTo('2027-02-28 06:00:00');
        $this->run_();
        $this->assertSame('2027-03-31', $rule->fresh()->next_run_on->toDateString());
    }

    public function test_a_deactivated_assignee_gets_no_new_work(): void
    {
        $gone = User::factory()->create(['deactivated_at' => now()]);
        $this->rule(['assignee_id' => $gone->id]);

        $this->run_();

        $this->assertNull(Task::sole()->assignee_id);
    }

    public function test_pausing_and_resuming_skips_what_was_missed(): void
    {
        $rule = $this->rule(['next_run_on' => '2026-11-09']);
        $this->actingAs($this->lead);

        $this->put(route('recurring-tasks.update', $rule), ['is_active' => 0])->assertRedirect();
        $this->assertFalse($rule->fresh()->is_active);

        $this->travelTo('2026-12-01 06:00:00');
        $this->put(route('recurring-tasks.update', $rule), ['is_active' => 1])->assertRedirect();

        $this->assertTrue($rule->fresh()->is_active);
        $this->assertSame('2026-12-07', $rule->fresh()->next_run_on->toDateString());
        $this->assertSame(0, $this->run_());
    }

    public function test_removing_a_rule_keeps_the_tasks_it_made(): void
    {
        $rule = $this->rule();
        $this->run_();

        $this->actingAs($this->lead)->delete(route('recurring-tasks.destroy', $rule))->assertRedirect();

        $this->assertSame(0, RecurringTask::count());
        $this->assertSame(1, Task::count());
    }

    public function test_the_command_runs_and_is_scheduled(): void
    {
        $this->rule();

        Artisan::call('tasks:recur');
        $this->assertSame(1, Task::count());

        $this->assertTrue(collect(app(Schedule::class)->events())->contains(fn ($e) => str_contains($e->command ?? '', 'tasks:recur')));
    }

    /** @return array<string, array{0: array<string, mixed>, 1: string}> */
    public static function invalid(): array
    {
        return [
            'no title' => [['frequency' => 'weekly', 'next_run_on' => '2026-11-03'], 'title'],
            'bad frequency' => [['title' => 'X', 'frequency' => 'hourly', 'next_run_on' => '2026-11-03'], 'frequency'],
            'past date' => [['title' => 'X', 'frequency' => 'weekly', 'next_run_on' => '2026-11-01'], 'next_run_on'],
            'bad estimate' => [['title' => 'X', 'frequency' => 'weekly', 'next_run_on' => '2026-11-03', 'estimate' => 'soon'], 'estimate_minutes'],
            'bad priority' => [['title' => 'X', 'frequency' => 'weekly', 'next_run_on' => '2026-11-03', 'priority' => 'whenever'], 'priority'],
        ];
    }

    #[DataProvider('invalid')]
    public function test_bad_rules_are_refused(array $payload, string $field): void
    {
        Sanctum::actingAs($this->lead);

        $this->postJson('/api/v1/recurring-tasks', $payload)->assertStatus(422)->assertJsonValidationErrors($field);
    }

    public function test_a_deactivated_person_cannot_be_chosen(): void
    {
        $gone = User::factory()->create(['deactivated_at' => now()]);
        Sanctum::actingAs($this->lead);

        $this->postJson('/api/v1/recurring-tasks', ['title' => 'X', 'frequency' => 'weekly', 'next_run_on' => '2026-11-03', 'assignee_id' => $gone->id])
            ->assertStatus(422)->assertJsonValidationErrors('assignee_id');
    }

    /** @return array<string, array{0: string}> */
    public static function nonLeads(): array
    {
        return ['developer' => [User::ROLE_DEVELOPER], 'qa' => [User::ROLE_QA], 'viewer' => [User::ROLE_VIEWER]];
    }

    #[DataProvider('nonLeads')]
    public function test_only_leads_manage_rules(string $role): void
    {
        $user = User::factory()->create(['role' => $role]);
        $rule = $this->rule();

        $this->actingAs($user)->get(route('recurring-tasks.index'))->assertForbidden();
        $this->post(route('recurring-tasks.store'), ['title' => 'X', 'frequency' => 'weekly', 'next_run_on' => '2026-11-03'])->assertForbidden();
        $this->delete(route('recurring-tasks.destroy', $rule))->assertForbidden();

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/recurring-tasks')->assertForbidden();
    }

    public function test_the_api_mirrors_the_web(): void
    {
        Sanctum::actingAs($this->lead);

        $id = $this->postJson('/api/v1/recurring-tasks', ['title' => 'From a client', 'frequency' => 'monthly', 'next_run_on' => '2026-11-15'])->assertCreated()
            ->assertJsonPath('data.frequency', 'monthly')
            ->assertJsonPath('data.is_active', true)
            ->json('data.id');
        $this->assertSame(15, RecurringTask::find($id)->anchor_day);

        $this->getJson('/api/v1/recurring-tasks')->assertOk()->assertJsonPath('data.0.id', $id);
        $this->putJson("/api/v1/recurring-tasks/{$id}", ['is_active' => false])->assertOk()->assertJsonPath('data.is_active', false);
        $this->deleteJson("/api/v1/recurring-tasks/{$id}")->assertOk();
    }
}
