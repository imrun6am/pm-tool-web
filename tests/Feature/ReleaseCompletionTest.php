<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Release;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReleaseCompletionTest extends TestCase
{
    use RefreshDatabase;

    private function release(array $attrs = []): Release
    {
        $project = Project::create(['name' => 'P', 'color' => '#4f46e5']);
        $team = Team::create(['name' => 'T', 'color' => '#0891b2']);

        return Release::create(array_merge([
            'project_id' => $project->id, 'team_id' => $team->id, 'name' => 'R',
            'year' => 2026, 'quarter' => 3, 'start_date' => '2026-07-10', 'end_date' => '2026-07-30',
        ], $attrs));
    }

    public function test_admin_can_complete_a_release_with_notes(): void
    {
        $release = $this->release();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->post(route('releases.complete', $release), ['completion_notes' => '**Done**'])
            ->assertRedirect();

        $release->refresh();
        $this->assertTrue($release->isComplete());
        $this->assertSame($admin->id, $release->completed_by);
        $this->assertStringContainsString('<strong>Done</strong>', (string) $release->renderedCompletionNotes());
    }

    public function test_completion_notes_strip_unsafe_html(): void
    {
        $release = $this->release();
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
            ->post(route('releases.complete', $release), ['completion_notes' => "Ok <script>alert(1)</script>"]);

        $this->assertStringNotContainsString('<script>', (string) $release->refresh()->renderedCompletionNotes());
    }

    public function test_non_admin_cannot_complete_or_reopen(): void
    {
        $release = $this->release();
        $this->actingAs(User::factory()->create(['role' => User::ROLE_DEVELOPER]))
            ->post(route('releases.complete', $release))->assertForbidden();

        $release->update(['completed_at' => now()]);
        $this->actingAs(User::factory()->create(['role' => User::ROLE_QA]))
            ->post(route('releases.reopen', $release))->assertForbidden();
    }

    public function test_admin_can_reopen(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $release = $this->release(['completed_at' => now(), 'completed_by' => $admin->id]);

        $this->actingAs($admin)->post(route('releases.reopen', $release))->assertRedirect();

        $this->assertFalse($release->refresh()->isComplete());
    }

    public function test_completed_release_hidden_from_dashboard(): void
    {
        $ongoing = $this->release(['name' => 'Ongoing One']);
        $done = $this->release(['name' => 'Done One', 'completed_at' => now()]);

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard', ['year' => 2026]))
            ->assertOk()
            ->assertSee('Ongoing One')
            ->assertDontSee('Done One');
    }

    public function test_completed_release_does_not_cause_overlap_warning(): void
    {
        $project = Project::create(['name' => 'P', 'color' => '#4f46e5']);
        $team = Team::create(['name' => 'T', 'color' => '#0891b2']);
        // A completed release occupying Jul 10-30.
        Release::create([
            'project_id' => $project->id, 'team_id' => $team->id, 'name' => 'Old',
            'year' => 2026, 'quarter' => 3, 'start_date' => '2026-07-10', 'end_date' => '2026-07-30',
            'completed_at' => now(),
        ]);

        $payload = [
            'project_id' => $project->id, 'team_id' => $team->id, 'name' => 'New',
            'year' => 2026, 'quarter' => 3, 'start_date' => '2026-07-20', 'end_date' => '2026-08-05',
            'phases' => [
                'development' => ['start' => '2026-07-20', 'end' => '2026-07-20'],
                'qa' => ['start' => '2026-07-20', 'end' => '2026-08-05'],
                'retest' => ['start' => '2026-08-05', 'end' => '2026-08-05'],
                'release' => ['start' => '2026-08-05', 'end' => '2026-08-05'],
            ],
        ];

        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
            ->post(route('releases.store'), $payload)
            ->assertSessionMissing('overlap_warning');
    }

    public function test_releases_index_filters_by_status(): void
    {
        $ongoing = $this->release(['name' => 'Ongoing One']);
        $done = $this->release(['name' => 'Done One', 'completed_at' => now()]);
        $user = User::factory()->create();

        // Assert on the row links — ongoing release *names* legitimately appear
        // on every page via the quick-links drawer's release dropdown.
        $this->actingAs($user)->get(route('releases.index', ['status' => 'completed']))
            ->assertOk()
            ->assertSee('/releases/'.$done->id, false)
            ->assertDontSee('/releases/'.$ongoing->id, false);

        $this->actingAs($user)->get(route('releases.index', ['status' => 'active']))
            ->assertOk()
            ->assertSee('/releases/'.$ongoing->id, false)
            ->assertDontSee('/releases/'.$done->id, false);
    }
}
