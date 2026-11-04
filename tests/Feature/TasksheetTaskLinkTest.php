<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\TaskTimeLog;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The tasksheet picks up from the board: open tasks can go straight into the
 * plan, and each row shows the hours logged that day.
 */
class TasksheetTaskLinkTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    private User $dev;

    protected function setUp(): void
    {
        parent::setUp();

        $this->team = Team::create(['name' => 'Core', 'color' => '#0891b2']);
        $this->dev = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $this->team->members()->attach($this->dev);
    }

    private function task(string $title, array $attrs = []): Task
    {
        return Task::create($attrs + ['title' => $title, 'status' => 'todo', 'position' => 0, 'assignee_id' => $this->dev->id, 'team_id' => $this->team->id]);
    }

    private function sheet(array $query = []): array
    {
        return ['team' => $this->team->id] + $query;
    }

    public function test_a_member_can_pick_their_open_tasks_for_the_plan_most_urgent_first(): void
    {
        $low = $this->task('Low one', ['priority' => 'low']);
        $urgent = $this->task('Urgent one', ['priority' => 'urgent']);
        $done = $this->task('Finished one', ['status' => 'done']);
        $theirs = $this->task('Someone elses', ['assignee_id' => User::factory()->create()->id]);

        $response = $this->actingAs($this->dev)->get(route('tasksheet.index', $this->sheet()))->assertOk();

        $response->assertSee('Add an open task to the plan')
            ->assertSeeInOrder([$urgent->reference().' Urgent one', $low->reference().' Low one'])
            ->assertDontSee('Finished one')
            ->assertDontSee('Someone elses');
    }

    public function test_a_task_already_in_the_plan_is_disabled_in_the_picker(): void
    {
        $task = $this->task('Planned');
        $this->actingAs($this->dev)->put(route('tasksheet.entries.upsert'), [
            'team_id' => $this->team->id, 'user_id' => $this->dev->id, 'date' => today()->toDateString(),
            'plan' => '<ul><li>'.$task->reference().' Planned</li></ul>',
        ])->assertRedirect();

        $this->get(route('tasksheet.index', $this->sheet()))->assertOk()
            ->assertSee('<option value="'.$task->id.'" disabled', false);
    }

    public function test_the_picker_stays_away_when_there_is_nothing_open(): void
    {
        $this->actingAs($this->dev)->get(route('tasksheet.index', $this->sheet()))->assertOk()
            ->assertDontSee('Add an open task to the plan');
    }

    public function test_a_lead_gets_each_rows_own_tasks(): void
    {
        $this->task('Devs task');
        $lead = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($lead)->get(route('tasksheet.index', $this->sheet()))->assertOk()->assertSee('Devs task');
    }

    public function test_a_teammate_does_not_get_someone_elses_open_tasks(): void
    {
        $this->task('Devs private work');
        $mate = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $this->team->members()->attach($mate);

        $this->actingAs($mate)->get(route('tasksheet.index', $this->sheet()))->assertOk()->assertDontSee('Devs private work');
    }

    public function test_rows_show_the_hours_logged_that_day(): void
    {
        $task = $this->task('Work');
        TaskTimeLog::create(['task_id' => $task->id, 'user_id' => $this->dev->id, 'logged_on' => today(), 'minutes' => 90]);
        TaskTimeLog::create(['task_id' => $task->id, 'user_id' => $this->dev->id, 'logged_on' => today(), 'minutes' => 30]);
        TaskTimeLog::create(['task_id' => $task->id, 'user_id' => $this->dev->id, 'logged_on' => today()->subDay(), 'minutes' => 300]);

        $this->actingAs($this->dev)->get(route('tasksheet.index', $this->sheet()))->assertOk()->assertSee('2h logged');
        $this->get(route('tasksheet.index', $this->sheet(['date' => today()->subDay()->toDateString()])))->assertOk()->assertSee('5h logged');

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($this->dev);
        $row = collect($this->getJson('/api/v1/tasksheet?team_id='.$this->team->id)->assertOk()->json('data.rows'))
            ->firstWhere('user.id', $this->dev->id);
        $this->assertSame(120, $row['logged_minutes']);
    }

    public function test_someone_who_logged_nothing_shows_no_hours_and_zero_on_the_api(): void
    {
        $this->actingAs($this->dev)->get(route('tasksheet.index', $this->sheet()))->assertOk()->assertDontSee('Time logged on tasks this day');

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($this->dev);
        $row = collect($this->getJson('/api/v1/tasksheet?team_id='.$this->team->id)->json('data.rows'))->firstWhere('user.id', $this->dev->id);
        $this->assertSame(0, $row['logged_minutes']);
    }

    public function test_a_viewer_does_not_get_hours(): void
    {
        $task = $this->task('Work');
        TaskTimeLog::create(['task_id' => $task->id, 'user_id' => $this->dev->id, 'logged_on' => today(), 'minutes' => 90]);
        $viewer = User::factory()->create(['role' => User::ROLE_VIEWER]);

        $this->actingAs($viewer)->get(route('tasksheet.index', $this->sheet()))->assertOk()->assertDontSee('1h 30m logged');

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($viewer);
        $row = collect($this->getJson('/api/v1/tasksheet?team_id='.$this->team->id)->assertOk()->json('data.rows'))->firstWhere('user.id', $this->dev->id);
        $this->assertArrayNotHasKey('logged_minutes', $row);
    }
}
