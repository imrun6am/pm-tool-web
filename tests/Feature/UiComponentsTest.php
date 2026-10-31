<?php

namespace Tests\Feature;

use App\Models\MeetingNote;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UiComponentsTest extends TestCase
{
    use RefreshDatabase;

    private function postNote(User $user, string $date, string $body): void
    {
        $this->actingAs($user)->post(route('notes.store'), [
            'date' => $date, 'body' => "<div>{$body}</div>", 'visibility' => 'private',
        ])->assertSessionHasNoErrors();
    }

    public function test_notes_feed_groups_by_day_newest_first(): void
    {
        $user = User::factory()->create();
        $this->postNote($user, now()->subDays(3)->toDateString(), 'Older note');
        $this->postNote($user, now()->subDay()->toDateString(), 'Yesterday note');
        $this->postNote($user, now()->toDateString(), 'First today note');
        $this->postNote($user, now()->toDateString(), 'Second today note');

        $this->actingAs($user)->get(route('notes.index'))
            ->assertOk()
            ->assertSeeInOrder([
                'Today', 'Second today note', 'First today note',
                'Yesterday', 'Yesterday note',
                now()->subDays(3)->format('D, M j'), 'Older note',
            ]);
    }

    public function test_notes_composer_reopens_with_the_error_after_failed_submit(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('notes.index'))
            ->assertSee('x-data="{ open: false }"', false);

        $this->actingAs($user)
            ->from(route('notes.index'))
            ->followingRedirects()
            ->post(route('notes.store'), ['date' => now()->toDateString(), 'body' => '', 'visibility' => 'private'])
            ->assertSee('x-data="{ open: true }"', false)
            ->assertSee('aria-invalid="true" aria-describedby="body-error"', false)
            ->assertSee('id="body-error"', false);
    }

    public function test_filter_bar_offers_clear_only_while_filtering(): void
    {
        $user = User::factory()->create();
        MeetingNote::create([
            'created_by' => $user->id, 'title' => 'Sync', 'meeting_date' => now()->toDateString(), 'body' => 'Minutes',
        ]);
        $clearLink = '#<a href="'.preg_quote(route('meeting-notes.index'), '#').'"[^>]*>\s*<svg[^>]*>.*?</svg>\s*Clear\s*</a>#s';

        $unfiltered = $this->actingAs($user)->get(route('meeting-notes.index'))->assertOk()->getContent();
        $this->assertDoesNotMatchRegularExpression($clearLink, $unfiltered);
        $this->assertStringContainsString('Apply filters', $unfiltered); // keyboard / no-JS submit stays

        $filtered = $this->actingAs($user)->get(route('meeting-notes.index', ['type' => 'sync_up']))->assertOk()->getContent();
        $this->assertMatchesRegularExpression($clearLink, $filtered);

        $lead = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $releasesClear = '#<a href="'.preg_quote(route('releases.index'), '#').'"[^>]*>\s*<svg[^>]*>.*?</svg>\s*Clear\s*</a>#s';
        $this->assertDoesNotMatchRegularExpression($releasesClear, $this->actingAs($lead)->get(route('releases.index'))->assertOk()->getContent());
        $this->assertMatchesRegularExpression($releasesClear, $this->actingAs($lead)->get(route('releases.index', ['status' => 'completed']))->assertOk()->getContent());

        $notesFiltered = $this->actingAs($user)->get(route('notes.index', ['author' => $user->id]))->getContent();
        $this->assertMatchesRegularExpression(
            '#<a href="'.preg_quote(route('notes.index'), '#').'"[^>]*>\s*<svg[^>]*>.*?</svg>\s*Clear\s*</a>#s',
            $notesFiltered,
        );
    }

    public function test_invalid_field_is_wired_to_its_inline_error(): void
    {
        $lead = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($lead)
            ->from(route('projects.create'))
            ->followingRedirects()
            ->post(route('projects.store'), ['name' => '', 'color' => '#0d9488'])
            ->assertSee('aria-invalid="true" aria-describedby="name-error"', false)
            ->assertSee('id="name-error"', false)
            ->assertSee('The name field is required.')
            // The chosen color survives the failed submit.
            ->assertSee('value="#0d9488"', false);
    }

    public function test_form_pages_render_breadcrumbs_and_verb_submit_labels(): void
    {
        $lead = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $project = Project::create(['name' => 'Checkout', 'color' => '#4f46e5']);

        $this->actingAs($lead)->get(route('projects.create'))
            ->assertOk()
            ->assertSee('aria-label="Breadcrumb"', false)
            ->assertSeeInOrder(['Projects', 'New project'])
            ->assertSee('Create project');

        $this->actingAs($lead)->get(route('projects.edit', $project))
            ->assertOk()
            ->assertSee('href="'.route('projects.show', $project).'"', false)
            ->assertSeeInOrder(['Projects', 'Checkout', 'Edit'])
            ->assertSee('aria-current="page"', false)
            ->assertSee('Save changes');

        $team = Team::create(['name' => 'Alpha', 'color' => '#0891b2']);
        $this->actingAs($lead)->get(route('teams.create'))->assertOk()->assertSee('Create team');
        $this->actingAs($lead)->get(route('teams.edit', $team))->assertOk()
            ->assertSeeInOrder(['Teams', 'Alpha', 'Edit'])
            ->assertSee('Save changes');
    }

    public function test_avatar_color_is_stable_per_user(): void
    {
        $user = User::factory()->create(['name' => 'Ada Lovelace']);

        $render = fn () => (string) $this->blade('<x-avatar :user="$u" />', ['u' => $user]);

        $this->assertSame($render(), $render());
        $this->assertStringContainsString('>AL</span>', $render());
    }
}
