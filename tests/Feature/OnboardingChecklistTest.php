<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The dashboard "Get started" card (openspec/changes/onboarding-checklist).
 */
class OnboardingChecklistTest extends TestCase
{
    use RefreshDatabase;

    private User $dev;

    protected function setUp(): void
    {
        parent::setUp();

        $team = Team::create(['name' => 'Core', 'color' => '#0891b2']);
        $this->dev = User::factory()->create(['role' => User::ROLE_DEVELOPER, 'name' => 'Dana Dev']);
        $team->members()->attach($this->dev);
    }

    public function test_a_new_developer_sees_four_steps_with_none_done(): void
    {
        $this->actingAs($this->dev)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Dana, a few quick steps to feel at home')
            ->assertSee('0 of 4')
            ->assertSeeInOrder(['Add your first task', 'Finish a task', 'Join the conversation', 'Log today’s tasksheet']);
    }

    public function test_steps_tick_themselves_off_from_real_data(): void
    {
        Task::create(['title' => 'Mine', 'status' => 'todo', 'position' => 0, 'created_by' => $this->dev->id]);

        $this->actingAs($this->dev)->get(route('dashboard'))->assertOk()->assertSee('1 of 4');
    }

    public function test_leads_and_viewers_get_role_appropriate_steps(): void
    {
        $lead = User::factory()->create(['role' => User::ROLE_TEAM_LEAD]);
        $viewer = User::factory()->create(['role' => User::ROLE_VIEWER]);

        $this->actingAs($lead)->get(route('dashboard'))->assertOk()->assertSee('Plan a release')->assertSee('0 of 4');
        $this->actingAs($viewer)->get(route('dashboard'))->assertOk()->assertSee('0 of 3')->assertDontSee('Plan a release');
    }

    public function test_dismissing_hides_the_card_for_good_on_web_and_api(): void
    {
        $this->actingAs($this->dev)->post(route('onboarding.dismiss'))->assertRedirect();

        $this->get(route('dashboard'))->assertOk()->assertDontSee('id="onboarding-title"', false);

        Sanctum::actingAs($this->dev);
        $this->getJson(route('api-v1.onboarding.show'))->assertOk()->assertJsonPath('data.visible', false);
    }

    public function test_the_api_lists_steps_and_can_dismiss(): void
    {
        Sanctum::actingAs($this->dev);

        $this->getJson(route('api-v1.onboarding.show'))
            ->assertOk()
            ->assertJsonPath('data.visible', true)
            ->assertJsonCount(4, 'data.steps')
            ->assertJsonPath('data.steps.0.key', 'add_task')
            ->assertJsonPath('data.steps.0.done', false);

        $this->postJson(route('api-v1.onboarding.dismiss'))->assertOk()->assertJsonPath('data.visible', false);

        $this->actingAs($this->dev, 'web')->get(route('dashboard'))->assertDontSee('id="onboarding-title"', false);
    }

    public function test_the_card_is_gone_once_every_step_is_done(): void
    {
        $viewer = User::factory()->create(['role' => User::ROLE_VIEWER]);
        $task = Task::create(['title' => 'Done', 'status' => 'done', 'position' => 0, 'created_by' => $viewer->id, 'assignee_id' => $viewer->id]);
        $task->comments()->create(['user_id' => $viewer->id, 'body' => 'Shipped']);

        $this->actingAs($viewer)->get(route('dashboard'))->assertOk()->assertDontSee('id="onboarding-title"', false);
    }
}
