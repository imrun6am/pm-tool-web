<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Release;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamMembershipTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    private function payload(Project $project, Team $team, array $members = []): array
    {
        $start = '2026-07-10';
        $end = '2026-07-30';

        return [
            'project_id' => $project->id,
            'team_id' => $team->id,
            'name' => 'Release X',
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
            'members' => $members,
        ];
    }

    public function test_team_lead_can_add_and_remove_team_members(): void
    {
        $lead = $this->user(User::ROLE_TEAM_LEAD);
        $team = Team::create(['name' => 'T', 'color' => '#0891b2']);
        $member = $this->user(User::ROLE_DEVELOPER);

        $this->actingAs($lead)->post(route('teams.members.store', $team), ['user_id' => $member->id])
            ->assertRedirect();
        $this->assertTrue($team->members()->where('users.id', $member->id)->exists());

        $this->actingAs($lead)->delete(route('teams.members.destroy', [$team, $member]))
            ->assertRedirect();
        $this->assertFalse($team->members()->where('users.id', $member->id)->exists());
    }

    public function test_admin_can_add_team_members(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $team = Team::create(['name' => 'T', 'color' => '#0891b2']);
        $member = $this->user(User::ROLE_QA);

        $this->actingAs($admin)->post(route('teams.members.store', $team), ['user_id' => $member->id])
            ->assertRedirect();
        $this->assertTrue($team->members()->where('users.id', $member->id)->exists());
    }

    public function test_viewer_cannot_manage_team_members(): void
    {
        $viewer = $this->user(User::ROLE_VIEWER);
        $team = Team::create(['name' => 'T', 'color' => '#0891b2']);
        $member = $this->user(User::ROLE_DEVELOPER);

        $this->actingAs($viewer)->post(route('teams.members.store', $team), ['user_id' => $member->id])
            ->assertForbidden();
        $this->assertSame(0, $team->members()->count());
    }

    public function test_adding_the_same_member_twice_is_idempotent(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $team = Team::create(['name' => 'T', 'color' => '#0891b2']);
        $member = $this->user(User::ROLE_DEVELOPER);

        $this->actingAs($admin)->post(route('teams.members.store', $team), ['user_id' => $member->id]);
        $this->actingAs($admin)->post(route('teams.members.store', $team), ['user_id' => $member->id]);

        $this->assertSame(1, $team->members()->count());
    }

    public function test_team_lead_can_create_release_with_team_members(): void
    {
        $lead = $this->user(User::ROLE_TEAM_LEAD);
        $project = Project::create(['name' => 'P', 'color' => '#4f46e5']);
        $team = Team::create(['name' => 'T', 'color' => '#0891b2']);
        $a = $this->user(User::ROLE_DEVELOPER);
        $b = $this->user(User::ROLE_QA);
        $team->members()->attach([$a->id, $b->id]);

        $this->actingAs($lead)
            ->post(route('releases.store'), $this->payload($project, $team, [$a->id, $b->id]))
            ->assertRedirect();

        $release = Release::first();
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $release->members()->pluck('users.id')->all());
    }

    public function test_release_members_must_belong_to_the_owning_team(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $project = Project::create(['name' => 'P', 'color' => '#4f46e5']);
        $team = Team::create(['name' => 'T', 'color' => '#0891b2']);
        $member = $this->user(User::ROLE_DEVELOPER);
        $outsider = $this->user(User::ROLE_DEVELOPER); // not on the team

        $team->members()->attach($member->id);

        $this->actingAs($admin)
            ->post(route('releases.store'), $this->payload($project, $team, [$member->id, $outsider->id]))
            ->assertSessionHasErrors('members');
        $this->assertSame(0, Release::count());
    }

    public function test_editing_release_syncs_members(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $project = Project::create(['name' => 'P', 'color' => '#4f46e5']);
        $team = Team::create(['name' => 'T', 'color' => '#0891b2']);
        $a = $this->user(User::ROLE_DEVELOPER);
        $b = $this->user(User::ROLE_QA);
        $team->members()->attach([$a->id, $b->id]);

        $this->actingAs($admin)->post(route('releases.store'), $this->payload($project, $team, [$a->id]));
        $release = Release::first();
        $this->assertEqualsCanonicalizing([$a->id], $release->members()->pluck('users.id')->all());

        $this->actingAs($admin)->put(route('releases.update', $release), $this->payload($project, $team, [$b->id]))
            ->assertRedirect();
        $this->assertEqualsCanonicalizing([$b->id], $release->members()->pluck('users.id')->all());
    }

    public function test_developer_cannot_create_a_release(): void
    {
        $developer = $this->user(User::ROLE_DEVELOPER);
        $project = Project::create(['name' => 'P', 'color' => '#4f46e5']);
        $team = Team::create(['name' => 'T', 'color' => '#0891b2']);

        $this->actingAs($developer)->get(route('releases.create'))->assertForbidden();
        $this->actingAs($developer)->post(route('releases.store'), $this->payload($project, $team))
            ->assertForbidden();
    }
}
