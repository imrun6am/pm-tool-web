<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Searchable selects (openspec/changes/searchable-selects) are a client-side
 * enhancement: the server keeps rendering plain native <select>s, which stay
 * the source of truth for posts, old() values and x-model. These checks pin
 * that contract so a view change can't quietly break the enhancement.
 */
class SearchableSelectsTest extends TestCase
{
    use RefreshDatabase;

    public function test_forms_render_native_selects_with_names_and_selected_options(): void
    {
        $lead = User::factory()->create(['role' => User::ROLE_TEAM_LEAD, 'name' => 'Lee Lead']);
        Team::create(['name' => 'Core', 'color' => '#0891b2']);

        $this->actingAs($lead)->get(route('tasks.quick'))
            ->assertOk()
            ->assertSee('<select id="qc-assignee" name="assignee_id"', false)
            ->assertSee('<option value="'.$lead->id.'" selected', false)
            ->assertDontSee('data-ss', false);
    }

    public function test_old_input_reselects_the_option_after_a_validation_error(): void
    {
        $lead = User::factory()->create(['role' => User::ROLE_TEAM_LEAD]);
        $other = User::factory()->create(['role' => User::ROLE_DEVELOPER, 'name' => 'Olive Other']);

        $this->actingAs($lead)
            ->from(route('tasks.quick'))
            ->post(route('tasks.quick.store'), ['title' => '', 'assignee_id' => (string) $other->id])
            ->assertRedirect(route('tasks.quick'));

        $this->get(route('tasks.quick'))
            ->assertOk()
            ->assertSee('<option value="'.$other->id.'" selected', false);
    }

    public function test_the_layout_ships_team_and_project_colours_for_the_dots(): void
    {
        $lead = User::factory()->create(['role' => User::ROLE_TEAM_LEAD]);
        $team = Team::create(['name' => 'Core', 'color' => '#0891b2']);

        $this->actingAs($lead)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('<script type="application/json" id="ss-swatches">', false)
            ->assertSee('"'.$team->id.'":"#0891b2"', false);
    }

    public function test_the_enhancer_ships_with_the_app_bundle(): void
    {
        $this->assertStringContainsString("import './searchable-select';", file_get_contents(resource_path('js/app.js')));
        $this->assertFileExists(resource_path('js/searchable-select.js'));
    }
}
