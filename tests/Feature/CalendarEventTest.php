<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarEventTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Sprint planning',
            'type' => 'meeting',
            'starts_at' => '2026-07-24T10:00',
            'ends_at' => '2026-07-24T11:00',
        ], $overrides);
    }

    public function test_any_user_can_create_an_event_with_attendees(): void
    {
        $creator = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $a = User::factory()->create();
        $b = User::factory()->create();

        $this->actingAs($creator)
            ->post(route('events.store'), $this->payload(['attendees' => [$a->id, $b->id]]))
            ->assertRedirect();

        $event = Event::first();
        $this->assertSame('Sprint planning', $event->title);
        $this->assertSame($creator->id, $event->created_by);
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $event->attendees->pluck('id')->all());
    }

    public function test_end_before_start_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('events.store'), $this->payload(['ends_at' => '2026-07-24T09:00']))
            ->assertSessionHasErrors('ends_at');

        $this->assertSame(0, Event::count());
    }

    public function test_all_day_normalizes_to_day_bounds(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('events.store'), $this->payload([
                'all_day' => '1', 'starts_at' => '2026-07-24T13:37', 'ends_at' => '2026-07-25T08:00',
            ]));

        $event = Event::first();
        $this->assertTrue($event->all_day);
        $this->assertSame('00:00:00', $event->starts_at->format('H:i:s'));
        $this->assertSame('23:59:59', $event->ends_at->format('H:i:s'));
    }

    public function test_month_view_shows_event_on_its_days(): void
    {
        $creator = User::factory()->create();
        Event::create([
            'title' => 'Design workshop', 'type' => 'meeting',
            'starts_at' => '2026-07-15 10:00:00', 'ends_at' => '2026-07-16 12:00:00',
            'created_by' => $creator->id,
        ]);

        $this->actingAs($creator)
            ->get(route('calendar.index', ['year' => 2026, 'month' => 7]))
            ->assertOk()
            ->assertSee('Design workshop')
            ->assertSee('July 2026');
    }

    public function test_only_creator_or_admin_can_edit_or_delete(): void
    {
        $creator = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $event = Event::create([
            'title' => 'X', 'type' => 'meeting', 'starts_at' => '2026-07-24 10:00:00', 'created_by' => $creator->id,
        ]);

        // A different non-admin cannot edit or delete.
        $other = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $this->actingAs($other)->get(route('events.edit', $event))->assertForbidden();
        $this->actingAs($other)->delete(route('events.destroy', $event))->assertForbidden();

        // The creator can — the edit URL opens the drawer on the event page.
        $this->actingAs($creator)->get(route('events.edit', $event))
            ->assertRedirect(route('events.show', [$event, 'edit' => 1]));

        // An admin can delete anyone's event.
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
            ->delete(route('events.destroy', $event))->assertRedirect();
        $this->assertSame(0, Event::count());
    }

    public function test_creating_returns_to_the_events_month(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('events.store'), $this->payload())
            ->assertRedirect(route('calendar.index', ['year' => 2026, 'month' => 7]))
            ->assertSessionHas('success', 'Event created.');
    }

    public function test_create_url_opens_the_drawer_on_that_days_month(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('events.create', ['date' => '2026-07-14']))
            ->assertRedirect(route('calendar.index', ['year' => 2026, 'month' => 7, 'new' => '2026-07-14']));
    }

    public function test_calendar_renders_the_new_event_drawer(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('calendar.index', ['year' => 2026, 'month' => 7]))
            ->assertOk()
            ->assertSee('action="'.route('events.store').'"', false)
            ->assertSee('open: false', false);

        // The create deep link opens it with the start on that day.
        $this->actingAs($user)
            ->get(route('calendar.index', ['year' => 2026, 'month' => 7, 'new' => '2026-07-14']))
            ->assertOk()
            ->assertSee('open: true', false)
            ->assertSee('value="2026-07-14T09:00"', false);
    }

    public function test_failed_drawer_submit_reopens_the_drawer_with_input(): void
    {
        $this->actingAs(User::factory()->create())
            ->from(route('calendar.index', ['year' => 2026, 'month' => 7]))
            ->followingRedirects()
            ->post(route('events.store'), $this->payload(['title' => 'Kept title', 'ends_at' => '2026-07-24T09:00', '_drawer' => 'event']))
            ->assertOk()
            ->assertSee('open: true', false)
            ->assertSee('value="Kept title"', false)
            ->assertSee('id="ends_at-error"', false);
    }

    public function test_edit_drawer_only_renders_for_those_who_may_edit(): void
    {
        $creator = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $event = Event::create([
            'title' => 'Retro', 'type' => 'meeting', 'starts_at' => '2026-07-24 10:00:00', 'created_by' => $creator->id,
        ]);
        $update = 'action="'.route('events.update', $event).'"';

        $this->actingAs($creator)->get(route('events.show', $event))
            ->assertOk()->assertSee($update, false)->assertSee('open: false', false);
        $this->actingAs($creator)->get(route('events.show', [$event, 'edit' => 1]))
            ->assertOk()->assertSee('open: true', false);

        $this->actingAs(User::factory()->create(['role' => User::ROLE_DEVELOPER]))
            ->get(route('events.show', $event))
            ->assertOk()->assertDontSee($update, false);
    }

    public function test_guest_is_redirected(): void
    {
        $this->get(route('calendar.index'))->assertRedirect(route('login'));
        $this->get(route('events.create'))->assertRedirect(route('login'));
    }
}
