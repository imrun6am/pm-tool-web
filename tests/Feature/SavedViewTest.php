<?php

namespace Tests\Feature;

use App\Models\SavedView;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Named filter sets on the task list and board: personal, or shared by a lead.
 */
class SavedViewTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = User::ROLE_DEVELOPER): User
    {
        return User::factory()->create(['role' => $role]);
    }

    private function savedView(User $owner, array $attrs = []): SavedView
    {
        return SavedView::create($attrs + ['user_id' => $owner->id, 'page' => 'tasks', 'name' => 'Mine', 'filters' => ['priority' => 'high'], 'is_shared' => false]);
    }

    public function test_saving_keeps_only_the_filters_a_page_accepts(): void
    {
        $user = $this->user();

        $this->actingAs($user)->from(route('tasks.index'))->post(route('saved-views.store'), [
            'page' => 'board', 'name' => 'Urgent bugs',
            'filters' => ['priority' => 'urgent', 'label_id' => '3', 'q' => 'not on the board', 'evil' => 'x', 'blank' => '', 'team_id' => ['nested']],
        ])->assertRedirect(route('tasks.index'));

        $this->assertSame(['priority' => 'urgent', 'label_id' => '3'], SavedView::sole()->filters);
        $this->assertSame(route('board.index', ['priority' => 'urgent', 'label_id' => '3']), SavedView::sole()->url());
    }

    public function test_saving_under_the_same_name_replaces_the_view(): void
    {
        $user = $this->user();

        $this->actingAs($user);
        $this->post(route('saved-views.store'), ['page' => 'tasks', 'name' => 'Mine', 'filters' => ['priority' => 'low']]);
        $this->post(route('saved-views.store'), ['page' => 'tasks', 'name' => 'Mine', 'filters' => ['priority' => 'high']]);

        $this->assertSame(['priority' => 'high'], SavedView::sole()->filters);
    }

    public function test_the_page_lists_own_and_shared_views_and_not_other_peoples(): void
    {
        $user = $this->user();
        $lead = $this->user(User::ROLE_TEAM_LEAD);
        $this->savedView($user, ['name' => 'My view']);
        $this->savedView($lead, ['name' => 'Team view', 'is_shared' => true]);
        $this->savedView($lead, ['name' => 'Private to lead']);
        $this->savedView($user, ['name' => 'Board view', 'page' => 'board']);

        $this->actingAs($user)->get(route('tasks.index'))->assertOk()
            ->assertSee('My view')
            ->assertSee('Team view')
            ->assertDontSee('Private to lead')
            ->assertDontSee('Board view');

        $this->get(route('board.index'))->assertOk()->assertSee('Board view')->assertDontSee('My view');
    }

    public function test_the_form_carries_the_current_filters(): void
    {
        $this->actingAs($this->user())->get(route('tasks.index', ['priority' => 'high', 'page' => 2]))->assertOk()
            ->assertSee('name="filters[priority]" value="high"', false)
            ->assertDontSee('name="filters[page]"', false);
    }

    /** @return array<string, array{0: string}> */
    public static function nonLeads(): array
    {
        return ['developer' => [User::ROLE_DEVELOPER], 'qa' => [User::ROLE_QA], 'viewer' => [User::ROLE_VIEWER]];
    }

    #[DataProvider('nonLeads')]
    public function test_only_leads_can_share(string $role): void
    {
        $user = $this->user($role);

        $this->actingAs($user)->post(route('saved-views.store'), ['page' => 'tasks', 'name' => 'X', 'shared' => 1])->assertForbidden();
        $this->post(route('saved-views.store'), ['page' => 'tasks', 'name' => 'X'])->assertRedirect();
        $this->get(route('tasks.index'))->assertDontSee('Share with everyone');

        $this->assertFalse(SavedView::sole()->is_shared);
    }

    public function test_a_lead_can_share(): void
    {
        $lead = $this->user(User::ROLE_TEAM_LEAD);

        $this->actingAs($lead)->get(route('tasks.index'))->assertSee('Share with everyone');
        $this->post(route('saved-views.store'), ['page' => 'tasks', 'name' => 'X', 'shared' => 1])->assertRedirect();

        $this->assertTrue(SavedView::sole()->is_shared);
    }

    public function test_who_can_remove_a_view(): void
    {
        $owner = $this->user();
        $other = $this->user();
        $lead = $this->user(User::ROLE_TEAM_LEAD);
        $personal = $this->savedView($owner);
        $shared = $this->savedView($this->user(User::ROLE_CTO), ['name' => 'Shared', 'is_shared' => true]);

        $this->actingAs($other)->delete(route('saved-views.destroy', $personal))->assertForbidden();
        $this->delete(route('saved-views.destroy', $shared))->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->actingAs($lead)->delete(route('saved-views.destroy', $personal))->assertForbidden();
        $this->delete(route('saved-views.destroy', $shared))->assertRedirect();

        $this->app['auth']->forgetGuards();
        $this->actingAs($owner)->delete(route('saved-views.destroy', $personal))->assertRedirect();

        $this->assertSame(0, SavedView::count());
    }

    public function test_the_api_mirrors_the_web(): void
    {
        $user = $this->user();
        $this->savedView($this->user(User::ROLE_CTO), ['name' => 'Shared', 'is_shared' => true]);
        Sanctum::actingAs($user);

        $id = $this->postJson('/api/v1/saved-views', ['page' => 'tasks', 'name' => 'Mine', 'filters' => ['priority' => 'high', 'evil' => 'x']])
            ->assertCreated()
            ->assertJsonPath('data.filters.priority', 'high')
            ->assertJsonMissingPath('data.filters.evil')
            ->assertJsonPath('data.is_mine', true)
            ->json('data.id');

        $this->getJson('/api/v1/saved-views?page=tasks')->assertOk()
            ->assertJsonPath('data.0.name', 'Mine')
            ->assertJsonPath('data.1.name', 'Shared')
            ->assertJsonPath('data.1.can_delete', false);

        $this->getJson('/api/v1/saved-views?page=nope')->assertStatus(422);
        $this->postJson('/api/v1/saved-views', ['page' => 'nope', 'name' => 'X'])->assertStatus(422);
        $this->postJson('/api/v1/saved-views', ['page' => 'tasks', 'name' => 'X', 'shared' => true])->assertForbidden();

        $this->deleteJson("/api/v1/saved-views/{$id}")->assertOk();
        $this->assertDatabaseMissing('saved_views', ['id' => $id]);
    }
}
