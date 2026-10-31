<?php

namespace Tests\Feature\Api\V1;

use App\Models\Note;
use App\Models\Project;
use App\Models\QuickLink;
use App\Models\Release;
use App\Models\TasksheetEntry;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Covers the `api-authorization` capability spec — that the API grants each of
 * the seven roles exactly the access it has on the web, and that every
 * restriction is enforced server-side rather than by a client omitting a call.
 */
class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    private function release(): Release
    {
        $project = Project::create(['name' => 'P', 'color' => '#4f46e5']);
        $team = Team::create(['name' => 'T', 'color' => '#0891b2']);

        return Release::create([
            'project_id' => $project->id, 'team_id' => $team->id, 'name' => 'Checkout v9',
            'year' => 2026, 'quarter' => 3, 'start_date' => '2026-07-10', 'end_date' => '2026-07-30',
        ]);
    }

    public function test_limited_roles_are_blocked_from_planning_surfaces(): void
    {
        foreach ([User::ROLE_DEVELOPER, User::ROLE_QA] as $role) {
            Sanctum::actingAs($this->user($role));

            foreach ([
                '/api/v1/projects',
                '/api/v1/teams',
                '/api/v1/releases',
                '/api/v1/activities',
            ] as $url) {
                $this->getJson($url)->assertForbidden();
            }

            $this->app['auth']->forgetGuards();
        }
    }

    public function test_limited_roles_keep_their_collaboration_surfaces(): void
    {
        $release = $this->release();

        foreach ([User::ROLE_DEVELOPER, User::ROLE_QA] as $role) {
            Sanctum::actingAs($this->user($role));

            foreach ([
                '/api/v1/board',
                '/api/v1/events',
                '/api/v1/notes',
                '/api/v1/meeting-notes',
                '/api/v1/quick-links',
                '/api/v1/tasksheet',
                '/api/v1/tasks',
                '/api/v1/releases/'.$release->id, // the detail, not the list
            ] as $url) {
                $this->getJson($url)->assertOk();
            }

            $this->app['auth']->forgetGuards();
        }
    }

    public function test_developer_dashboard_is_the_personal_view(): void
    {
        Sanctum::actingAs($this->user(User::ROLE_DEVELOPER));

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.mode', 'member')
            ->assertJsonStructure(['data' => ['my_tasks', 'tasksheet_today', 'upcoming_meetings']]);
    }

    public function test_lead_dashboard_is_the_planning_timeline(): void
    {
        Sanctum::actingAs($this->user(User::ROLE_TEAM_LEAD));

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.mode', 'planning')
            ->assertJsonStructure(['data' => ['months', 'groups', 'analytics', 'conflicting_release_ids']]);
    }

    public function test_every_lead_role_may_plan_releases(): void
    {
        $project = Project::create(['name' => 'P', 'color' => '#4f46e5']);
        $team = Team::create(['name' => 'T', 'color' => '#0891b2']);

        foreach (User::LEAD_ROLES as $role) {
            Sanctum::actingAs($this->user($role));

            $this->postJson('/api/v1/releases', [
                'project_id' => $project->id,
                'team_id' => $team->id,
                'name' => 'Release by '.$role,
                'year' => 2026, 'quarter' => 3,
                'start_date' => '2026-07-01', 'end_date' => '2026-07-31',
                'phases' => [
                    'development' => ['start' => '2026-07-01', 'end' => '2026-07-10'],
                    'qa' => ['start' => '2026-07-11', 'end' => '2026-07-18'],
                    'retest' => ['start' => '2026-07-19', 'end' => '2026-07-25'],
                    'release' => ['start' => '2026-07-26', 'end' => '2026-07-31'],
                ],
            ])->assertCreated();

            $this->app['auth']->forgetGuards();
        }
    }

    public function test_non_lead_roles_cannot_plan_releases(): void
    {
        $project = Project::create(['name' => 'P', 'color' => '#4f46e5']);
        $team = Team::create(['name' => 'T', 'color' => '#0891b2']);

        foreach ([User::ROLE_DEVELOPER, User::ROLE_QA, User::ROLE_VIEWER] as $role) {
            Sanctum::actingAs($this->user($role));

            $this->postJson('/api/v1/releases', [
                'project_id' => $project->id,
                'team_id' => $team->id,
                'name' => 'Nope',
                'year' => 2026, 'quarter' => 3,
                'start_date' => '2026-07-01', 'end_date' => '2026-07-31',
            ])->assertForbidden();

            $this->app['auth']->forgetGuards();
        }
    }

    public function test_only_user_managers_reach_user_administration(): void
    {
        foreach ([User::ROLE_DEVELOPER, User::ROLE_QA, User::ROLE_VIEWER] as $role) {
            Sanctum::actingAs($this->user($role));
            $this->getJson('/api/v1/users')->assertForbidden();
            $this->app['auth']->forgetGuards();
        }

        Sanctum::actingAs($this->user(User::ROLE_ADMIN));
        $this->getJson('/api/v1/users')->assertOk();
    }

    public function test_viewer_cannot_upload_a_release_document(): void
    {
        $release = $this->release();
        Sanctum::actingAs($this->user(User::ROLE_VIEWER));

        $this->postJson("/api/v1/releases/{$release->id}/documents", [])->assertForbidden();
    }

    public function test_performance_is_closed_to_non_leads(): void
    {
        foreach ([User::ROLE_DEVELOPER, User::ROLE_QA, User::ROLE_VIEWER] as $role) {
            Sanctum::actingAs($this->user($role));

            foreach ([
                '/api/v1/performance/overview',
                '/api/v1/performance/evaluate',
                '/api/v1/performance/competencies',
                '/api/v1/performance/teams',
            ] as $url) {
                $this->getJson($url)->assertForbidden();
            }

            $this->app['auth']->forgetGuards();
        }
    }

    public function test_team_lead_cannot_reach_performance_for_a_team_they_do_not_lead(): void
    {
        $otherTeam = Team::create(['name' => 'Other', 'color' => '#0891b2']);
        Sanctum::actingAs($this->user(User::ROLE_TEAM_LEAD));

        $this->getJson('/api/v1/performance/overview?team_id='.$otherTeam->id)->assertForbidden();
    }

    public function test_team_lead_cannot_manage_the_competency_catalog(): void
    {
        Sanctum::actingAs($this->user(User::ROLE_TEAM_LEAD));

        $this->getJson('/api/v1/performance/competencies')->assertForbidden();
        $this->postJson('/api/v1/performance/competencies', [
            'name' => 'Sneaky', 'category' => 'technical', 'role_scope' => 'both',
            'cadence' => 'weekly', 'weight' => 5,
        ])->assertForbidden();
    }

    public function test_org_level_lead_may_manage_the_competency_catalog(): void
    {
        Sanctum::actingAs($this->user(User::ROLE_CTO));

        $this->getJson('/api/v1/performance/competencies')->assertOk();
    }

    public function test_a_lead_cannot_edit_someone_elses_personal_note(): void
    {
        $author = $this->user(User::ROLE_DEVELOPER);
        $note = $author->notes()->create([
            'date' => '2026-07-20', 'body' => '<p>Mine</p>', 'visibility' => Note::VISIBILITY_PRIVATE,
        ]);

        Sanctum::actingAs($this->user(User::ROLE_ADMIN));

        $this->putJson("/api/v1/notes/{$note->id}", [
            'date' => '2026-07-20', 'body' => '<p>Tampered</p>', 'visibility' => Note::VISIBILITY_PRIVATE,
        ])->assertForbidden();
    }

    public function test_another_users_private_note_is_absent_from_the_collection(): void
    {
        $author = $this->user(User::ROLE_DEVELOPER);
        $author->notes()->create([
            'date' => '2026-07-20', 'body' => '<p>Secret</p>', 'visibility' => Note::VISIBILITY_PRIVATE,
        ]);

        Sanctum::actingAs($this->user(User::ROLE_ADMIN));

        $this->getJson('/api/v1/notes')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_limited_role_sees_only_its_own_quick_links(): void
    {
        $other = $this->user(User::ROLE_ADMIN);
        QuickLink::create([
            'user_id' => $other->id, 'label' => 'Shared thing',
            'url' => 'https://example.com', 'visibility' => QuickLink::VISIBILITY_SHARED,
        ]);

        $dev = $this->user(User::ROLE_DEVELOPER);
        QuickLink::create([
            'user_id' => $dev->id, 'label' => 'Mine',
            'url' => 'https://example.com/mine', 'visibility' => QuickLink::VISIBILITY_PRIVATE,
        ]);

        Sanctum::actingAs($dev);

        $this->getJson('/api/v1/quick-links')
            ->assertOk()
            ->assertJsonCount(1, 'data.mine')
            ->assertJsonCount(0, 'data.shared');
    }

    public function test_limited_role_cannot_create_a_shared_quick_link(): void
    {
        Sanctum::actingAs($this->user(User::ROLE_DEVELOPER));

        $this->postJson('/api/v1/quick-links', [
            'label' => 'Nope',
            'url' => 'https://example.com',
            'visibility' => QuickLink::VISIBILITY_SHARED,
        ])->assertStatus(422)->assertJsonValidationErrors('visibility');
    }

    public function test_tasksheet_feedback_is_omitted_for_a_non_lead(): void
    {
        $team = Team::create(['name' => 'T', 'color' => '#0891b2']);
        $dev = $this->user(User::ROLE_DEVELOPER);
        $team->members()->attach($dev->id);

        TasksheetEntry::create([
            'team_id' => $team->id, 'user_id' => $dev->id, 'date' => today()->toDateString(),
            'plan' => '<p>Plan</p>', 'feedback' => '<p>Needs to speak up in standup</p>',
        ]);

        Sanctum::actingAs($dev);
        $asDeveloper = $this->getJson('/api/v1/tasksheet?team_id='.$team->id)->assertOk();

        $this->assertArrayNotHasKey('feedback', $asDeveloper->json('data.rows.0.entry'));
        $this->assertFalse($asDeveloper->json('data.can_write_feedback'));

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($this->user(User::ROLE_ADMIN));
        $asLead = $this->getJson('/api/v1/tasksheet?team_id='.$team->id)->assertOk();

        $this->assertArrayHasKey('feedback', $asLead->json('data.rows.0.entry'));
        $this->assertTrue($asLead->json('data.can_write_feedback'));
    }

    public function test_a_member_cannot_write_tasksheet_feedback(): void
    {
        $team = Team::create(['name' => 'T', 'color' => '#0891b2']);
        $dev = $this->user(User::ROLE_DEVELOPER);
        $team->members()->attach($dev->id);

        Sanctum::actingAs($dev);

        $this->putJson('/api/v1/tasksheet/entries', [
            'team_id' => $team->id,
            'user_id' => $dev->id,
            'date' => today()->toDateString(),
            'plan' => '<p>My plan</p>',
            'feedback' => '<p>I am wonderful</p>',
        ])->assertOk();

        $entry = TasksheetEntry::where('user_id', $dev->id)->first();
        $this->assertNull($entry->feedback);
    }

    public function test_a_member_cannot_save_a_teammates_row(): void
    {
        $team = Team::create(['name' => 'T', 'color' => '#0891b2']);
        $dev = $this->user(User::ROLE_DEVELOPER);
        $teammate = $this->user(User::ROLE_QA);
        $team->members()->attach([$dev->id, $teammate->id]);

        Sanctum::actingAs($dev);

        $this->putJson('/api/v1/tasksheet/entries', [
            'team_id' => $team->id,
            'user_id' => $teammate->id,
            'date' => today()->toDateString(),
            'plan' => '<p>Forged</p>',
        ])->assertForbidden();
    }

    public function test_another_members_tasksheet_history_is_private(): void
    {
        $dev = $this->user(User::ROLE_DEVELOPER);
        $teammate = $this->user(User::ROLE_QA);

        Sanctum::actingAs($dev);

        $this->getJson("/api/v1/tasksheet/users/{$teammate->id}")->assertForbidden();
        $this->getJson("/api/v1/tasksheet/users/{$dev->id}")->assertOk();
    }
}
