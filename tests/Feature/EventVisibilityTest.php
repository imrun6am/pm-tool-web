<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Event;
use App\Models\MeetingNote;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EventVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function event(User $creator, array $attributes = [], array $attendees = []): Event
    {
        $event = Event::create(array_merge([
            'title' => 'Event', 'type' => 'meeting', 'starts_at' => '2026-11-10 10:00:00', 'created_by' => $creator->id,
        ], $attributes));
        $event->attendees()->sync(collect($attendees)->pluck('id'));

        return $event;
    }

    private function calendar(User $viewer, array $query = []): TestResponse
    {
        return $this->actingAs($viewer)->get(route('calendar.index', ['year' => 2026, 'month' => 11] + $query));
    }

    public function test_events_default_to_shared_including_rows_created_before_the_column(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('events.store'), [
            'title' => 'Planning', 'type' => 'meeting', 'starts_at' => '2026-11-10T10:00',
        ])->assertRedirect();
        $this->assertSame(Event::VISIBILITY_SHARED, Event::sole()->visibility);

        // A row inserted without the column (as existing rows were) reads back shared.
        DB::table('events')->insert(['title' => 'Old', 'type' => 'meeting', 'starts_at' => '2026-11-11 10:00:00', 'created_by' => $user->id]);
        $this->assertSame(Event::VISIBILITY_SHARED, Event::where('title', 'Old')->sole()->visibility);
    }

    public function test_a_private_event_is_seen_only_by_its_creator_and_attendees(): void
    {
        $creator = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $attendee = User::factory()->create(['role' => User::ROLE_QA]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $lead = User::factory()->create(['role' => User::ROLE_TECH_LEAD]);
        Team::create(['name' => 'Payments', 'color' => '#0891b2'])->members()->attach([$creator->id, $attendee->id]);

        $this->actingAs($creator)->post(route('events.store'), [
            'title' => 'Dentist', 'type' => 'other', 'starts_at' => '2026-11-10T10:00', 'visibility' => 'private',
            'attendees' => [$attendee->id],
        ])->assertRedirect();
        $private = Event::sole();
        $this->assertTrue($private->isPrivate());
        $this->event($creator, ['title' => 'Team sync']);

        foreach ([$creator, $attendee] as $insider) {
            $this->calendar($insider)->assertSee('Dentist')->assertSee('Team sync');
            $this->actingAs($insider)->get(route('events.show', $private))->assertOk()->assertSee('Private');
        }

        foreach ([$admin, $lead] as $outsider) {
            $this->calendar($outsider)->assertDontSee('Dentist')->assertSee('Team sync');
            $this->actingAs($outsider)->get(route('events.show', $private))->assertForbidden();
        }

        // Private events stay out of the shared activity feed.
        $this->assertSame(0, Activity::where('subject_type', $private->getMorphClass())->where('subject_id', $private->id)->count());
        $this->assertSame(1, Activity::where('description', 'like', '%Team sync%')->count());
    }

    public function test_the_api_applies_the_same_rule(): void
    {
        $creator = User::factory()->create();
        $other = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $private = $this->event($creator, ['title' => 'Dentist', 'visibility' => 'private']);
        $shared = $this->event($creator, ['title' => 'Team sync']);

        Sanctum::actingAs($other);
        $titles = collect($this->getJson('/api/v1/events?year=2026&month=11')->assertOk()->json('data.events'))->pluck('title');
        $this->assertSame(['Team sync'], $titles->all());
        $this->getJson("/api/v1/events/{$private->id}")->assertForbidden();
        $this->getJson("/api/v1/events/{$shared->id}")->assertOk()
            ->assertJsonPath('data.visibility', 'shared')->assertJsonPath('data.can_edit', false);
        $this->putJson("/api/v1/events/{$shared->id}", ['title' => 'Hijacked', 'type' => 'meeting', 'starts_at' => '2026-11-10 10:00'])->assertForbidden();
        $this->getJson("/api/v1/events/{$shared->id}")->assertJsonPath('data.can_delete', true);
        $this->deleteJson("/api/v1/events/{$private->id}")->assertForbidden();

        Sanctum::actingAs($creator);
        $this->getJson("/api/v1/events/{$private->id}")->assertOk()
            ->assertJsonPath('data.is_private', true)->assertJsonPath('data.can_edit', true);
        $this->postJson('/api/v1/events', [
            'title' => 'Focus time', 'type' => 'other', 'starts_at' => '2026-11-12 09:00', 'visibility' => 'private',
        ])->assertCreated()->assertJsonPath('data.visibility', 'private');
        $this->postJson('/api/v1/events', [
            'title' => 'Bad', 'type' => 'other', 'starts_at' => '2026-11-12 09:00', 'visibility' => 'secret',
        ])->assertUnprocessable();

        // An update without `visibility` keeps it.
        $this->putJson("/api/v1/events/{$private->id}", ['title' => 'Dentist (moved)', 'type' => 'other', 'starts_at' => '2026-11-10 11:00'])->assertOk();
        $this->assertTrue($private->fresh()->isPrivate());
    }

    public function test_the_calendar_filters_all_mine_and_shared(): void
    {
        $me = User::factory()->create();
        $colleague = User::factory()->create();
        $this->event($me, ['title' => 'My private', 'visibility' => 'private']);
        $this->event($me, ['title' => 'My shared']);
        $this->event($colleague, ['title' => 'Their shared']);

        $this->calendar($me)->assertSee('My private')->assertSee('My shared')->assertSee('Their shared');
        $this->calendar($me, ['show' => 'mine'])->assertSee('My private')->assertSee('My shared')->assertDontSee('Their shared');
        $this->calendar($me, ['show' => 'shared'])->assertDontSee('My private')->assertSee('My shared')->assertSee('Their shared');

        Sanctum::actingAs($me);
        $titles = fn (string $show) => collect($this->getJson("/api/v1/events?year=2026&month=11&show={$show}")->json('data.events'))->pluck('title')->sort()->values()->all();
        $this->assertSame(['My private', 'My shared'], $titles('mine'));
        $this->assertSame(['My shared', 'Their shared'], $titles('shared'));
        $this->getJson('/api/v1/events?show=everything')->assertUnprocessable();
    }

    public function test_actions_show_only_for_those_who_may_take_them(): void
    {
        $creator = User::factory()->create();
        $event = $this->event($creator, ['title' => 'Planning']);
        $edit = route('events.edit', $event);
        $destroy = route('events.destroy', $event);

        $this->actingAs($creator)->get(route('events.show', $event))->assertOk()->assertSee($edit)->assertSee($destroy);
        // An admin may delete a shared event but not edit it.
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))->get(route('events.show', $event))
            ->assertOk()->assertDontSee($edit)->assertSee($destroy);
        $this->actingAs(User::factory()->create(['role' => User::ROLE_TECH_LEAD]))->get(route('events.show', $event))
            ->assertOk()->assertDontSee($edit)->assertDontSee($destroy);
    }

    public function test_dashboard_and_meeting_notes_do_not_leak_private_events(): void
    {
        $creator = User::factory()->create();
        $reader = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $private = $this->event($creator, ['title' => 'Secret 1:1', 'visibility' => 'private']);

        $note = MeetingNote::create([
            'event_id' => $private->id, 'created_by' => $creator->id, 'title' => 'Notes', 'type' => array_key_first(MeetingNote::TYPES),
            'meeting_date' => '2026-11-10', 'body' => '<div>Hi</div>', 'visibility' => MeetingNote::VISIBILITY_EVERYONE,
        ]);

        $this->actingAs($reader)->get(route('meeting-notes.show', $note))
            ->assertOk()->assertSee('Private event')->assertDontSee(route('events.show', $private));
        $this->actingAs($creator)->get(route('meeting-notes.show', $note))
            ->assertOk()->assertSee(route('events.show', $private));
    }
}
