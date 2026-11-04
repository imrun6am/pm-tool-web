<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Release;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Notifications\TaskAssigned;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** The board's Add a card form can set an assignee, under the usual limits. */
class BoardQuickAddAssigneeTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    private Team $otherTeam;

    protected function setUp(): void
    {
        parent::setUp();

        $this->team = Team::create(['name' => 'Alpha', 'color' => '#4f46e5']);
        $this->otherTeam = Team::create(['name' => 'Beta', 'color' => '#db2777']);
    }

    private function user(string $role, ?Team $team = null): User
    {
        $user = User::factory()->create(['role' => $role]);
        $team?->members()->attach($user);

        return $user;
    }

    private function add(array $data = []): array
    {
        return $data + ['title' => 'New card', 'status' => 'todo'];
    }

    public function test_a_card_can_be_created_with_an_assignee_who_is_notified(): void
    {
        Notification::fake();
        $ana = $this->user(User::ROLE_DEVELOPER, $this->team);

        $this->actingAs($this->user(User::ROLE_ADMIN))
            ->post(route('board.tasks.store'), $this->add(['assignee_id' => $ana->id]))
            ->assertRedirect()->assertSessionHasNoErrors();

        $task = Task::where('title', 'New card')->firstOrFail();
        $this->assertSame($ana->id, $task->assignee_id);
        $this->assertSame($this->team->id, $task->team_id); // from the assignee's only team
        Notification::assertSentTo($ana, TaskAssigned::class);
    }

    public function test_leaving_it_blank_creates_an_unassigned_card(): void
    {
        $this->actingAs($this->user(User::ROLE_ADMIN))
            ->post(route('board.tasks.store'), $this->add(['assignee_id' => '']))
            ->assertSessionHasNoErrors();

        $this->assertNull(Task::where('title', 'New card')->firstOrFail()->assignee_id);
    }

    public function test_the_api_accepts_an_assignee_too(): void
    {
        $ana = $this->user(User::ROLE_DEVELOPER, $this->team);

        Sanctum::actingAs($this->user(User::ROLE_ADMIN), ['*']);

        $this->postJson('/api/v1/board/tasks', $this->add(['assignee_id' => $ana->id]))->assertCreated();

        $this->assertSame($ana->id, Task::where('title', 'New card')->firstOrFail()->assignee_id);
    }

    public function test_a_release_card_needs_someone_on_the_releases_team(): void
    {
        $release = Release::create([
            'project_id' => Project::create(['name' => 'Shop', 'color' => '#0891b2'])->id, 'team_id' => $this->team->id,
            'name' => 'v1', 'year' => 2026, 'quarter' => 4, 'start_date' => '2026-10-01', 'end_date' => '2026-12-01',
        ]);
        $outsider = $this->user(User::ROLE_DEVELOPER, $this->otherTeam);

        $this->actingAs($this->user(User::ROLE_ADMIN))
            ->post(route('board.tasks.store'), $this->add(['release_id' => $release->id, 'assignee_id' => $outsider->id]))
            ->assertSessionHasErrors('assignee_id');

        $this->assertDatabaseMissing('tasks', ['title' => 'New card']);
    }

    public function test_a_developer_can_only_pick_a_teammate(): void
    {
        $dev = $this->user(User::ROLE_DEVELOPER, $this->team);
        $mate = $this->user(User::ROLE_DEVELOPER, $this->team);
        $outsider = $this->user(User::ROLE_DEVELOPER, $this->otherTeam);

        $this->actingAs($dev)->post(route('board.tasks.store'), $this->add(['assignee_id' => $outsider->id]))
            ->assertSessionHasErrors('assignee_id');
        $this->assertDatabaseMissing('tasks', ['title' => 'New card']);

        $this->actingAs($dev)->post(route('board.tasks.store'), $this->add(['assignee_id' => $mate->id]))
            ->assertSessionHasNoErrors();
        $this->assertSame($mate->id, Task::where('title', 'New card')->firstOrFail()->assignee_id);
    }

    public function test_a_card_a_developer_or_qa_adds_is_theirs_by_default(): void
    {
        foreach ([User::ROLE_DEVELOPER, User::ROLE_QA] as $role) {
            $member = $this->user($role, $this->team);

            $this->actingAs($member)->post(route('board.tasks.store'), $this->add(['title' => "Card {$role}", 'assignee_id' => '']))
                ->assertSessionHasNoErrors();

            $this->assertSame($member->id, Task::where('title', "Card {$role}")->firstOrFail()->assignee_id);
        }
    }

    public function test_the_api_makes_a_developers_card_theirs_too(): void
    {
        $dev = $this->user(User::ROLE_DEVELOPER, $this->team);
        Sanctum::actingAs($dev, ['*']);

        $this->postJson('/api/v1/board/tasks', $this->add())->assertCreated();

        $this->assertSame($dev->id, Task::where('title', 'New card')->firstOrFail()->assignee_id);
    }

    public function test_a_lead_leaving_it_blank_still_gets_an_unassigned_card(): void
    {
        $this->actingAs($this->user(User::ROLE_TEAM_LEAD, $this->team))
            ->post(route('board.tasks.store'), $this->add())->assertSessionHasNoErrors();

        $this->assertNull(Task::where('title', 'New card')->firstOrFail()->assignee_id);
    }

    public function test_a_developers_form_starts_on_themselves_with_no_unassigned_option(): void
    {
        $dev = $this->user(User::ROLE_DEVELOPER, $this->team);

        $form = str($this->actingAs($dev)->get(route('board.index'))->assertOk()->getContent())
            ->after('id="new-card-assignee-todo"')->before('</select>');

        $this->assertFalse($form->contains('>Unassigned<'));
        $this->assertTrue($form->contains('selected'));
        $this->assertMatchesRegularExpression('/value="'.$dev->id.'"\s+selected/', (string) $form);
    }

    public function test_someone_deactivated_cant_be_picked(): void
    {
        $gone = User::factory()->create(['role' => User::ROLE_DEVELOPER, 'deactivated_at' => now()]);

        $this->actingAs($this->user(User::ROLE_ADMIN))
            ->post(route('board.tasks.store'), $this->add(['assignee_id' => $gone->id]))
            ->assertSessionHasErrors('assignee_id');
    }

    public function test_the_form_offers_people_and_reopens_on_an_error(): void
    {
        $ana = $this->user(User::ROLE_DEVELOPER, $this->team);
        $gone = User::factory()->create(['name' => 'Long Gone Person', 'role' => User::ROLE_DEVELOPER, 'deactivated_at' => now()]);
        $admin = $this->user(User::ROLE_ADMIN);

        $html = $this->actingAs($admin)->get(route('board.index'))->assertOk()->getContent();
        $form = str($html)->after('id="new-card-assignee-todo"')->before('</select>');
        $this->assertTrue($form->contains($ana->name));
        $this->assertFalse($form->contains('Long Gone Person'));

        $this->actingAs($admin)->from(route('board.index'))
            ->followingRedirects()
            ->post(route('board.tasks.store'), $this->add(['title' => 'Keep me', 'assignee_id' => $gone->id]))
            ->assertSee('Keep me')
            ->assertSee('role="alert"', false);
    }
}
