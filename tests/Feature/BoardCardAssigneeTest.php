<?php

namespace Tests\Feature;

use App\Models\Activity;
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

/**
 * The board's Unassigned filter, and setting a card's assignee from the board
 * under the same rules as the task form.
 */
class BoardCardAssigneeTest extends TestCase
{
    use RefreshDatabase;

    private Team $core;

    private Team $growth;

    private Release $coreRelease;

    protected function setUp(): void
    {
        parent::setUp();

        $this->core = Team::create(['name' => 'Core', 'color' => '#0891b2']);
        $this->growth = Team::create(['name' => 'Growth', 'color' => '#e11d48']);
        $project = Project::create(['name' => 'Checkout', 'color' => '#4f46e5']);
        $this->coreRelease = Release::create([
            'project_id' => $project->id, 'team_id' => $this->core->id, 'name' => 'Core R1',
            'year' => 2026, 'quarter' => 3, 'start_date' => '2026-07-01', 'end_date' => '2026-07-31',
        ]);
    }

    private function task(string $title, ?Team $team = null, array $attrs = []): Task
    {
        return Task::create($attrs + ['team_id' => $team?->id, 'title' => $title, 'status' => 'todo', 'position' => 0]);
    }

    private function linkedTask(string $title = 'Linked', array $attrs = []): Task
    {
        return $this->task($title, $this->core, $attrs + ['release_id' => $this->coreRelease->id]);
    }

    private function member(Team $team, string $role = User::ROLE_DEVELOPER): User
    {
        $user = User::factory()->create(['role' => $role]);
        $team->members()->attach($user);

        return $user;
    }

    private function lead(): User
    {
        return User::factory()->create(['role' => User::ROLE_TEAM_LEAD]);
    }

    /** @return list<string> */
    private function webBoardTitles(User $viewer, array $query): array
    {
        return collect($this->actingAs($viewer)->get(route('board.index', $query))->assertOk()->viewData('columns'))
            ->flatten()->pluck('title')->sort()->values()->all();
    }

    // ── The filter ─────────────────────────────────────────────────────────

