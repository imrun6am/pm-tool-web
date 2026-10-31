<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Release;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReleasePlanningTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function viewer(): User
    {
        return User::factory()->create(['role' => User::ROLE_VIEWER]);
    }

    private function payload(Project $project, Team $team, string $start, string $end, string $name = 'Release X'): array
    {
        return [
            'project_id' => $project->id,
            'team_id' => $team->id,
            'name' => $name,
            'year' => 2026,
            'quarter' => 3,
            'start_date' => $start,
            'end_date' => $end,
            'phases' => [
                'development' => ['start' => $start, 'end' => $start],
                'qa' => ['start' => $start, 'end' => $end],
                'retest' => ['start' => $end, 'end' => $end],
                'release' => ['start' => $end, 'end' => $end],
            ],
        ];
    }

    public function test_release_can_be_created_with_off_days(): void
    {
        $project = Project::create(['name' => 'P', 'color' => '#4f46e5']);
        $team = Team::create(['name' => 'T', 'color' => '#0891b2']);

        $payload = $this->payload($project, $team, '2026-07-10', '2026-07-30');
        $payload['off_days'] = [
            ['date' => '2026-07-14', 'reason' => 'Holiday'],
            ['date' => '2026-07-18', 'reason' => ''],
        ];

        $this->actingAs($this->admin())->post(route('releases.store'), $payload)->assertRedirect();

        $release = Release::first();
        $this->assertSame(2, $release->offDays()->count());
        $this->assertSame('Holiday', $release->offDays()->whereDate('date', '2026-07-14')->value('reason'));
    }

    public function test_off_day_outside_window_is_rejected(): void
    {
        $project = Project::create(['name' => 'P', 'color' => '#4f46e5']);
        $team = Team::create(['name' => 'T', 'color' => '#0891b2']);

        $payload = $this->payload($project, $team, '2026-07-10', '2026-07-30');
        $payload['off_days'] = [['date' => '2026-08-15', 'reason' => 'Nope']];

        $this->actingAs($this->admin())->post(route('releases.store'), $payload)
            ->assertSessionHasErrors('off_days.0.date');
        $this->assertSame(0, Release::count());
    }

    public function test_editing_release_syncs_off_days(): void
    {
        $project = Project::create(['name' => 'P', 'color' => '#4f46e5']);
        $team = Team::create(['name' => 'T', 'color' => '#0891b2']);
        $this->actingAs($this->admin())->post(route('releases.store'),
            array_merge($this->payload($project, $team, '2026-07-10', '2026-07-30'), [
                'off_days' => [['date' => '2026-07-14', 'reason' => 'Holiday']],
            ]));
        $release = Release::first();

        // Replace the off-day set on update.
        $this->actingAs($this->admin())->put(route('releases.update', $release),
            array_merge($this->payload($project, $team, '2026-07-10', '2026-07-30'), [
                'off_days' => [['date' => '2026-07-20', 'reason' => 'Team offsite']],
            ]))->assertRedirect();

        $this->assertSame(1, $release->offDays()->count());
        $this->assertNotNull($release->offDays()->whereDate('date', '2026-07-20')->first());
        $this->assertNull($release->offDays()->whereDate('date', '2026-07-14')->first());
    }

    public function test_admin_can_create_a_release_with_four_phases(): void
    {
        $project = Project::create(['name' => 'P', 'color' => '#4f46e5']);
        $team = Team::create(['name' => 'T', 'color' => '#0891b2']);

        $this->actingAs($this->admin())
            ->post(route('releases.store'), $this->payload($project, $team, '2026-07-10', '2026-07-30'))
            ->assertRedirect();

        $release = Release::first();
        $this->assertNotNull($release);
        $this->assertCount(4, $release->phases);
        $this->assertSame('development', $release->phases->first()->phase);
    }

    public function test_overlapping_same_team_release_saves_with_a_warning(): void
    {
        $project = Project::create(['name' => 'P', 'color' => '#4f46e5']);
        $team = Team::create(['name' => 'T', 'color' => '#0891b2']);

        // Existing booking Jul 10–30.
        $this->actingAs($this->admin())
            ->post(route('releases.store'), $this->payload($project, $team, '2026-07-10', '2026-07-30', 'First'));

        // New booking Jul 20–Aug 5 overlaps → should still save but flash a warning.
        $response = $this->actingAs($this->admin())
            ->post(route('releases.store'), $this->payload($project, $team, '2026-07-20', '2026-08-05', 'Second'));

        $response->assertSessionHas('overlap_warning');
        $this->assertSame(2, Release::count());
    }

    public function test_non_overlapping_release_saves_without_a_warning(): void
    {
        $project = Project::create(['name' => 'P', 'color' => '#4f46e5']);
        $team = Team::create(['name' => 'T', 'color' => '#0891b2']);

        $this->actingAs($this->admin())
            ->post(route('releases.store'), $this->payload($project, $team, '2026-07-10', '2026-07-30', 'First'));

        // Next window starts Aug 1 — no overlap.
        $response = $this->actingAs($this->admin())
            ->post(route('releases.store'), $this->payload($project, $team, '2026-08-01', '2026-08-20', 'Second'));

        $response->assertSessionMissing('overlap_warning');
    }

    public function test_phase_outside_window_is_rejected(): void
    {
        $project = Project::create(['name' => 'P', 'color' => '#4f46e5']);
        $team = Team::create(['name' => 'T', 'color' => '#0891b2']);

        $payload = $this->payload($project, $team, '2026-07-10', '2026-07-30');
        $payload['phases']['development']['start'] = '2026-07-01'; // before window

        $this->actingAs($this->admin())
            ->post(route('releases.store'), $payload)
            ->assertSessionHasErrors('phases.development.start');

        $this->assertSame(0, Release::count());
    }

    public function test_viewer_cannot_create_a_release(): void
    {
        $project = Project::create(['name' => 'P', 'color' => '#4f46e5']);
        $team = Team::create(['name' => 'T', 'color' => '#0891b2']);

        $this->actingAs($this->viewer())
            ->post(route('releases.store'), $this->payload($project, $team, '2026-07-10', '2026-07-30'))
            ->assertForbidden();

        $this->assertSame(0, Release::count());
    }

    public function test_viewer_can_view_the_dashboard(): void
    {
        $this->actingAs($this->viewer())
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_dashboard_defaults_to_current_quarter_but_all_quarters_is_selectable(): void
    {
        $q = (int) now()->quarter;

        // Fresh visit → current quarter preselected.
        $this->actingAs($this->viewer())->get(route('dashboard'))
            ->assertOk()
            ->assertSee('value="'.$q.'" selected', false);

        // Explicit "All quarters" (empty param) → no quarter selected.
        $this->actingAs($this->viewer())->get(route('dashboard', ['quarter' => '']))
            ->assertOk()
            ->assertDontSee('value="'.$q.'" selected', false);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }
}
