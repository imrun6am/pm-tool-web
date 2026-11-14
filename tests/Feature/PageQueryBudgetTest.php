<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Release;
use App\Models\ReleaseOffDay;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Services\BoardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Pages and API reads that list many rows must not ask the database once per
 * row, and long finished lists are cut so a page stays quick as work piles up.
 */
class PageQueryBudgetTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->team = Team::create(['name' => 'Core', 'color' => '#0891b2']);
    }

    private function release(string $name = 'R1'): Release
    {
        $project = Project::firstOrCreate(['name' => 'Checkout'], ['color' => '#4f46e5']);

        return Release::create([
            'project_id' => $project->id, 'team_id' => $this->team->id, 'name' => $name,
            'year' => 2026, 'quarter' => 3, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31',
        ]);
    }

    /** Runs $call and returns how many queries matched $needle. */
    private function countQueries(string $needle, callable $call): int
    {
        $count = 0;
        DB::listen(function ($q) use (&$count, $needle) {
            if (str_contains($q->sql, $needle)) {
                $count++;
            }
        });
        $call();

        return $count;
    }

    public function test_the_release_api_reads_blocked_status_once_for_all_its_tasks(): void
    {
        $release = $this->release();
        foreach (range(1, 6) as $i) {
            $task = Task::create(['release_id' => $release->id, 'team_id' => $this->team->id, 'title' => "Task {$i}", 'status' => 'todo', 'position' => $i]);
            Task::create(['release_id' => $release->id, 'team_id' => $this->team->id, 'parent_id' => $task->id, 'title' => "Step {$i}", 'status' => 'todo', 'position' => 0]);
        }
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

        $queries = $this->countQueries('task_dependencies', fn () => $this->getJson("/api/v1/releases/{$release->id}")->assertOk());

        // Once for the tasks and once for their subtasks, however many there are.
        $this->assertSame(2, $queries);
    }

    public function test_the_release_list_counts_off_days_without_a_query_per_release(): void
    {
        foreach (range(1, 4) as $i) {
            $release = $this->release("R{$i}");
            ReleaseOffDay::create(['release_id' => $release->id, 'date' => '2026-07-0'.$i, 'reason' => 'Holiday']);
        }
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

        $response = null;
        $queries = $this->countQueries('release_off_days', function () use (&$response) {
            $response = $this->getJson('/api/v1/releases')->assertOk();
        });

        $this->assertSame(1, $queries);
        $this->assertSame([1, 1, 1, 1], array_column($response->json('data'), 'off_day_count'));
    }

    public function test_the_release_page_lists_open_tasks_and_only_the_latest_finished_ones(): void
    {
        $release = $this->release();
        $open = Task::create(['release_id' => $release->id, 'team_id' => $this->team->id, 'title' => 'Still open', 'status' => 'todo', 'position' => 0]);
        $finished = [];
        foreach (range(1, BoardService::FINISHED_LIMIT + 3) as $i) {
            $this->travelTo(now()->addMinute());
            $finished[] = Task::create(['release_id' => $release->id, 'team_id' => $this->team->id, 'title' => sprintf('Finished %03d', $i), 'status' => 'done', 'position' => $i]);
        }
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $page = $this->actingAs($admin)->get(route('releases.show', $release))->assertOk();
        $page->assertSee('Still open')
            ->assertSee(end($finished)->title)
            ->assertDontSee($finished[0]->title)
            ->assertSee('3 older finished tasks not shown.');

        $this->actingAs($admin)->get(route('releases.show', [$release, 'tasks' => 'all']))->assertOk()
            ->assertSee($finished[0]->title)
            ->assertDontSee('older finished');
        $this->assertNotNull($open);
    }

    public function test_team_scoping_sees_a_team_change_on_the_next_request(): void
    {
        $other = Team::create(['name' => 'Growth', 'color' => '#e11d48']);
        $dev = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $this->team->members()->attach($dev->id);
        $theirs = Task::create(['team_id' => $other->id, 'title' => 'Growth work', 'status' => 'todo', 'position' => 0]);

        $this->actingAs($dev)->get(route('tasks.show', $theirs))->assertForbidden();

        $other->members()->attach($dev->id);

        $this->actingAs($dev)->get(route('tasks.show', $theirs))->assertOk();
    }
}
