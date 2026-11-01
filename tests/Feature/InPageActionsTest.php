<?php

namespace Tests\Feature;

use App\Models\Note;
use App\Models\PerformanceCompetency;
use App\Models\Project;
use App\Models\QuickLink;
use App\Models\Release;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * In-page actions (<x-live-region>): a form inside a region is submitted by
 * Turbo with a Turbo-Frame header, the controller answers with its usual
 * redirect back, and the client cuts the region (plus the #flash-toasts
 * payload) out of the page it lands on. These tests pin that contract.
 */
class InPageActionsTest extends TestCase
{
    use RefreshDatabase;

    /** Headers Turbo sends for a form submission that targets a frame. */
    private function frameHeaders(string $frame): array
    {
        return [
            'Accept' => 'text/vnd.turbo-stream.html, text/html, application/xhtml+xml',
            'Turbo-Frame' => $frame,
        ];
    }

    /** The inner HTML of the live region with this id. */
    private function region(TestResponse $response, string $id): string
    {
        $html = $response->getContent();
        $this->assertMatchesRegularExpression('/<turbo-frame id="'.preg_quote($id, '/').'" target="_top" data-live-region/', $html);
        preg_match('/<turbo-frame id="'.preg_quote($id, '/').'"[^>]*>(.*?)<\/turbo-frame>/s', $html, $m);

        return $m[1] ?? '';
    }

    /** The flash toasts the layout hands to the client for region updates. */
    private function flashToasts(TestResponse $response): array
    {
        preg_match('/<script type="application\/json" id="flash-toasts">(.*?)<\/script>/s', $response->getContent(), $m);
        $this->assertNotEmpty($m, 'The #flash-toasts payload is missing.');

        return json_decode($m[1], true);
    }

    private function release(): Release
    {
        $project = Project::create(['name' => 'P', 'color' => '#4f46e5']);
        $team = Team::create(['name' => 'T', 'color' => '#0891b2']);

        return Release::create([
            'project_id' => $project->id, 'team_id' => $team->id, 'name' => 'R',
            'year' => 2026, 'quarter' => 3, 'start_date' => '2026-07-10', 'end_date' => '2026-07-30',
        ]);
    }

    public function test_converted_pages_render_their_live_regions(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $release = $this->release();

        $this->region($this->actingAs($admin)->get(route('notes.index'))->assertOk(), 'notes');
        $this->region($this->actingAs($admin)->get(route('users.index'))->assertOk(), 'users');
        $this->region($this->actingAs($admin)->get(route('performance.competencies.index'))->assertOk(), 'competencies');
        $this->region($this->actingAs($admin)->get(route('dashboard'))->assertOk(), 'quick-links');

        $show = $this->actingAs($admin)->get(route('releases.show', $release))->assertOk();
        $this->region($show, 'release-tasks');
        $this->region($show, 'release-comments');
    }

    public function test_toggling_a_user_redirects_back_to_the_filtered_list_with_the_row_updated(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $target = User::factory()->create(['name' => 'Target Person', 'role' => User::ROLE_DEVELOPER]);
        $list = route('users.index', ['q' => 'Target']);

        $this->actingAs($admin)->from($list)
            ->withHeaders($this->frameHeaders('users'))
            ->post(route('users.toggle', $target))
            ->assertRedirect($list);

        $page = $this->actingAs($admin)->get($list);
        $this->assertStringContainsString('Reactivate Target Person', $this->region($page, 'users'));
        $this->assertSame('success', $this->flashToasts($page)[0]['type']);
        $this->assertFalse($target->fresh()->isActive());
    }

