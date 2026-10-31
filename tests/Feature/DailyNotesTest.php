<?php

namespace Tests\Feature;

use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyNotesTest extends TestCase
{
    use RefreshDatabase;

    private function note(User $user, array $attrs = []): Note
    {
        return $user->notes()->create(array_merge([
            'date' => '2026-07-22', 'body' => 'A note', 'visibility' => 'private',
        ], $attrs));
    }

    public function test_user_can_add_private_and_shared_notes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('notes.store'), [
            'date' => '2026-07-22', 'body' => 'Private one', 'visibility' => 'private',
        ])->assertRedirect();

        $this->actingAs($user)->post(route('notes.store'), [
            'date' => '2026-07-22', 'body' => 'Shared one', 'visibility' => 'shared',
        ])->assertRedirect();

        $this->assertSame(2, Note::count());
        $this->assertSame($user->id, Note::first()->user_id);
    }

    public function test_empty_or_invalid_visibility_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('notes.store'), ['date' => '2026-07-22', 'body' => '', 'visibility' => 'nope'])
            ->assertSessionHasErrors(['body', 'visibility']);
    }

    public function test_day_view_shows_own_and_shared_but_hides_others_private(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();

        $this->note($me, ['body' => 'My private', 'visibility' => 'private']);
        $this->note($other, ['body' => 'Their shared', 'visibility' => 'shared']);
        $this->note($other, ['body' => 'Their secret', 'visibility' => 'private']);

        $this->actingAs($me)->get(route('notes.index', ['date' => '2026-07-22']))
            ->assertOk()
            ->assertSee('My private')
            ->assertSee('Their shared')
            ->assertDontSee('Their secret');
    }

    public function test_only_author_can_edit_or_delete(): void
    {
        $author = User::factory()->create();
        $note = $this->note($author, ['visibility' => 'shared']);
        $other = User::factory()->create();

        $this->actingAs($other)->put(route('notes.update', $note), [
            'date' => '2026-07-22', 'body' => 'hijack', 'visibility' => 'shared',
        ])->assertForbidden();

        $this->actingAs($other)->delete(route('notes.destroy', $note))->assertForbidden();

        $this->actingAs($author)->put(route('notes.update', $note), [
            'date' => '2026-07-22', 'body' => 'edited', 'visibility' => 'private',
        ])->assertRedirect();
        $this->assertSame('edited', $note->refresh()->body);
        $this->assertSame('private', $note->visibility);

        $this->actingAs($author)->delete(route('notes.destroy', $note))->assertRedirect();
        $this->assertSame(0, Note::count());
    }

    public function test_notes_require_auth(): void
    {
        $this->get(route('notes.index'))->assertRedirect(route('login'));
    }

    public function test_range_view_shows_only_notes_within_the_span(): void
    {
        $me = User::factory()->create();

        $this->note($me, ['date' => '2026-07-10', 'body' => 'Before range']);
        $this->note($me, ['date' => '2026-07-15', 'body' => 'Inside range']);
        $this->note($me, ['date' => '2026-07-20', 'body' => 'Also inside']);
        $this->note($me, ['date' => '2026-07-25', 'body' => 'After range']);

        $this->actingAs($me)->get(route('notes.index', ['from' => '2026-07-14', 'to' => '2026-07-21']))
            ->assertOk()
            ->assertSee('Inside range')
            ->assertSee('Also inside')
            ->assertDontSee('Before range')
            ->assertDontSee('After range');
    }

    public function test_range_view_respects_visibility(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();

        $this->note($me, ['date' => '2026-07-15', 'body' => 'My private', 'visibility' => 'private']);
        $this->note($other, ['date' => '2026-07-16', 'body' => 'Their shared', 'visibility' => 'shared']);
        $this->note($other, ['date' => '2026-07-17', 'body' => 'Their secret', 'visibility' => 'private']);

        $this->actingAs($me)->get(route('notes.index', ['from' => '2026-07-01', 'to' => '2026-07-31']))
            ->assertOk()
            ->assertSee('My private')
            ->assertSee('Their shared')
            ->assertDontSee('Their secret');
    }

    public function test_range_view_tolerates_a_reversed_span(): void
    {
        $me = User::factory()->create();
        $this->note($me, ['date' => '2026-07-15', 'body' => 'Inside range']);

        $this->actingAs($me)->get(route('notes.index', ['from' => '2026-07-21', 'to' => '2026-07-10']))
            ->assertOk()
            ->assertSee('Inside range');
    }

    public function test_rich_text_is_sanitized(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('notes.store'), [
            'date' => '2026-07-22',
            'body' => '<strong>Hi</strong><script>alert(1)</script><a href="javascript:alert(2)">x</a>',
            'visibility' => 'shared',
        ])->assertRedirect();

        $body = Note::first()->body;
        $this->assertStringContainsString('<strong>Hi</strong>', $body);
        $this->assertStringNotContainsString('<script>', $body);
        $this->assertStringNotContainsString('javascript:', $body);
    }

    public function test_visually_empty_html_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('notes.store'), ['date' => '2026-07-22', 'body' => '<div><br></div>', 'visibility' => 'shared'])
            ->assertSessionHasErrors('body');
    }

    public function test_index_defaults_to_all_visible_notes_newest_first(): void
    {
        $me = User::factory()->create();
        $this->note($me, ['date' => '2026-07-01', 'body' => 'Older note']);
        $this->note($me, ['date' => '2026-07-25', 'body' => 'Newer note']);

        // No date filter → all visible notes on one paginated list.
        $this->actingAs($me)->get(route('notes.index'))
            ->assertOk()
            ->assertSee('Older note')
            ->assertSee('Newer note')
            ->assertSeeInOrder(['Newer note', 'Older note']);
    }

    public function test_specific_note_is_visible_only_to_recipients_and_author(): void
    {
        $author = User::factory()->create();
        $recipient = User::factory()->create();
        $outsider = User::factory()->create();
        $lead = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $note = $this->note($author, ['body' => 'Just for you', 'visibility' => 'specific']);
        $note->recipients()->sync([$recipient->id]);

        // Author and the chosen recipient see it in their list…
        $this->actingAs($author)->get(route('notes.index'))->assertOk()->assertSee('Just for you');
        $this->actingAs($recipient)->get(route('notes.index'))->assertOk()->assertSee('Just for you');

        // …but an outsider does not — and a targeted share does NOT leak to leads.
        $this->actingAs($outsider)->get(route('notes.index'))->assertOk()->assertDontSee('Just for you');
        $this->actingAs($lead)->get(route('notes.index'))->assertOk()->assertDontSee('Just for you');
    }

    public function test_storing_a_specific_note_syncs_recipients(): void
    {
        $author = User::factory()->create();
        $a = User::factory()->create();
        $b = User::factory()->create();

        $this->actingAs($author)->post(route('notes.store'), [
            'date' => '2026-07-22', 'body' => 'Shared with two', 'visibility' => 'specific',
            'recipients' => [$a->id, $b->id],
        ])->assertRedirect();

        $note = Note::first();
        $this->assertSame('specific', $note->visibility);
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $note->recipients->pluck('id')->all());
    }

    public function test_recipients_are_dropped_when_note_is_not_specific(): void
    {
        $author = User::factory()->create();
        $a = User::factory()->create();

        // Recipients submitted but visibility is shared → they must not be stored.
        $this->actingAs($author)->post(route('notes.store'), [
            'date' => '2026-07-22', 'body' => 'Public', 'visibility' => 'shared', 'recipients' => [$a->id],
        ])->assertRedirect();

        $this->assertSame(0, Note::first()->recipients()->count());
    }
}
