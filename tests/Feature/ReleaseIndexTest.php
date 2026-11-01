<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Release;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The releases index's filters, sorting and flags, the release page's KPI
 * numbers, and the create form's pre-selection.
 */
class ReleaseIndexTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = Project::create(['name' => 'Shop', 'color' => '#4f46e5']);
        $this->team = Team::create(['name' => 'Core', 'color' => '#0891b2']);
    }

    private function release(string $name, string $start, string $end, array $extra = []): Release
    {
        return Release::create([
            'project_id' => $this->project->id, 'team_id' => $this->team->id, 'name' => $name,
            'year' => (int) substr($start, 0, 4), 'quarter' => (int) ceil(((int) substr($start, 5, 2)) / 3),
            'start_date' => $start, 'end_date' => $end,
        ] + $extra);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    /** @return array<int, string> release names in the order the index lists them */
    private function names(array $query): array
    {
        return $this->actingAs($this->admin())->get(route('releases.index', $query))
            ->assertOk()->viewData('releases')->pluck('name')->all();
    }

    public function test_status_filters_follow_the_release_state(): void
    {
        $this->release('Past', today()->subDays(20)->toDateString(), today()->subDays(5)->toDateString());
        $this->release('Now', today()->subDays(2)->toDateString(), today()->addDays(5)->toDateString());
        $this->release('Later', today()->addDays(3)->toDateString(), today()->addDays(9)->toDateString());
        $this->release('Shipped', today()->subDays(9)->toDateString(), today()->addDays(2)->toDateString(), ['completed_at' => now()]);

        $this->assertSame(['Now'], $this->names(['status' => 'in_progress']));
        $this->assertSame(['Later'], $this->names(['status' => 'upcoming']));
        $this->assertSame(['Past'], $this->names(['status' => 'overdue']));
        $this->assertSame(['Shipped'], $this->names(['status' => 'completed']));
        $this->assertEqualsCanonicalizing(['Past', 'Now', 'Later'], $this->names(['status' => 'active']));

        $counts = $this->actingAs($this->admin())->get(route('releases.index', ['status' => 'upcoming']))
            ->viewData('stateCounts');
        $this->assertSame(['active' => 1, 'upcoming' => 1, 'overdue' => 1, 'completed' => 1], $counts);
    }

    public function test_search_quarter_and_sort(): void
    {
        $this->release('Beta checkout', '2026-02-01', '2026-02-20');
        $this->release('alpha search', '2026-05-01', '2026-05-20');
        $this->release('Gamma checkout', '2026-08-01', '2026-08-20');

        $this->assertEqualsCanonicalizing(['Beta checkout', 'Gamma checkout'], $this->names(['search' => 'checkout']));
        $this->assertSame(['alpha search'], $this->names(['quarter' => 2]));
        $this->assertSame(['alpha search', 'Beta checkout', 'Gamma checkout'], $this->names(['sort' => 'name']));
        $this->assertSame(['Gamma checkout', 'alpha search', 'Beta checkout'], $this->names(['sort' => '-start']));
        $this->assertSame(['Beta checkout', 'alpha search', 'Gamma checkout'], $this->names(['sort' => 'end']));

        // Unknown values are ignored rather than erroring.
        $this->assertCount(3, $this->names(['sort' => 'bogus', 'status' => 'nope', 'quarter' => 9]));
    }

    public function test_progress_sort_ignores_archived_tasks(): void
    {
        $half = $this->release('Half', '2026-07-01', '2026-07-20');
        $full = $this->release('Full', '2026-08-01', '2026-08-20');
        $this->release('Empty', '2026-09-01', '2026-09-20');

        Task::create(['release_id' => $half->id, 'title' => 'a', 'status' => 'done']);
        Task::create(['release_id' => $half->id, 'title' => 'b', 'status' => 'todo']);
        Task::create(['release_id' => $full->id, 'title' => 'c', 'status' => 'done']);
        Task::create(['release_id' => $full->id, 'title' => 'd', 'status' => 'archive']); // not counted

        $this->assertSame(['Full', 'Half', 'Empty'], $this->names(['sort' => '-progress']));

        $releases = $this->actingAs($this->admin())->get(route('releases.index'))->viewData('releases');
        $this->assertSame(['done' => 1, 'total' => 1], $releases->firstWhere('name', 'Full')->taskProgress());
    }

    public function test_double_booked_releases_are_flagged(): void
    {
        $a = $this->release('A', '2026-07-01', '2026-07-20');
        $b = $this->release('B', '2026-07-15', '2026-07-30');
        $c = $this->release('C', '2026-09-01', '2026-09-20');

        $conflicts = $this->actingAs($this->admin())->get(route('releases.index'))
            ->assertOk()->assertSee('Overlap')->viewData('conflicts');

        $this->assertTrue($conflicts[$a->id] ?? false);
        $this->assertTrue($conflicts[$b->id] ?? false);
        $this->assertArrayNotHasKey($c->id, $conflicts);

        // A clash is still flagged when its partner is filtered out of the list.
        $conflicts = $this->actingAs($this->admin())->get(route('releases.index', ['search' => 'A']))->viewData('conflicts');
        $this->assertTrue($conflicts[$a->id] ?? false);
    }

    public function test_release_page_counts_open_and_overdue_tasks(): void
    {
        $release = $this->release('Checkout', today()->subDays(3)->toDateString(), today()->addDays(10)->toDateString());
        Task::create(['release_id' => $release->id, 'title' => 'done', 'status' => 'done', 'due_date' => today()->subDay()]);
        Task::create(['release_id' => $release->id, 'title' => 'late', 'status' => 'todo', 'due_date' => today()->subDay()]);
        Task::create(['release_id' => $release->id, 'title' => 'today', 'status' => 'in_progress', 'due_date' => today()]);
        Task::create(['release_id' => $release->id, 'title' => 'gone', 'status' => 'archive', 'due_date' => today()->subDay()]);

        $this->actingAs($this->admin())->get(route('releases.show', $release))
            ->assertOk()
            ->assertViewHas('taskStats', ['total' => 3, 'done' => 1, 'open' => 2, 'overdue' => 1])
            ->assertSee('class="stat-strip', false);
    }

    public function test_create_preselects_an_active_project_and_team_from_the_query(): void
    {
        $archived = Team::create(['name' => 'Old', 'color' => '#64748b', 'archived_at' => now()]);

        $release = $this->actingAs($this->admin())
            ->get(route('releases.create', ['project_id' => $this->project->id, 'team_id' => $this->team->id]))
            ->assertOk()->viewData('release');
        $this->assertSame($this->project->id, $release->project_id);
        $this->assertSame($this->team->id, $release->team_id);

        $release = $this->actingAs($this->admin())
            ->get(route('releases.create', ['project_id' => 999, 'team_id' => $archived->id]))
            ->assertOk()->viewData('release');
        $this->assertNull($release->project_id);
        $this->assertNull($release->team_id);
    }

    public function test_edit_form_renders_with_inline_errors(): void
    {
        $release = $this->release('Checkout', '2026-07-01', '2026-07-20');
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('releases.edit', $release))->assertOk()
            ->assertSee('x-data="releaseForm(', false)
            ->assertSee('name="phases[development][start]"', false)
            ->assertSee('id="delete-release"', false);

        $this->actingAs($admin)->from(route('releases.edit', $release))
            ->put(route('releases.update', $release), ['name' => ''])
            ->assertRedirect(route('releases.edit', $release));

        $this->actingAs($admin)->get(route('releases.edit', $release))->assertOk()
            ->assertSee('aria-invalid="true" aria-describedby="name-error"', false)
            ->assertSee('id="name-error"', false)
            ->assertSee('id="phases.development.start-error"', false);
    }
}
