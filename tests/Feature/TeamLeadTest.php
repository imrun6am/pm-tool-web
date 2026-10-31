<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamLeadTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    public function test_admin_can_assign_any_user_as_lead_regardless_of_role(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $team = Team::create(['name' => 'T', 'color' => '#0891b2']);
        // A plain developer — role is deliberately not "team_lead".
        $person = $this->user(User::ROLE_DEVELOPER);

        $this->actingAs($admin)
            ->put(route('teams.lead.update', $team), ['team_lead_id' => $person->id])
            ->assertRedirect();

        $this->assertSame($person->id, $team->fresh()->team_lead_id);
    }

    public function test_admin_can_clear_the_team_lead(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $person = $this->user(User::ROLE_QA);
        $team = Team::create(['name' => 'T', 'color' => '#0891b2', 'team_lead_id' => $person->id]);

        $this->actingAs($admin)
            ->put(route('teams.lead.update', $team), ['team_lead_id' => ''])
            ->assertRedirect();

        $this->assertNull($team->fresh()->team_lead_id);
    }

    public function test_team_lead_can_be_set_when_creating_a_team(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $person = $this->user(User::ROLE_VIEWER);

        $this->actingAs($admin)->post(route('teams.store'), [
            'name' => 'Alpha',
            'color' => '#0891b2',
            'team_lead_id' => $person->id,
        ])->assertRedirect();

        $this->assertSame($person->id, Team::firstWhere('name', 'Alpha')->team_lead_id);
    }

    public function test_lead_must_be_an_active_user(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $team = Team::create(['name' => 'T', 'color' => '#0891b2']);
        $deactivated = User::factory()->create(['deactivated_at' => now()]);

        $this->actingAs($admin)
            ->put(route('teams.lead.update', $team), ['team_lead_id' => $deactivated->id])
            ->assertSessionHasErrors('team_lead_id');

        $this->assertNull($team->fresh()->team_lead_id);
    }

    public function test_non_lead_cannot_assign_the_team_lead(): void
    {
        $actor = $this->user(User::ROLE_DEVELOPER);
        $team = Team::create(['name' => 'T', 'color' => '#0891b2']);
        $person = $this->user(User::ROLE_DEVELOPER);

        $this->actingAs($actor)
            ->put(route('teams.lead.update', $team), ['team_lead_id' => $person->id])
            ->assertForbidden();

        $this->assertNull($team->fresh()->team_lead_id);
    }

    public function test_any_lead_can_assign_the_team_lead(): void
    {
        // The leadership tier shares one access level, so a CTO / tech lead /
        // team lead may assign the lead just as an admin can.
        foreach ([User::ROLE_CTO, User::ROLE_TECH_LEAD, User::ROLE_TEAM_LEAD] as $role) {
            $actor = $this->user($role);
            $team = Team::create(['name' => "T-$role", 'color' => '#0891b2']);
            $person = $this->user(User::ROLE_DEVELOPER);

            $this->actingAs($actor)
                ->put(route('teams.lead.update', $team), ['team_lead_id' => $person->id])
                ->assertRedirect();

            $this->assertSame($person->id, $team->fresh()->team_lead_id);
        }
    }

    public function test_team_pages_render_with_the_lead_picker(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $lead = $this->user(User::ROLE_DEVELOPER);
        $team = Team::create(['name' => 'Alpha', 'color' => '#0891b2', 'team_lead_id' => $lead->id]);

        $this->actingAs($admin)->get(route('teams.create'))->assertOk()->assertSee('Team lead');
        $this->actingAs($admin)->get(route('teams.edit', $team))->assertOk()->assertSee('Team lead');
        $this->actingAs($admin)->get(route('teams.show', $team))->assertOk()
            ->assertSee('Team lead')->assertSee($lead->name);
    }

    public function test_deleting_the_lead_user_nulls_the_teams_lead(): void
    {
        $person = $this->user(User::ROLE_DEVELOPER);
        $team = Team::create(['name' => 'T', 'color' => '#0891b2', 'team_lead_id' => $person->id]);

        $person->delete();

        $this->assertNull($team->fresh()->team_lead_id);
    }
}
