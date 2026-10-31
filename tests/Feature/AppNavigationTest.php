<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_page_item_is_marked_active(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $html = $this->actingAs($user)->get(route('board.index'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'aria-current="page"'));
        $this->assertMatchesRegularExpression('#href="[^"]*/board"[^>]*nav-item-active[^>]*aria-current="page"#', $html);
    }

    public function test_nested_page_keeps_parent_item_active(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $event = Event::create([
            'title' => 'Retro', 'type' => 'meeting',
            'starts_at' => '2026-07-15 10:00:00', 'ends_at' => '2026-07-15 11:00:00',
            'created_by' => $user->id,
        ]);

        $html = $this->actingAs($user)->get(route('events.show', $event))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'aria-current="page"'));
        $this->assertMatchesRegularExpression('#href="[^"]*/calendar"[^>]*nav-item-active[^>]*aria-current="page"#', $html);
    }

    public function test_page_outside_the_menu_has_no_active_item(): void
    {
        $user = User::factory()->create();

        $html = $this->actingAs($user)->get(route('profile.edit'))->assertOk()->getContent();

        $this->assertStringNotContainsString('aria-current="page"', $html);
    }

    public function test_developer_shell_never_mentions_hidden_sections(): void
    {
        foreach ([User::ROLE_DEVELOPER, User::ROLE_QA] as $role) {
            $user = User::factory()->create(['role' => $role]);

            // Covers sidebar markup, tooltips, and the command-palette JSON.
            $this->actingAs($user)->get(route('notes.index'))
                ->assertOk()
                ->assertSee('Collaboration')
                ->assertSee('>Board<', false)
                ->assertDontSee('Planning')
                ->assertDontSee('Administration')
                ->assertDontSee('Projects')
                ->assertDontSee('Teams')
                ->assertDontSee('Releases')
                ->assertDontSee('Activity')
                ->assertDontSee('Performance')
                ->assertDontSee('>Users<', false)
                ->assertDontSee('New release');
        }
    }

    public function test_lead_shell_shows_groups_create_menu_and_quick_links_button(): void
    {
        $lead = User::factory()->create(['role' => User::ROLE_TEAM_LEAD]);

        $this->actingAs($lead)->get(route('dashboard'))
            ->assertOk()
            ->assertSeeInOrder(['>Planning<', '>Work<', '>Collaboration<', '>Administration<'], false)
            ->assertSee('New release')
            ->assertSee('New meeting note')
            ->assertSee(route('releases.create'), false)
            ->assertSee('aria-label="Quick links"', false)
            ->assertSee('Command palette')
            // The old floating right-edge handle is gone.
            ->assertDontSee('fixed right-0 top-1/3', false);
    }

    public function test_viewer_sees_planning_but_not_administration(): void
    {
        $viewer = User::factory()->create(['role' => User::ROLE_VIEWER]);

        $this->actingAs($viewer)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('>Planning<', false)
            ->assertDontSee('>Administration<', false)
            ->assertDontSee('New release');
    }
}
