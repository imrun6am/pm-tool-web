<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Release;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    private function newUserPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'New Person',
            'email' => 'new@example.com',
            'role' => User::ROLE_DEVELOPER,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], $overrides);
    }

    public function test_leads_can_create_users_others_cannot(): void
    {
        // Every leadership role (admin, CTO, tech lead, team lead) may manage users.
        $i = 0;
        foreach (User::LEAD_ROLES as $role) {
            $this->actingAs($this->user($role))
                ->post(route('users.store'), $this->newUserPayload(['email' => "lead-$i@example.com"]))
                ->assertRedirect(route('users.index'));
            $this->assertDatabaseHas('users', ['email' => "lead-$i@example.com", 'role' => 'developer']);
            $i++;
        }

        // Non-leads may neither see the directory nor create users.
        foreach ([User::ROLE_DEVELOPER, User::ROLE_QA, User::ROLE_VIEWER] as $role) {
            $this->actingAs($this->user($role))
                ->get(route('users.index'))
                ->assertForbidden();
            $this->actingAs($this->user($role))
                ->post(route('users.store'), $this->newUserPayload(['email' => "x-$role@example.com"]))
                ->assertForbidden();
        }
    }

    public function test_duplicate_email_is_rejected(): void
    {
        $this->user(User::ROLE_ADMIN); // exists with factory email? ensure unique target
        User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs($this->user(User::ROLE_ADMIN))
            ->post(route('users.store'), $this->newUserPayload(['email' => 'taken@example.com']))
            ->assertSessionHasErrors('email');
    }

    public function test_deactivated_user_cannot_log_in(): void
    {
        $user = User::factory()->create([
            'email' => 'off@example.com',
            'password' => Hash::make('secret123'),
            'deactivated_at' => now(),
        ]);

        $this->post(route('login'), ['email' => 'off@example.com', 'password' => 'secret123'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_active_user_can_log_in(): void
    {
        User::factory()->create(['email' => 'on@example.com', 'password' => Hash::make('secret123')]);

        $this->post(route('login'), ['email' => 'on@example.com', 'password' => 'secret123']);

        $this->assertAuthenticated();
    }

    public function test_cannot_deactivate_self_or_last_admin(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);

        // Only admin → cannot deactivate self (also the last admin).
        $this->actingAs($admin)->post(route('users.toggle', $admin))->assertSessionHas('error');
        $this->assertTrue($admin->fresh()->isActive());

        // A second admin exists; still cannot deactivate the *acting* admin (self rule).
        $other = $this->user(User::ROLE_ADMIN);
        $this->actingAs($admin)->post(route('users.toggle', $admin))->assertSessionHas('error');

        // But can deactivate the other admin now that two exist.
        $this->actingAs($admin)->post(route('users.toggle', $other))->assertSessionHas('success');
        $this->assertFalse($other->fresh()->isActive());
    }

    public function test_deleted_users_data_stays_visible_with_tag_and_login_is_blocked(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $team = Team::create(['name' => 'T', 'color' => '#0891b2']);
        $dev = User::factory()->create(['role' => User::ROLE_DEVELOPER, 'name' => 'Gone Person']);
        $team->members()->attach($dev);

        \App\Models\TasksheetEntry::create([
            'team_id' => $team->id, 'user_id' => $dev->id,
            'date' => today()->subDay()->toDateString(), 'plan' => 'Legacy work',
        ]);

        $this->actingAs($admin)->delete(route('users.destroy', $dev))->assertRedirect();

        // Data still visible on the sheet, tagged.
        $this->actingAs($admin)
            ->get(route('tasksheet.index', ['team' => $team->id, 'date' => today()->subDay()->toDateString()]))
            ->assertOk()
            ->assertSee('Gone Person')
            ->assertSee('Legacy work')
            ->assertSee('Deleted user');

        // Login is blocked (soft-deleted accounts are out of the auth scope).
        \Illuminate\Support\Facades\Auth::logout();
        $this->post('/login', ['email' => $dev->email, 'password' => 'password']);
        $this->assertGuest();
    }

    public function test_deactivated_users_data_is_tagged(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $team = Team::create(['name' => 'T', 'color' => '#0891b2']);
        $dev = User::factory()->create(['role' => User::ROLE_DEVELOPER, 'name' => 'Paused Person']);
        $team->members()->attach($dev);

        \App\Models\TasksheetEntry::create([
            'team_id' => $team->id, 'user_id' => $dev->id,
            'date' => today()->subDay()->toDateString(), 'plan' => 'Recent work',
        ]);

        $this->actingAs($admin)->post(route('users.toggle', $dev))->assertRedirect();

        $this->actingAs($admin)
            ->get(route('tasksheet.index', ['team' => $team->id, 'date' => today()->subDay()->toDateString()]))
            ->assertOk()
            ->assertSee('Paused Person')
            ->assertSee('Deactivated');
    }

    public function test_user_list_links_to_tasksheet_history_for_dev_and_qa(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $dev = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $viewer = $this->user(User::ROLE_VIEWER);

        $response = $this->actingAs($admin)->get(route('users.index'))->assertOk();
        $response->assertSee('tasksheet/users/'.$dev->id, false);
        $response->assertDontSee('tasksheet/users/'.$viewer->id, false);
    }

    public function test_registration_route_is_disabled(): void
    {
        $this->assertFalse(app('router')->has('register'));
        $this->get('/register')->assertNotFound();
    }

    public function test_new_role_user_can_comment_and_check_tasks(): void
    {
        $project = Project::create(['name' => 'P', 'color' => '#4f46e5']);
        $team = Team::create(['name' => 'T', 'color' => '#0891b2']);
        $release = Release::create([
            'project_id' => $project->id, 'team_id' => $team->id, 'name' => 'R',
            'year' => 2026, 'quarter' => 3, 'start_date' => '2026-07-10', 'end_date' => '2026-07-30',
        ]);

        $qa = $this->user(User::ROLE_QA);

        // QA works from the board: add a task there, then "check" it by moving to done.
        $this->actingAs($qa)->post(route('board.tasks.store'), [
            'release_id' => $release->id, 'title' => 'Verify build', 'status' => 'todo',
        ])->assertRedirect();
        $task = Task::first();
        $this->actingAs($qa)->patch(route('tasks.status', $task), ['status' => 'done'])->assertRedirect();
        $this->assertSame('done', $task->fresh()->status);

        // And comment — on the task and on the release (QA may collaborate).
        $this->actingAs($qa)->post(route('tasks.comments.store', $task), ['body' => 'Looks good'])->assertRedirect();
        $this->assertSame(1, $task->comments()->count());
        $this->actingAs($qa)->post(route('releases.comments.store', $release), ['body' => 'On the release'])->assertRedirect();

        // But release *editing* stays forbidden for QA (dev-qa-restricted-access).
        $this->actingAs($qa)->get(route('releases.edit', $release))->assertForbidden();
    }
}
