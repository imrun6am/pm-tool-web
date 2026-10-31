<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Release;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardLayoutTest extends TestCase
{
    use RefreshDatabase;

    private const TODAY_MARKER = '">Today</span>';

    private function release(Team $team, string $name, Carbon $start, Carbon $end): Release
    {
        $project = Project::firstOrCreate(['name' => 'Checkout'], ['color' => '#4f46e5']);

        return Release::create([
            'project_id' => $project->id, 'team_id' => $team->id, 'name' => $name,
            'year' => $start->year, 'quarter' => $start->quarter,
            'start_date' => $start->toDateString(), 'end_date' => $end->toDateString(),
        ]);
    }

    public function test_timeline_comes_before_the_insights(): void
    {
        $team = Team::create(['name' => 'Alpha', 'color' => '#0891b2']);
        $this->release($team, 'Current work', today()->firstOfQuarter(), today()->firstOfQuarter()->addDays(20));

        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))->get(route('dashboard'))
            ->assertOk()
            ->assertSeeInOrder(['id="timeline"', 'Current work', 'Delivery progress', 'Release load', 'Team workload'], false);
    }

    public function test_header_shows_the_period_and_its_range(): void
    {
        $this->actingAs(User::factory()->create())->get(route('dashboard', ['year' => 2026, 'quarter' => 4]))
            ->assertOk()
            ->assertSee('Q4 2026 · Oct 1 – Dec 31, 2026');
    }

    public function test_today_marker_only_inside_the_visible_range(): void
    {
        $team = Team::create(['name' => 'Alpha', 'color' => '#0891b2']);
        $this->release($team, 'Current work', today()->firstOfQuarter(), today()->lastOfQuarter());
        $this->release($team, 'Old work', Carbon::create(2020, 1, 6), Carbon::create(2020, 2, 20));
        $viewer = User::factory()->create();

        $this->actingAs($viewer)->get(route('dashboard'))
            ->assertOk()->assertSee(self::TODAY_MARKER, false);

        $this->actingAs($viewer)->get(route('dashboard', ['year' => 2020, 'quarter' => 1]))
            ->assertOk()->assertSee('Old work')->assertDontSee(self::TODAY_MARKER, false);
    }

    public function test_conflict_cell_links_to_the_timeline(): void
    {
        $team = Team::create(['name' => 'Alpha', 'color' => '#0891b2']);
        $start = today()->firstOfQuarter();
        $this->release($team, 'First', $start, $start->copy()->addDays(20));
        $this->release($team, 'Second', $start->copy()->addDays(10), $start->copy()->addDays(30));

        $this->actingAs(User::factory()->create())->get(route('dashboard'))
            ->assertOk()
            ->assertSee('href="#timeline"', false)
            ->assertSee('double-booked');
    }

    public function test_member_summary_counts_and_flags_overdue_work(): void
    {
        $dev = User::factory()->create(['role' => User::ROLE_DEVELOPER, 'name' => 'Devin Dev']);
        $team = Team::create(['name' => 'Alpha', 'color' => '#0891b2']);
        $release = $this->release($team, 'Current work', today()->subDays(10), today()->addDays(20));
        Task::create(['release_id' => $release->id, 'title' => 'Late one', 'status' => 'todo',
            'assignee_id' => $dev->id, 'due_date' => today()->subDays(2)->toDateString()]);
        Task::create(['release_id' => $release->id, 'title' => 'On time', 'status' => 'in_progress',
            'assignee_id' => $dev->id, 'due_date' => today()->addDays(3)->toDateString()]);

        $html = $this->actingAs($dev)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('My day')
            ->assertSee(', Devin · ')
            ->assertSee('Overdue · '.today()->subDays(2)->format('M j'))
            ->assertSee('past their due date')
            ->getContent();

        $this->assertMatchesRegularExpression('#Open tasks</span></p>\s*<p class="stat-value">2</p>#', $html);
        $this->assertMatchesRegularExpression('#bg-danger-50/50[^>]*>.*?Overdue.*?<p class="stat-value text-danger-700">1</p>#s', $html);
    }

    public function test_member_without_overdue_work_stays_neutral(): void
    {
        $dev = User::factory()->create(['role' => User::ROLE_DEVELOPER]);

        $this->actingAs($dev)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('nothing late')
            ->assertSee("You're all caught up.")
            ->assertDontSee('bg-danger-50/50', false);
    }
}
