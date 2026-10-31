<?php

namespace Tests\Feature;

use App\Models\Note;
use App\Models\User;
use App\Services\NoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyNoteAuthorFilterTest extends TestCase
{
    use RefreshDatabase;

    private function note(User $user, array $attrs = [], array $recipients = []): Note
    {
        $note = $user->notes()->create(array_merge([
            'date' => '2026-07-22', 'body' => 'A note', 'visibility' => 'private',
        ], $attrs));

        if ($recipients) {
            $note->recipients()->sync($recipients);
        }

        return $note;
    }

    /** Note bodies the list rendered, for the given query. */
    private function listed(User $viewer, array $query = []): array
    {
        $response = $this->actingAs($viewer)->get(route('notes.index', $query))->assertOk();

        return collect($response->viewData('notes')->items())
            ->map(fn (Note $n) => trim(strip_tags((string) $n->body)))
            ->sort()->values()->all();
    }

    public function test_filtering_by_an_author_narrows_the_list(): void
    {
        $viewer = User::factory()->create();
        $ada = User::factory()->create(['name' => 'Ada']);
        $bea = User::factory()->create(['name' => 'Bea']);

        $this->note($ada, ['body' => 'From Ada', 'visibility' => 'shared']);
        $this->note($bea, ['body' => 'From Bea', 'visibility' => 'shared']);

        $this->assertSame(['From Ada'], $this->listed($viewer, ['author' => $ada->id]));
        $this->assertSame(['From Bea'], $this->listed($viewer, ['author' => $bea->id]));
        $this->assertSame(['From Ada', 'From Bea'], $this->listed($viewer));
    }

    public function test_filtering_by_yourself_includes_your_private_notes(): void
    {
        $viewer = User::factory()->create();
        $other = User::factory()->create();

        $this->note($viewer, ['body' => 'My private thought', 'visibility' => 'private']);
        $this->note($viewer, ['body' => 'My shared thought', 'visibility' => 'shared']);
        $this->note($other, ['body' => 'Someone else', 'visibility' => 'shared']);

        $this->assertSame(
            ['My private thought', 'My shared thought'],
            $this->listed($viewer, ['author' => $viewer->id])
        );
    }

    public function test_the_author_filter_never_reveals_another_persons_private_notes(): void
    {
        $viewer = User::factory()->create();
        $ada = User::factory()->create(['name' => 'Ada']);

        $this->note($ada, ['body' => 'Ada private', 'visibility' => 'private']);
        $this->note($ada, ['body' => 'Ada shared', 'visibility' => 'shared']);
        $this->note($ada, ['body' => 'Ada to viewer', 'visibility' => 'specific'], [$viewer->id]);
        $this->note($ada, ['body' => 'Ada to someone else', 'visibility' => 'specific'], [User::factory()->create()->id]);

        // Only what the viewer could already see, narrowed to Ada.
        $this->assertSame(
            ['Ada shared', 'Ada to viewer'],
            $this->listed($viewer, ['author' => $ada->id])
        );
    }

    public function test_an_author_with_nothing_visible_yields_an_empty_list_not_an_error(): void
    {
        $viewer = User::factory()->create();
        $hidden = User::factory()->create();
        $this->note($hidden, ['body' => 'Not for you', 'visibility' => 'private']);

        $this->assertSame([], $this->listed($viewer, ['author' => $hidden->id]));
        // An id belonging to nobody is empty too, not a validation error.
        $this->assertSame([], $this->listed($viewer, ['author' => 999999]));
    }

    public function test_author_composes_with_the_date_filters(): void
    {
        $viewer = User::factory()->create();
        $ada = User::factory()->create(['name' => 'Ada']);
        $bea = User::factory()->create(['name' => 'Bea']);

        $this->note($ada, ['body' => 'Ada on the 22nd', 'date' => '2026-07-22', 'visibility' => 'shared']);
        $this->note($ada, ['body' => 'Ada on the 25th', 'date' => '2026-07-25', 'visibility' => 'shared']);
        $this->note($bea, ['body' => 'Bea on the 22nd', 'date' => '2026-07-22', 'visibility' => 'shared']);

        $this->assertSame(
            ['Ada on the 22nd'],
            $this->listed($viewer, ['author' => $ada->id, 'date' => '2026-07-22'])
        );

        $this->assertSame(
            ['Ada on the 22nd', 'Ada on the 25th'],
            $this->listed($viewer, ['author' => $ada->id, 'from' => '2026-07-20', 'to' => '2026-07-26'])
        );
    }

    public function test_clearing_restores_the_full_list(): void
    {
        $viewer = User::factory()->create();
        $ada = User::factory()->create(['name' => 'Ada']);

        $this->note($viewer, ['body' => 'Mine', 'visibility' => 'private']);
        $this->note($ada, ['body' => 'Theirs', 'visibility' => 'shared']);

        $this->assertSame(['Mine', 'Theirs'], $this->listed($viewer));
    }

    // ------------------------------------------------------ author options

    public function test_author_options_list_only_authors_of_visible_notes(): void
    {
        $viewer = User::factory()->create(['name' => 'Viewer']);
        $visible = User::factory()->create(['name' => 'Ada']);
        $invisible = User::factory()->create(['name' => 'Zed']);

        $this->note($viewer, ['body' => 'Mine', 'visibility' => 'private']);
        $this->note($visible, ['body' => 'Shared', 'visibility' => 'shared']);
        // Zed's only note is private to them — they must not be offered.
        $this->note($invisible, ['body' => 'Hidden', 'visibility' => 'private']);

        $names = app(NoteService::class)->authorsVisibleTo($viewer)->pluck('name')->sort()->values()->all();

        $this->assertSame(['Ada', 'Viewer'], $names);
    }

    public function test_a_soft_deleted_author_stays_selectable_while_their_notes_are_visible(): void
    {
        $viewer = User::factory()->create(['name' => 'Viewer']);
        $departed = User::factory()->create(['name' => 'Gone']);
        $this->note($departed, ['body' => 'Still readable', 'visibility' => 'shared']);

        $departed->delete();

        $authors = app(NoteService::class)->authorsVisibleTo($viewer);

        $this->assertContains('Gone', $authors->pluck('name')->all());
        // …and their note is still listed and still filterable by them.
        $this->assertSame(['Still readable'], $this->listed($viewer, ['author' => $departed->id]));
    }

    public function test_author_options_ignore_the_other_filters(): void
    {
        $viewer = User::factory()->create(['name' => 'Viewer']);
        $ada = User::factory()->create(['name' => 'Ada']);
        $this->note($ada, ['body' => 'Long ago', 'date' => '2026-01-05', 'visibility' => 'shared']);

        // Ada wrote nothing in this range, but must stay selectable so the
        // filter can be changed without the selection silently disappearing.
        $response = $this->actingAs($viewer)
            ->get(route('notes.index', ['from' => '2026-07-01', 'to' => '2026-07-31']))->assertOk();

        $this->assertContains('Ada', collect($response->viewData('authors'))->pluck('name')->all());
    }

    public function test_api_author_filter_matches_the_web_list(): void
    {
        $viewer = User::factory()->create();
        $ada = User::factory()->create(['name' => 'Ada']);

        $this->note($ada, ['body' => 'Ada shared', 'visibility' => 'shared']);
        $this->note($ada, ['body' => 'Ada private', 'visibility' => 'private']);
        $this->note(User::factory()->create(), ['body' => 'Someone else', 'visibility' => 'shared']);

        $response = $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/v1/notes?author='.$ada->id)->assertOk();

        $bodies = collect($response->json('data'))->map(fn ($n) => trim(strip_tags((string) $n['body'])))->all();

        $this->assertSame(['Ada shared'], $bodies);
        $this->assertSame($bodies, $this->listed($viewer, ['author' => $ada->id]));
    }
}