    public function test_the_unassigned_filter_shows_only_cards_without_an_assignee(): void
    {
        $lead = $this->lead();
        $this->task('Open', $this->core);
        $this->task('Taken', $this->core, ['assignee_id' => $lead->id]);
        $parent = $this->task('Parent', $this->core, ['assignee_id' => $lead->id]);
        Task::create(['parent_id' => $parent->id, 'title' => 'Open subtask', 'status' => 'todo', 'position' => 0]);

        $this->assertSame(['Open'], $this->webBoardTitles($lead, ['assignee_id' => 'none']));

        $this->actingAs($lead)->get(route('board.index', ['assignee_id' => 'none']))
            ->assertSee('<option value="none" selected>Unassigned</option>', false);

        // Admins' select all names the filter it covers.
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))->get(route('board.index', ['assignee_id' => 'none']))
            ->assertSee('Only Unassigned', false);
    }

    public function test_the_filter_is_offered_and_not_selected_by_default(): void
    {
        $this->actingAs($this->lead())->get(route('board.index'))
            ->assertOk()
            ->assertSee('<option value="none" >Unassigned</option>', false);
    }

    public function test_the_unassigned_filter_combines_with_the_team_and_release_filters(): void
    {
        $lead = $this->lead();
        $this->task('Core open', $this->core);
        $this->task('Core taken', $this->core, ['assignee_id' => $lead->id]);
        $this->task('Growth open', $this->growth);
        $this->task('Teamless open');
        $this->linkedTask('Linked open');

        $this->assertSame(['Core open', 'Linked open'], $this->webBoardTitles($lead, ['assignee_id' => 'none', 'team_id' => $this->core->id]));
        $this->assertSame(['Teamless open'], $this->webBoardTitles($lead, ['assignee_id' => 'none', 'team_id' => 'none']));
        $this->assertSame(['Linked open'], $this->webBoardTitles($lead, ['assignee_id' => 'none', 'release_id' => $this->coreRelease->id]));
    }

    public function test_a_named_assignee_filter_and_my_tasks_still_work(): void
    {
        $lead = $this->lead();
        $this->task('Open', $this->core);
        $this->task('Mine', $this->core, ['assignee_id' => $lead->id]);

        $this->assertSame(['Mine'], $this->webBoardTitles($lead, ['assignee_id' => $lead->id]));
        $this->assertSame(['Mine', 'Open'], $this->webBoardTitles($lead, []));
    }

    public function test_a_developer_sees_only_their_own_teams_unassigned_cards(): void
    {
        $dev = $this->member($this->core);
        $this->task('Core open', $this->core);
        $this->task('Growth open', $this->growth);

        $this->assertSame(['Core open'], $this->webBoardTitles($dev, ['assignee_id' => 'none']));
    }

    public function test_the_api_filters_and_echoes_unassigned(): void
    {
        $lead = $this->lead();
        $this->task('Open', $this->core);
        $this->task('Taken', $this->core, ['assignee_id' => $lead->id]);
        $this->task('Growth open', $this->growth);

        Sanctum::actingAs($lead);
        $response = $this->getJson("/api/v1/board?assignee_id=none&team_id={$this->core->id}")->assertOk()
            ->assertJsonPath('data.filters.assignee_id', 'none');

        $this->assertSame(['Open'], collect($response->json('data.columns'))->flatMap(fn ($c) => $c['tasks'])->pluck('title')->all());
    }

    // ── Assigning ──────────────────────────────────────────────────────────

    public function test_a_lead_assigns_changes_and_clears_without_touching_anything_else(): void
    {
        $lead = $this->lead();
        $first = $this->member($this->core);
        $second = $this->member($this->core);
        $task = $this->linkedTask('Card', ['status' => 'in_review', 'position' => 7]);

        $this->actingAs($lead)->patchJson(route('board.tasks.assign', $task), ['assignee_id' => $first->id])
            ->assertOk()
            ->assertJsonPath('assignee_id', $first->id)
            ->assertJsonPath('assignee_name', $first->name)
            ->assertJsonPath('visible', true);
        $this->assertSame($first->id, $task->fresh()->assignee_id);

        $this->actingAs($lead)->patchJson(route('board.tasks.assign', $task), ['assignee_id' => $second->id])->assertOk();
        $this->assertSame($second->id, $task->fresh()->assignee_id);

        $this->actingAs($lead)->patchJson(route('board.tasks.assign', $task), ['assignee_id' => null])
            ->assertOk()
            ->assertJsonPath('assignee_id', null)
            ->assertJsonPath('assignee_name', null);

        $fresh = $task->fresh();
        $this->assertNull($fresh->assignee_id);
        $this->assertSame('in_review', $fresh->status);
        $this->assertSame(7, $fresh->position);
        $this->assertSame($this->coreRelease->id, $fresh->release_id);
        $this->assertSame($this->core->id, $fresh->team_id);
    }

    public function test_the_response_renders_the_new_control(): void
    {
        $person = $this->member($this->core);
        $task = $this->linkedTask();

        $html = $this->actingAs($this->lead())
            ->patchJson(route('board.tasks.assign', $task), ['assignee_id' => $person->id])
            ->assertOk()->json('html');

        $this->assertStringContainsString($person->name, $html);
        $this->assertStringContainsString('board-assign', $html);
    }

    public function test_assigning_is_logged_and_notifies_the_new_assignee(): void
    {
        Notification::fake();
        $lead = $this->lead();
        $person = $this->member($this->core);
        $task = $this->linkedTask();

        $this->actingAs($lead)->patchJson(route('board.tasks.assign', $task), ['assignee_id' => $person->id])->assertOk();

        Notification::assertSentTo($person, TaskAssigned::class);
        $this->assertTrue(Activity::query()
            ->where('subject_type', $task->getMorphClass())->where('subject_id', $task->id)
            ->where('event', 'updated')->where('causer_id', $lead->id)->exists());
    }

    public function test_the_assignee_key_is_required(): void
    {
        $person = $this->member($this->core);
        $task = $this->linkedTask(attrs: ['assignee_id' => $person->id]);

        $this->actingAs($this->lead())->patchJson(route('board.tasks.assign', $task), [])
            ->assertStatus(422)->assertJsonValidationErrors('assignee_id');

        $this->assertSame($person->id, $task->fresh()->assignee_id);
    }

    public function test_a_guest_cannot_assign_or_list(): void
    {
        $task = $this->task('Card', $this->core);

        $this->patch(route('board.tasks.assign', $task), ['assignee_id' => null])->assertRedirect(route('login'));
        $this->get(route('board.tasks.assignees', $task))->assertRedirect(route('login'));
    }

    // ── Who may be picked ──────────────────────────────────────────────────

    public function test_a_lead_cannot_assign_outside_the_release_team(): void
    {
        $outsider = $this->member($this->growth);
        $task = $this->linkedTask();

        $this->actingAs($this->lead())->patchJson(route('board.tasks.assign', $task), ['assignee_id' => $outsider->id])
            ->assertStatus(422)->assertJsonValidationErrors('assignee_id');

        $this->assertNull($task->fresh()->assignee_id);
    }

    public function test_sending_another_release_does_not_skip_the_team_check(): void
    {
        $outsider = $this->member($this->growth);
        $task = $this->linkedTask();
        $lead = $this->lead();

        $url = route('board.tasks.assign', $task);
        foreach ([
            fn () => $this->patchJson($url, ['assignee_id' => $outsider->id, 'release_id' => null]),
            fn () => $this->patchJson($url.'?release_id=', ['assignee_id' => $outsider->id]),
        ] as $attempt) {
            $this->actingAs($lead);
            $attempt()->assertStatus(422)->assertJsonValidationErrors('assignee_id');
        }

        $this->assertNull($task->fresh()->assignee_id);
        $this->assertSame($this->coreRelease->id, $task->fresh()->release_id);
    }

    public function test_only_the_assignee_is_written_whatever_else_is_sent(): void
    {
        $person = $this->member($this->core);
        $task = $this->linkedTask('Card');

        $this->actingAs($this->lead())->patchJson(route('board.tasks.assign', $task), [
            'assignee_id' => $person->id, 'title' => 'Changed', 'status' => 'done', 'team_id' => $this->growth->id,
        ])->assertOk();

        $fresh = $task->fresh();
        $this->assertSame('Card', $fresh->title);
        $this->assertSame('todo', $fresh->status);
        $this->assertSame($this->core->id, $fresh->team_id);
    }

    public function test_a_developer_can_pick_only_themselves_or_a_teammate(): void
    {
        $dev = $this->member($this->core);
        $teammate = $this->member($this->core, User::ROLE_QA);
        $outsider = $this->member($this->growth);
        $task = $this->task('Card', $this->core);

        $this->actingAs($dev)->patchJson(route('board.tasks.assign', $task), ['assignee_id' => $outsider->id])
            ->assertStatus(422)->assertJsonValidationErrors('assignee_id');
        $this->assertNull($task->fresh()->assignee_id);

        $this->actingAs($dev)->patchJson(route('board.tasks.assign', $task), ['assignee_id' => $teammate->id])->assertOk();
        $this->assertSame($teammate->id, $task->fresh()->assignee_id);

        $this->actingAs($dev)->patchJson(route('board.tasks.assign', $task), ['assignee_id' => $dev->id])->assertOk();
        $this->assertSame($dev->id, $task->fresh()->assignee_id);
    }

    public function test_limited_roles_cannot_touch_another_teams_task(): void
    {
        $task = $this->task('Growth card', $this->growth);

        foreach ([User::ROLE_DEVELOPER, User::ROLE_QA] as $role) {
            $user = $this->member($this->core, $role);

            $this->actingAs($user)->patchJson(route('board.tasks.assign', $task), ['assignee_id' => $user->id])->assertForbidden();
            $this->actingAs($user)->getJson(route('board.tasks.assignees', $task))->assertForbidden();
        }

        $this->assertNull($task->fresh()->assignee_id);
    }

    public function test_a_deactivated_person_cannot_be_newly_assigned_but_can_stay_or_be_cleared(): void
    {
        $lead = $this->lead();
        $gone = User::factory()->create(['deactivated_at' => now()]);
        $this->core->members()->attach($gone);
        $open = $this->linkedTask('Open');
        $held = $this->linkedTask('Held', ['assignee_id' => $gone->id]);

        $this->actingAs($lead)->patchJson(route('board.tasks.assign', $open), ['assignee_id' => $gone->id])
            ->assertStatus(422)->assertJsonValidationErrors('assignee_id');
        $this->assertNull($open->fresh()->assignee_id);

        $this->actingAs($lead)->patchJson(route('board.tasks.assign', $held), ['assignee_id' => $gone->id])->assertOk();
        $this->assertSame($gone->id, $held->fresh()->assignee_id);

        $this->actingAs($lead)->patchJson(route('board.tasks.assign', $held), ['assignee_id' => null])->assertOk();
        $this->assertNull($held->fresh()->assignee_id);
    }

    public function test_an_unknown_or_malformed_assignee_is_rejected(): void
    {
        $task = $this->task('Card', $this->core);
        $lead = $this->lead();

        foreach ([999999, 'abc'] as $value) {
            $this->actingAs($lead)->patchJson(route('board.tasks.assign', $task), ['assignee_id' => $value])
                ->assertStatus(422)->assertJsonValidationErrors('assignee_id');
        }

        $this->assertNull($task->fresh()->assignee_id);
    }

    public function test_the_response_says_when_the_viewer_loses_sight_of_the_card(): void
    {
        $dev = $this->member($this->core);
        // Another team's task, visible to this developer only as its assignee.
        $task = $this->task('Growth card', $this->growth, ['assignee_id' => $dev->id]);

        $this->actingAs($dev)->patchJson(route('board.tasks.assign', $task), ['assignee_id' => null])
            ->assertOk()->assertJsonPath('visible', false);
    }

    // ── The picker's list ──────────────────────────────────────────────────

    /** @return list<int> */
    private function pickerIds(User $viewer, Task $task): array
    {
        return collect($this->actingAs($viewer)->getJson(route('board.tasks.assignees', $task))->assertOk()->json('people'))
            ->pluck('id')->sort()->values()->all();
    }

    public function test_a_linked_card_offers_the_release_teams_active_members_and_its_current_assignee(): void
    {
        $member = $this->member($this->core);
        $gone = User::factory()->create(['deactivated_at' => now()]);
        $this->core->members()->attach($gone);
        $leftTeam = $this->member($this->growth);
        $this->member($this->growth);

        $open = $this->linkedTask('Open');
        $held = $this->linkedTask('Held', ['assignee_id' => $leftTeam->id]);

        $this->assertSame([$member->id], $this->pickerIds($this->lead(), $open));
        $this->assertEqualsCanonicalizing([$member->id, $leftTeam->id], $this->pickerIds($this->lead(), $held));
    }

    public function test_a_card_without_a_release_offers_every_active_user_to_a_lead(): void
    {
        $lead = $this->lead();
        $core = $this->member($this->core);
        $growth = $this->member($this->growth);
        User::factory()->create(['deactivated_at' => now()]);
        $task = $this->task('Loose');

        $this->assertEqualsCanonicalizing([$lead->id, $core->id, $growth->id], $this->pickerIds($lead, $task));
    }

    public function test_a_developer_is_never_offered_people_from_other_teams(): void
    {
        $dev = $this->member($this->core);
        $teammate = $this->member($this->core, User::ROLE_QA);
        $outsider = $this->member($this->growth);
        $task = $this->task('Card', $this->core);
        // Someone from another team on a subtask must not reach the developer's list.
        Task::create(['parent_id' => $task->id, 'team_id' => $this->core->id, 'title' => 'Sub', 'status' => 'todo', 'position' => 0, 'assignee_id' => $outsider->id]);

        $people = $this->actingAs($dev)->getJson(route('board.tasks.assignees', $task))->assertOk()->assertJsonMissing(['name' => $outsider->name])->json('people');

        $this->assertEqualsCanonicalizing([$dev->id, $teammate->id], collect($people)->pluck('id')->all());
    }

    public function test_the_api_picker_lists_summaries_without_private_fields(): void
    {
        $dev = $this->member($this->core);
        $this->member($this->growth);
        $task = $this->task('Card', $this->core);

        Sanctum::actingAs($dev);
        $response = $this->getJson("/api/v1/board/tasks/{$task->id}/assignees")->assertOk()
            ->assertJsonPath('data.assignee_id', null)
            ->assertJsonCount(1, 'data.people')
            ->assertJsonPath('data.people.0.id', $dev->id);

        $this->assertArrayNotHasKey('team_ids', $response->json('data.people.0'));
    }

    // ── API writes ─────────────────────────────────────────────────────────

    public function test_the_api_assigns_clears_and_rejects_by_the_same_rules(): void
    {
        $dev = $this->member($this->core);
        $outsider = $this->member($this->growth);
        $task = $this->task('Card', $this->core, ['status' => 'recheck']);
        $other = $this->task('Growth card', $this->growth);

        Sanctum::actingAs($dev);

        $this->patchJson("/api/v1/board/tasks/{$task->id}/assignee", ['assignee_id' => $dev->id])->assertOk()
            ->assertJsonPath('data.assignee_id', $dev->id)
            ->assertJsonPath('data.assignee.name', $dev->name)
            ->assertJsonPath('data.status', 'recheck')
            ->assertJsonPath('message', 'Task assigned.');

        $this->patchJson("/api/v1/board/tasks/{$task->id}/assignee", ['assignee_id' => null])->assertOk()
            ->assertJsonPath('data.assignee_id', null)
            ->assertJsonPath('message', 'Task unassigned.');

        $this->patchJson("/api/v1/board/tasks/{$task->id}/assignee", ['assignee_id' => $outsider->id])
            ->assertStatus(422)->assertJsonValidationErrors('assignee_id');

        $this->patchJson("/api/v1/board/tasks/{$other->id}/assignee", ['assignee_id' => $dev->id])->assertForbidden();
        $this->getJson("/api/v1/board/tasks/{$other->id}/assignees")->assertForbidden();
    }

    // ── The card ───────────────────────────────────────────────────────────

    public function test_every_card_carries_the_assign_control(): void
    {
        $person = $this->member($this->core);
        $open = $this->task('Open', $this->core);
        $held = $this->task('Held', $this->core, ['assignee_id' => $person->id]);

        $this->actingAs($this->lead())->get(route('board.index'))
            ->assertOk()
            ->assertSee(route('board.tasks.assign', $open), false)
            ->assertSee(route('board.tasks.assign', $held), false)
            ->assertSee('Unassigned. Assign “Open”', false)
            ->assertSee('Assigned to '.$person->name, false);
    }
}
