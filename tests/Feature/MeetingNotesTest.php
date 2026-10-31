<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\MeetingNote;
use App\Models\Project;
use App\Models\Release;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeetingNotesTest extends TestCase
{
    use RefreshDatabase;

    private function release(string $name = 'R'): Release
    {
        $project = Project::create(['name' => 'P', 'color' => '#4f46e5']);
        $team = Team::create(['name' => 'T '.$name, 'color' => '#0891b2']);

        return Release::create([
            'project_id' => $project->id, 'team_id' => $team->id, 'name' => $name,
            'year' => 2026, 'quarter' => 3, 'start_date' => '2026-07-10', 'end_date' => '2026-07-30',
        ]);
    }

    private function note(User $author, array $attrs = []): MeetingNote
    {
        return MeetingNote::create(array_merge([
            'created_by' => $author->id, 'title' => 'Sync', 'meeting_date' => '2026-07-22', 'body' => 'Minutes',
        ], $attrs));
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Sprint retro', 'meeting_date' => '2026-07-22', 'body' => 'What went well…',
        ], $overrides);
    }

    public function test_user_can_create_general_meeting_note(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_DEVELOPER]);

        $this->actingAs($user)->post(route('meeting-notes.store'), $this->payload())
            ->assertRedirect();

        $note = MeetingNote::first();
        $this->assertSame('Sprint retro', $note->title);
        $this->assertSame($user->id, $note->created_by);
        $this->assertNull($note->release_id);
    }

    public function test_user_can_create_release_linked_meeting_note(): void
    {
        $release = $this->release();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('meeting-notes.store'), $this->payload(['release_id' => $release->id]))
            ->assertRedirect();

        $this->assertSame($release->id, MeetingNote::first()->release_id);
    }

    public function test_missing_title_date_or_body_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('meeting-notes.store'), ['title' => '', 'meeting_date' => '', 'body' => ''])
            ->assertSessionHasErrors(['title', 'meeting_date', 'body']);

        $this->assertSame(0, MeetingNote::count());
    }

    public function test_visually_empty_html_body_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('meeting-notes.store'), $this->payload(['body' => '<div><br></div>']))
            ->assertSessionHasErrors('body');
    }

    public function test_rich_text_is_sanitized(): void
    {
        $this->actingAs(User::factory()->create())->post(route('meeting-notes.store'), $this->payload([
            'body' => '<strong>Hi</strong><script>alert(1)</script><a href="javascript:alert(2)">x</a>',
        ]))->assertRedirect();

        $body = MeetingNote::first()->body;
        $this->assertStringContainsString('<strong>Hi</strong>', $body);
        $this->assertStringNotContainsString('<script>', $body);
        $this->assertStringNotContainsString('javascript:', $body);
    }

    public function test_index_filters_by_release_and_general(): void
    {
        $release = $this->release();
        $author = User::factory()->create();

        $this->note($author, ['title' => 'Release sync', 'release_id' => $release->id]);
        $this->note($author, ['title' => 'All hands']);

        $viewer = User::factory()->create(['role' => User::ROLE_VIEWER]);

        $this->actingAs($viewer)->get(route('meeting-notes.index'))
            ->assertOk()->assertSee('Release sync')->assertSee('All hands');

        $this->actingAs($viewer)->get(route('meeting-notes.index', ['release' => $release->id]))
            ->assertOk()->assertSee('Release sync')->assertDontSee('All hands');

        $this->actingAs($viewer)->get(route('meeting-notes.index', ['release' => 'general']))
            ->assertOk()->assertSee('All hands')->assertDontSee('Release sync');
    }

    public function test_index_filters_by_date_range_and_tolerates_reversed_span(): void
    {
        $author = User::factory()->create();
        $this->note($author, ['title' => 'Before span', 'meeting_date' => '2026-07-10']);
        $this->note($author, ['title' => 'Inside span', 'meeting_date' => '2026-07-15']);
        $this->note($author, ['title' => 'Also within', 'meeting_date' => '2026-07-20']);
        $this->note($author, ['title' => 'After span', 'meeting_date' => '2026-07-25']);

        $this->actingAs($author)->get(route('meeting-notes.index', ['from' => '2026-07-14', 'to' => '2026-07-21']))
            ->assertOk()
            ->assertSee('Inside span')->assertSee('Also within')
            ->assertDontSee('Before span')->assertDontSee('After span');

        $this->actingAs($author)->get(route('meeting-notes.index', ['from' => '2026-07-21', 'to' => '2026-07-14']))
            ->assertOk()->assertSee('Inside span')->assertDontSee('Before span');
    }

    public function test_index_combines_release_and_date_filters(): void
    {
        $release = $this->release();
        $author = User::factory()->create();
        $this->note($author, ['title' => 'Linked in span', 'release_id' => $release->id, 'meeting_date' => '2026-07-15']);
        $this->note($author, ['title' => 'Linked out of span', 'release_id' => $release->id, 'meeting_date' => '2026-07-25']);
        $this->note($author, ['title' => 'General in span', 'meeting_date' => '2026-07-15']);

        $this->actingAs($author)->get(route('meeting-notes.index', [
            'release' => $release->id, 'from' => '2026-07-14', 'to' => '2026-07-21',
        ]))->assertOk()
            ->assertSee('Linked in span')
            ->assertDontSee('Linked out of span')
            ->assertDontSee('General in span');
    }

    public function test_release_show_page_lists_its_meeting_notes(): void
    {
        $release = $this->release();
        $this->note(User::factory()->create(), ['title' => 'Kickoff minutes', 'release_id' => $release->id]);

        $this->actingAs(User::factory()->create())
            ->get(route('releases.show', $release))
            ->assertOk()
            ->assertSee('Kickoff minutes');
    }

    public function test_only_author_can_edit(): void
    {
        $author = User::factory()->create();
        $note = $this->note($author);
        $other = User::factory()->create();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($other)->put(route('meeting-notes.update', $note), $this->payload(['title' => 'hijack']))
            ->assertForbidden();
        $this->actingAs($admin)->put(route('meeting-notes.update', $note), $this->payload(['title' => 'hijack']))
            ->assertForbidden();

        $this->actingAs($author)->put(route('meeting-notes.update', $note), $this->payload(['title' => 'Edited']))
            ->assertRedirect();
        $this->assertSame('Edited', $note->refresh()->title);
    }

    public function test_author_and_admin_can_delete_but_others_cannot(): void
    {
        $author = User::factory()->create();
        $other = User::factory()->create();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $note = $this->note($author);
        $this->actingAs($other)->delete(route('meeting-notes.destroy', $note))->assertForbidden();
        $this->actingAs($author)->delete(route('meeting-notes.destroy', $note))->assertRedirect();
        $this->assertSame(0, MeetingNote::count());

        $note = $this->note($author);
        $this->actingAs($admin)->delete(route('meeting-notes.destroy', $note))->assertRedirect();
        $this->assertSame(0, MeetingNote::count());
    }

    public function test_deleting_release_keeps_notes_as_general(): void
    {
        $release = $this->release();
        $note = $this->note(User::factory()->create(), ['release_id' => $release->id]);

        $release->delete();

        $this->assertNull($note->refresh()->release_id);
        $this->assertSame(1, MeetingNote::count());
    }

    public function test_meeting_notes_require_auth(): void
    {
        $this->get(route('meeting-notes.index'))->assertRedirect(route('login'));
    }

    public function test_create_form_lists_only_ongoing_releases(): void
    {
        $this->release('Ongoing sprint');
        $this->release('Shipped release')->update(['completed_at' => now()]);

        $this->actingAs(User::factory()->create())
            ->get(route('meeting-notes.create'))
            ->assertOk()
            ->assertSee('Ongoing sprint')
            ->assertDontSee('Shipped release');
    }

    public function test_store_rejects_completed_release(): void
    {
        $release = $this->release();
        $release->update(['completed_at' => now()]);

        $this->actingAs(User::factory()->create())
            ->post(route('meeting-notes.store'), $this->payload(['release_id' => $release->id]))
            ->assertSessionHasErrors('release_id');

        $this->assertSame(0, MeetingNote::count());
    }

    public function test_meeting_event_offers_write_meeting_note_but_other_types_do_not(): void
    {
        $creator = User::factory()->create();
        $release = $this->release();

        $meeting = Event::create([
            'title' => 'Sprint planning', 'type' => 'meeting',
            'starts_at' => '2026-07-24 10:00:00', 'release_id' => $release->id, 'created_by' => $creator->id,
        ]);
        $deadline = Event::create([
            'title' => 'Code freeze', 'type' => 'deadline',
            'starts_at' => '2026-07-25 10:00:00', 'created_by' => $creator->id,
        ]);

        $this->actingAs($creator)->get(route('events.show', $meeting))
            ->assertOk()->assertSee('Write meeting note');

        $this->actingAs($creator)->get(route('events.show', $deadline))
            ->assertOk()->assertDontSee('Write meeting note');
    }

    public function test_create_form_prefills_from_event_and_links_it(): void
    {
        $release = $this->release('Ongoing sprint');
        $event = Event::create([
            'title' => 'Sprint planning', 'type' => 'meeting',
            'starts_at' => '2026-07-24 10:00:00', 'release_id' => $release->id,
            'created_by' => User::factory()->create()->id,
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('meeting-notes.create', ['event' => $event->id]))
            ->assertOk()
            ->assertSee('value="Sprint planning"', false)
            ->assertSee('value="2026-07-24"', false)
            ->assertSee('name="event_id" value="'.$event->id.'"', false)
            ->assertSee('Linked to event');
    }

    public function test_note_linked_to_release_and_event_is_visible_on_both_pages(): void
    {
        $release = $this->release();
        $author = User::factory()->create();
        $event = Event::create([
            'title' => 'Sprint planning', 'type' => 'meeting',
            'starts_at' => '2026-07-24 10:00:00', 'release_id' => $release->id, 'created_by' => $author->id,
        ]);

        $this->actingAs($author)->post(route('meeting-notes.store'), $this->payload([
            'title' => 'Planning minutes', 'release_id' => $release->id, 'event_id' => $event->id,
        ]))->assertRedirect();

        $note = MeetingNote::first();
        $this->assertSame($event->id, $note->event_id);
        $this->assertSame($release->id, $note->release_id);

        // Same note, visible from both details pages.
        $this->actingAs($author)->get(route('releases.show', $release))
            ->assertOk()->assertSee('Planning minutes');
        $this->actingAs($author)->get(route('events.show', $event))
            ->assertOk()->assertSee('Planning minutes');
    }

    public function test_deleting_event_keeps_notes(): void
    {
        $release = $this->release();
        $author = User::factory()->create();
        $event = Event::create([
            'title' => 'Sync', 'type' => 'meeting',
            'starts_at' => '2026-07-24 10:00:00', 'created_by' => $author->id,
        ]);
        $note = $this->note($author, ['release_id' => $release->id, 'event_id' => $event->id]);

        $event->delete();

        $note->refresh();
        $this->assertNull($note->event_id);
        $this->assertSame($release->id, $note->release_id);
        $this->assertSame(1, MeetingNote::count());
    }

    public function test_note_keeps_link_to_since_completed_release(): void
    {
        $release = $this->release('Shipped release');
        $author = User::factory()->create();
        $note = $this->note($author, ['release_id' => $release->id]);

        $release->update(['completed_at' => now()]);

        // The edit form still offers the note's own (completed) release…
        $this->actingAs($author)->get(route('meeting-notes.edit', $note))
            ->assertOk()->assertSee('Shipped release');

        // …and saving without changing the link succeeds.
        $this->actingAs($author)
            ->put(route('meeting-notes.update', $note), $this->payload(['title' => 'Edited', 'release_id' => $release->id]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertSame($release->id, $note->refresh()->release_id);
    }
}
