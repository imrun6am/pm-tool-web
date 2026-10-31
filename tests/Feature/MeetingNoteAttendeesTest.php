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

class MeetingNoteAttendeesTest extends TestCase
{
    use RefreshDatabase;

    private function release(): Release
    {
        $project = Project::create(['name' => 'P', 'color' => '#4f46e5']);
        $team = Team::create(['name' => 'T', 'color' => '#0891b2']);

        return Release::create([
            'project_id' => $project->id, 'team_id' => $team->id, 'name' => 'Checkout',
            'year' => 2026, 'quarter' => 3, 'start_date' => '2026-07-10', 'end_date' => '2026-07-30',
        ]);
    }

    private function note(User $author, array $attrs = [], array $attendees = []): MeetingNote
    {
        $note = MeetingNote::create(array_merge([
            'created_by' => $author->id, 'title' => 'Sync', 'meeting_date' => '2026-07-22', 'body' => 'Minutes',
        ], $attrs));
        $note->attendees()->sync($attendees);

        return $note;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Retro', 'meeting_date' => '2026-07-22', 'body' => 'What went well…',
        ], $overrides);
    }

    public function test_store_syncs_attendees_and_persists_visibility(): void
    {
        $author = User::factory()->create();
        $a = User::factory()->create();
        $b = User::factory()->create();

        $this->actingAs($author)->post(route('meeting-notes.store'), $this->payload([
            'attendees' => [$a->id, $b->id], 'visibility' => 'attendees',
        ]))->assertRedirect();

        $note = MeetingNote::first();
        $this->assertSame('attendees', $note->visibility);
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $note->attendees->pluck('id')->all());
    }

    public function test_update_syncs_attendees(): void
    {
        $author = User::factory()->create();
        $a = User::factory()->create();
        $b = User::factory()->create();
        $note = $this->note($author, [], [$a->id]);

        $this->actingAs($author)->put(route('meeting-notes.update', $note), $this->payload([
            'attendees' => [$b->id],
        ]))->assertRedirect();

        $this->assertEqualsCanonicalizing([$b->id], $note->refresh()->attendees->pluck('id')->all());
    }

    public function test_invalid_visibility_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('meeting-notes.store'), $this->payload(['visibility' => 'nope']))
            ->assertSessionHasErrors('visibility');
        $this->assertSame(0, MeetingNote::count());
    }

    public function test_create_form_prefills_attendees_from_event(): void
    {
        $creator = User::factory()->create();
        $attendee = User::factory()->create(['name' => 'Rafi Attendee']);
        $event = Event::create([
            'title' => 'Planning', 'type' => 'meeting',
            'starts_at' => '2026-07-24 10:00:00', 'created_by' => $creator->id,
        ]);
        $event->attendees()->attach($attendee);

        $this->actingAs($creator)->get(route('meeting-notes.create', ['event' => $event->id]))
            ->assertOk()
            ->assertSee('data-selected="'.$attendee->id.'"', false) // preselected in the multi-select
            ->assertSee('Rafi Attendee');                            // option present
    }

    public function test_attendees_only_note_view_access(): void
    {
        $author = User::factory()->create(['role' => User::ROLE_VIEWER]);
        $attendee = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $outsider = User::factory()->create(['role' => User::ROLE_VIEWER]);
        $lead = User::factory()->create(['role' => User::ROLE_TEAM_LEAD]);

        $note = $this->note($author, ['visibility' => 'attendees'], [$attendee->id]);

        $this->actingAs($attendee)->get(route('meeting-notes.show', $note))->assertOk();
        $this->actingAs($author)->get(route('meeting-notes.show', $note))->assertOk();
        $this->actingAs($lead)->get(route('meeting-notes.show', $note))->assertOk();
        $this->actingAs($outsider)->get(route('meeting-notes.show', $note))->assertForbidden();
    }

    public function test_everyone_note_is_public(): void
    {
        $note = $this->note(User::factory()->create(), ['visibility' => 'everyone']);

        $this->actingAs(User::factory()->create(['role' => User::ROLE_VIEWER]))
            ->get(route('meeting-notes.show', $note))->assertOk();
    }

    public function test_index_hides_attendees_only_notes_from_non_viewers(): void
    {
        $author = User::factory()->create();
        $this->note($author, ['title' => 'Public sync', 'visibility' => 'everyone']);
        $this->note($author, ['title' => 'Secret retro', 'visibility' => 'attendees']);

        // A non-lead, non-attendee sees only the public one.
        $this->actingAs(User::factory()->create(['role' => User::ROLE_VIEWER]))
            ->get(route('meeting-notes.index'))
            ->assertOk()->assertSee('Public sync')->assertDontSee('Secret retro');

        // A lead sees both.
        $this->actingAs(User::factory()->create(['role' => User::ROLE_CTO]))
            ->get(route('meeting-notes.index'))
            ->assertOk()->assertSee('Public sync')->assertSee('Secret retro');
    }

    public function test_release_and_event_cards_hide_attendees_only_notes(): void
    {
        $release = $this->release();
        $author = User::factory()->create();
        $event = Event::create([
            'title' => 'Kickoff', 'type' => 'meeting',
            'starts_at' => '2026-07-24 10:00:00', 'release_id' => $release->id, 'created_by' => $author->id,
        ]);

        $this->note($author, ['title' => 'Open note', 'visibility' => 'everyone', 'release_id' => $release->id, 'event_id' => $event->id]);
        $this->note($author, ['title' => 'Private note', 'visibility' => 'attendees', 'release_id' => $release->id, 'event_id' => $event->id]);

        $viewer = User::factory()->create(['role' => User::ROLE_VIEWER]);

        $this->actingAs($viewer)->get(route('releases.show', $release))
            ->assertOk()->assertSee('Open note')->assertDontSee('Private note');

        $this->actingAs($viewer)->get(route('events.show', $event))
            ->assertOk()->assertSee('Open note')->assertDontSee('Private note');
    }
}