    public function test_toggling_a_competency_updates_its_row(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $comp = PerformanceCompetency::create([
            'key' => 'k-1', 'name' => 'Code quality',
            'category' => PerformanceCompetency::CATEGORY_TECHNICAL,
            'role_scope' => PerformanceCompetency::SCOPE_BOTH,
            'cadence' => PerformanceCompetency::CADENCE_WEEKLY,
            'weight' => 1, 'active' => true, 'position' => 0,
        ]);
        $list = route('performance.competencies.index');

        $this->actingAs($admin)->from($list)
            ->withHeaders($this->frameHeaders('competencies'))
            ->post(route('performance.competencies.toggle', $comp))
            ->assertRedirect($list);

        $region = $this->region($this->actingAs($admin)->get($list), 'competencies');
        $this->assertStringContainsString('Inactive', $region);
        $this->assertStringContainsString('>Activate</button>', $region);
    }

    public function test_pinning_a_quick_link_updates_the_drawer_region_and_flashes_a_toast(): void
    {
        $user = User::factory()->create();
        $link = QuickLink::create([
            'user_id' => $user->id, 'label' => 'Runbook', 'url' => 'https://example.com', 'visibility' => 'private',
        ]);
        $page = route('dashboard');

        $this->actingAs($user)->from($page)
            ->withHeaders($this->frameHeaders('quick-links'))
            ->post(route('quick-links.pin', $link))
            ->assertRedirect($page);

        $response = $this->actingAs($user)->get($page);
        $this->assertStringContainsString('Runbook', $this->region($response, 'quick-links'));
        $this->assertSame([['type' => 'success', 'message' => 'Link pinned.']], $this->flashToasts($response));
    }

    public function test_adding_a_note_lands_on_the_feed_with_the_note_in_the_region(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->from(route('notes.index'))
            ->withHeaders($this->frameHeaders('notes'))
            ->post(route('notes.store'), ['date' => now()->toDateString(), 'body' => 'Shipped the frame demo', 'visibility' => 'private'])
            ->assertRedirect(route('notes.index'));

        $response = $this->actingAs($user)->get(route('notes.index'));
        $this->assertStringContainsString('Shipped the frame demo', $this->region($response, 'notes'));
        $this->assertSame('Note added.', $this->flashToasts($response)[0]['message']);
        $this->assertSame(1, Note::count());
    }

    public function test_an_invalid_note_comes_back_to_the_region_with_inline_errors(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->from(route('notes.index'))
            ->withHeaders($this->frameHeaders('notes'))
            ->post(route('notes.store'), ['date' => now()->toDateString(), 'body' => '', 'visibility' => 'private'])
            ->assertRedirect(route('notes.index'));
        // (No assertSessionHasErrors here: it ages the flash, hiding the errors from the next request.)

        $region = $this->region($this->actingAs($user)->get(route('notes.index')), 'notes');
        $this->assertStringContainsString('aria-invalid="true"', $region);
        $this->assertStringContainsString('id="body-error"', $region);
        $this->assertSame(0, Note::count());
    }

    public function test_adding_a_task_on_a_release_updates_the_tasks_region(): void
    {
        $release = $this->release();
        $user = User::factory()->create(['role' => User::ROLE_VIEWER]);
        $page = route('releases.show', $release);

        $this->actingAs($user)->from($page)
            ->withHeaders($this->frameHeaders('release-tasks'))
            ->post(route('releases.tasks.store', $release), ['title' => 'Wire up frames'])
            ->assertRedirect($page);

        $region = $this->region($this->actingAs($user)->get($page), 'release-tasks');
        $this->assertStringContainsString('Wire up frames', $region);
        // The card header's count badge reflects the new task.
        $this->assertMatchesRegularExpression('/Tasks\s*<span class="badge badge-slate tabular">1<\/span>/', $region);
    }

    public function test_flash_toast_payload_cannot_break_out_of_its_script_tag(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withSession(['success' => '</script><img src=x onerror=alert(1)>'])
            ->get(route('dashboard'));

        $this->assertStringNotContainsString('</script><img', $response->getContent());
        $this->assertSame('</script><img src=x onerror=alert(1)>', $this->flashToasts($response)[0]['message']);
    }
}
