<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Release;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Services\ProjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The redesigned Projects index (filters, sorting, roll-up cards) and detail
 * page (KPI strip, release timeline, teams involved).
 */
class ProjectPagesTest extends TestCase
{
    use RefreshDatabase;

    private User $lead;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lead = User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function release(Project $project, Team $team, string $name, int $startOffset, int $endOffset, bool $completed = false): Release
    {
        $start = today()->addDays($startOffset);

        return Release::create([
            'project_id' => $project->id, 'team_id' => $team->id, 'name' => $name,
            'year' => $start->year, 'quarter' => $start->quarter,
            'start_date' => $start->toDateString(), 'end_date' => today()->addDays($endOffset)->toDateString(),
            'completed_at' => $completed ? now() : null,
        ]);
    }

    public function test_index_cards_roll_up_releases_progress_teams_and_the_current_release(): void
    {
        $project = Project::create(['name' => 'Checkout', 'color' => '#4f46e5', 'description' => 'Payments and carts']);
        $alpha = Team::create(['name' => 'Alpha', 'color' => '#0891b2']);
        $beta = Team::create(['name' => 'Beta', 'color' => '#db2777']);

        $this->release($project, $alpha, 'Old one', -60, -40, completed: true);
        $current = $this->release($project, $alpha, 'Checkout v2', -5, 10);
        $this->release($project, $beta, 'Checkout v3', 20, 40);

        foreach (['todo', 'done', 'done', 'archive'] as $i => $status) {
            Task::create(['release_id' => $current->id, 'title' => "T{$i}", 'status' => $status]);
        }

        $summary = app(ProjectService::class)->cardSummaries(Project::withCount('releases')->get())[$project->id];
        $this->assertSame(2, $summary['ongoing']);
        $this->assertSame(1, $summary['completed']);
        // Archived tasks never count.
        $this->assertSame(['done' => 2, 'total' => 3], ['done' => $summary['done'], 'total' => $summary['total']]);
        $this->assertSame(['Alpha', 'Beta'], $summary['teams']->pluck('name')->all());
        $this->assertTrue($summary['next']->is($current));

        $this->actingAs($this->lead)->get(route('projects.index'))
            ->assertOk()
            ->assertSee('Payments and carts')
            ->assertSee('2 of 3 tasks done (67%)')
            ->assertSee('Current release')
            ->assertSee('Checkout v2')
            ->assertSeeInOrder(['Alpha', 'Beta'])
            ->assertSee('New project');
    }

    public function test_index_filters_by_search_and_status_and_keeps_them_in_the_url(): void
    {
        Project::create(['name' => 'Checkout', 'color' => '#4f46e5']);
        Project::create(['name' => 'Search', 'color' => '#4f46e5', 'archived_at' => now()]);

        $this->actingAs($this->lead)->get(route('projects.index', ['search' => 'check']))
            ->assertOk()
            ->assertSee('value="check"', false)
            ->assertSee('Checkout')
            ->assertDontSee('>Search</a>', false);

        $this->actingAs($this->lead)->get(route('projects.index', ['status' => 'archived']))
            ->assertOk()
            ->assertSee('<option value="archived" selected>', false)
            ->assertSee('>Search</a>', false)
            ->assertDontSee('>Checkout</a>', false);

        $this->actingAs($this->lead)->get(route('projects.index', ['search' => 'nothing-matches']))
            ->assertOk()
            ->assertSee('No projects match these filters.');
    }

    public function test_index_sorts_by_releases_and_progress_with_archived_last(): void
    {
        $team = Team::create(['name' => 'Alpha', 'color' => '#0891b2']);
        $quiet = Project::create(['name' => 'Aardvark', 'color' => '#4f46e5']);
        $busy = Project::create(['name' => 'Zebra', 'color' => '#4f46e5']);
        $shelved = Project::create(['name' => 'Mammoth', 'color' => '#4f46e5', 'archived_at' => now()]);

        $this->release($busy, $team, 'Z1', 0, 5);
        $this->release($busy, $team, 'Z2', 10, 15);
        $this->release($shelved, $team, 'M1', 0, 5);
        $q = $this->release($quiet, $team, 'A1', 0, 5);
        Task::create(['release_id' => $q->id, 'title' => 'Done', 'status' => 'done']);

        $names = fn (string $sort) => app(ProjectService::class)->filtered(['sort' => $sort])->pluck('name')->all();

        $this->assertSame(['Aardvark', 'Zebra', 'Mammoth'], $names('name'));
        $this->assertSame(['Zebra', 'Aardvark', 'Mammoth'], $names('releases'));
        $this->assertSame(['Aardvark', 'Zebra', 'Mammoth'], $names('progress'));
        $this->assertSame(['Aardvark', 'Zebra', 'Mammoth'], $names('bogus'));

        $this->actingAs($this->lead)->get(route('projects.index', ['sort' => 'releases']))
            ->assertOk()
            ->assertSee('<option value="releases" selected>', false)
            ->assertSeeInOrder(['Zebra', 'Aardvark', 'Mammoth']);
    }

    public function test_show_has_kpis_timeline_teams_and_flags_double_booking(): void
    {
        $project = Project::create(['name' => 'Checkout', 'color' => '#4f46e5']);
        $alpha = Team::create(['name' => 'Alpha', 'color' => '#0891b2']);
        $first = $this->release($project, $alpha, 'Checkout v2', -5, 10);
        $this->release($project, $alpha, 'Checkout v3', 5, 20);
        Task::create(['release_id' => $first->id, 'title' => 'Late', 'status' => 'todo', 'due_date' => today()->subDay()->toDateString()]);

        $this->actingAs($this->lead)->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('class="stat-strip', false)
            ->assertSee('Releases over time')
            ->assertSee('2 double-booked')
            ->assertSee('In progress')
            ->assertSee('Upcoming')
            ->assertSee('Teams involved')
            ->assertSee('href="'.route('teams.show', $alpha).'"', false)
            ->assertSee('Progress and status mix')
            ->assertSee('open past their due date')
            // A project with releases is archived, never deleted.
            ->assertSee('action="'.route('projects.archive', $project).'"', false)
            ->assertDontSee('action="'.route('projects.destroy', $project).'"', false);
    }

    public function test_show_handles_an_empty_and_an_archived_project(): void
    {
        $empty = Project::create(['name' => 'Empty', 'color' => '#4f46e5', 'archived_at' => now()]);

        $this->actingAs($this->lead)->get(route('projects.show', $empty))
            ->assertOk()
            ->assertSee('No releases in this project yet.')
            ->assertSee('This project is archived')
            ->assertSee('action="'.route('projects.restore', $empty).'"', false)
            ->assertSee('action="'.route('projects.destroy', $empty).'"', false);
    }

    public function test_edit_offers_delete_only_for_a_project_without_releases(): void
    {
        $empty = Project::create(['name' => 'Empty', 'color' => '#4f46e5']);
        $used = Project::create(['name' => 'Used', 'color' => '#4f46e5']);
        $this->release($used, Team::create(['name' => 'Alpha', 'color' => '#0891b2']), 'U1', 0, 5);

        $this->actingAs($this->lead)->get(route('projects.edit', $empty))
            ->assertOk()
            ->assertSee('id="delete-project"', false);

        $this->actingAs($this->lead)->get(route('projects.edit', $used))
            ->assertOk()
            ->assertSee('id="archive-project"', false)
            ->assertDontSee('id="delete-project"', false);
    }
}
