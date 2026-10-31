<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Release;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin, CTO, tech lead, and team lead form a single leadership tier that
 * shares one identical access level. These tests pin that parity so the roles
 * can never silently drift apart.
 */
class LeadAccessParityTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    private function releasePayload(Project $project, Team $team, string $name): array
    {
        $start = '2026-07-10';
        $end = '2026-07-30';

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

    public function test_the_four_lead_roles_are_exactly_admin_cto_tech_lead_team_lead(): void
    {
        $this->assertEqualsCanonicalizing(
            [User::ROLE_ADMIN, User::ROLE_CTO, User::ROLE_TECH_LEAD, User::ROLE_TEAM_LEAD],
            User::LEAD_ROLES,
        );
    }

    public function test_every_lead_role_can_manage_projects_teams_releases_and_users(): void
    {
        foreach (User::LEAD_ROLES as $i => $role) {
            $actor = $this->user($role);

            // Projects
            $this->actingAs($actor)
                ->post(route('projects.store'), ['name' => "Proj $role", 'color' => '#4f46e5'])
                ->assertRedirect();
            $this->assertDatabaseHas('projects', ['name' => "Proj $role"]);

            // Teams
            $this->actingAs($actor)
                ->post(route('teams.store'), ['name' => "Team $role", 'color' => '#0891b2'])
                ->assertRedirect();
            $team = Team::firstWhere('name', "Team $role");
            $this->assertNotNull($team);

            // Releases
            $project = Project::firstWhere('name', "Proj $role");
            $this->actingAs($actor)
                ->post(route('releases.store'), $this->releasePayload($project, $team, "Rel $role"))
                ->assertRedirect();
            $this->assertNotNull(Release::firstWhere('name', "Rel $role"));

            // User directory
            $this->actingAs($actor)->get(route('users.index'))->assertOk();
        }
    }

    public function test_non_leads_cannot_manage_projects_or_teams(): void
    {
        foreach ([User::ROLE_DEVELOPER, User::ROLE_QA, User::ROLE_VIEWER] as $role) {
            $actor = $this->user($role);

            $this->actingAs($actor)
                ->post(route('projects.store'), ['name' => "Nope $role", 'color' => '#4f46e5'])
                ->assertForbidden();

            $this->actingAs($actor)
                ->post(route('teams.store'), ['name' => "Nope $role", 'color' => '#0891b2'])
                ->assertForbidden();
        }

        $this->assertSame(0, Project::count());
        $this->assertSame(0, Team::count());
    }
}
