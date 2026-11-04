<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Release;
use App\Models\ReleaseTemplate;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Standard task sets that a release can start from.
 */
class ReleaseTemplateTest extends TestCase
{
    use RefreshDatabase;

    private User $lead;

    private Release $release;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lead = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $project = Project::create(['name' => 'P', 'color' => '#4f46e5']);
        $team = Team::create(['name' => 'Core', 'color' => '#0891b2']);
        $this->release = Release::create(['project_id' => $project->id, 'team_id' => $team->id, 'name' => 'R1', 'year' => 2026, 'quarter' => 4, 'start_date' => '2026-11-01', 'end_date' => '2026-12-31']);
        $this->release->phases()->create(['phase' => 'qa', 'position' => 1, 'start_date' => '2026-12-01', 'end_date' => '2026-12-15']);
    }

    private function template(array $tasks = [['title' => 'Regression pass', 'phase' => 'qa', 'priority' => 'high', 'estimate_minutes' => 120]]): ReleaseTemplate
    {
        $template = ReleaseTemplate::create(['name' => 'Standard', 'created_by' => $this->lead->id]);
        foreach ($tasks as $i => $task) {
            $template->tasks()->create($task + ['position' => $i]);
        }

        return $template;
    }

    public function test_a_lead_builds_a_template_on_the_web(): void
    {
        $this->actingAs($this->lead)->from(route('release-templates.index'))->post(route('release-templates.store'), ['name' => 'Standard'])
            ->assertRedirect(route('release-templates.index'));
        $template = ReleaseTemplate::sole();

        $this->post(route('release-templates.tasks.store', $template), ['title' => 'Smoke test', 'phase' => 'qa', 'priority' => 'high', 'estimate' => '2h'])->assertRedirect();

        $task = $template->tasks()->sole();
        $this->assertSame(120, $task->estimate_minutes);

        $this->get(route('release-templates.index'))->assertOk()
            ->assertSee('id="release-templates"', false)
            ->assertSee('Smoke test')
            ->assertSee('2h');

        $this->delete(route('release-templates.tasks.destroy', [$template, $task]))->assertRedirect();
        $this->assertSame(0, $template->tasks()->count());
    }

    public function test_template_names_are_unique(): void
    {
        $this->template();

        $this->actingAs($this->lead)->post(route('release-templates.store'), ['name' => 'Standard'])->assertSessionHasErrors('name');
    }

    public function test_a_task_cannot_be_removed_through_another_template(): void
    {
        $template = $this->template();
        $other = ReleaseTemplate::create(['name' => 'Other']);

        $this->actingAs($this->lead)->delete(route('release-templates.tasks.destroy', [$other, $template->tasks()->first()]))->assertNotFound();

        $this->assertSame(1, $template->tasks()->count());
    }

    public function test_bad_template_tasks_are_refused(): void
    {
        $template = $this->template();
        Sanctum::actingAs($this->lead);

        $this->postJson("/api/v1/release-templates/{$template->id}/tasks", [])->assertStatus(422)->assertJsonValidationErrors('title');
        $this->postJson("/api/v1/release-templates/{$template->id}/tasks", ['title' => 'X', 'phase' => 'nope'])->assertStatus(422)->assertJsonValidationErrors('phase');
        $this->postJson("/api/v1/release-templates/{$template->id}/tasks", ['title' => 'X', 'estimate' => 'soon'])->assertStatus(422)->assertJsonValidationErrors('estimate_minutes');
    }

    public function test_applying_adds_tasks_due_when_their_phase_ends(): void
    {
        $template = $this->template([
            ['title' => 'Regression pass', 'phase' => 'qa', 'priority' => 'high', 'estimate_minutes' => 120],
            ['title' => 'No phase work'],
        ]);

        $this->actingAs($this->lead)->from(route('releases.show', $this->release))
            ->post(route('releases.apply-template', $this->release), ['template_id' => $template->id])
            ->assertRedirect(route('releases.show', $this->release))
            ->assertSessionHas('success', '2 tasks added from the template.');

        $task = Task::where('title', 'Regression pass')->sole();
        $this->assertSame($this->release->id, $task->release_id);
        $this->assertSame($this->release->team_id, $task->team_id);
        $this->assertSame('qa', $task->phase);
        $this->assertSame('high', $task->priority);
        $this->assertSame(120, $task->estimate_minutes);
        $this->assertSame('2026-12-15', $task->due_date->toDateString());
        $this->assertSame($this->lead->id, $task->created_by);
        $this->assertNull(Task::where('title', 'No phase work')->sole()->due_date);
    }

    public function test_applying_twice_skips_tasks_already_there(): void
    {
        $template = $this->template();
        $this->actingAs($this->lead);

        $this->post(route('releases.apply-template', $this->release), ['template_id' => $template->id]);
        $this->post(route('releases.apply-template', $this->release), ['template_id' => $template->id])
            ->assertSessionHas('success', 'This release already has those tasks.');

        $this->assertSame(1, Task::count());
    }

    public function test_removing_a_template_keeps_tasks_already_made(): void
    {
        $template = $this->template();
        $this->actingAs($this->lead)->post(route('releases.apply-template', $this->release), ['template_id' => $template->id]);

        $this->delete(route('release-templates.destroy', $template))->assertRedirect();

        $this->assertSame(0, ReleaseTemplate::count());
        $this->assertDatabaseCount('release_template_tasks', 0);
        $this->assertSame(1, Task::count());
    }

    public function test_the_release_page_offers_templates_to_leads_only(): void
    {
        $this->template();
        $this->actingAs($this->lead)->get(route('releases.show', $this->release))->assertOk()
            ->assertSee('Add tasks from a template')
            ->assertSee('Standard (1)');

        $this->app['auth']->forgetGuards();
        $viewer = User::factory()->create(['role' => User::ROLE_VIEWER]);
        $this->actingAs($viewer)->get(route('releases.show', $this->release))->assertOk()
            ->assertDontSee('Add tasks from a template');
    }

    /** @return array<string, array{0: string}> */
    public static function nonLeads(): array
    {
        return ['developer' => [User::ROLE_DEVELOPER], 'qa' => [User::ROLE_QA], 'viewer' => [User::ROLE_VIEWER]];
    }

    #[DataProvider('nonLeads')]
    public function test_only_leads_manage_and_apply(string $role): void
    {
        $user = User::factory()->create(['role' => $role]);
        $template = $this->template();

        $this->actingAs($user)->get(route('release-templates.index'))->assertForbidden();
        $this->post(route('release-templates.store'), ['name' => 'X'])->assertForbidden();
        $this->post(route('releases.apply-template', $this->release), ['template_id' => $template->id])->assertForbidden();

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($user);
        $this->postJson("/api/v1/releases/{$this->release->id}/apply-template", ['template_id' => $template->id])->assertForbidden();

        $this->assertSame(0, Task::count());
    }

    public function test_the_api_mirrors_the_web(): void
    {
        Sanctum::actingAs($this->lead);

        $id = $this->postJson('/api/v1/release-templates', ['name' => 'Client'])->assertCreated()->json('data.id');
        $task = $this->postJson("/api/v1/release-templates/{$id}/tasks", ['title' => 'Smoke test', 'phase' => 'qa', 'estimate_minutes' => 60])->assertCreated()
            ->assertJsonPath('data.tasks.0.title', 'Smoke test')
            ->json('data.tasks.0.id');
        $this->getJson('/api/v1/release-templates')->assertOk()->assertJsonPath('data.0.id', $id);

        $this->postJson("/api/v1/releases/{$this->release->id}/apply-template", ['template_id' => $id])->assertOk()->assertJsonPath('data.created', 1);
        $this->postJson("/api/v1/releases/{$this->release->id}/apply-template", ['template_id' => 999999])->assertStatus(422);

        $this->deleteJson("/api/v1/release-templates/{$id}/tasks/{$task}")->assertOk();
        $this->deleteJson("/api/v1/release-templates/{$id}")->assertOk();
    }
}
