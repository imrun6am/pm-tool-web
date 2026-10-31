<?php

namespace Tests\Feature;

use App\Models\MeetingNote;
use App\Models\Project;
use App\Models\Release;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeetingNoteTypesAndFiltersTest extends TestCase
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
            'title' => 'Sprint retro', 'meeting_date' => '2026-07-22', 'body' => 'What went well…',
        ], $overrides);
    }

    /** Titles of the notes the index rendered, read from the paginator. */
    private function listedTitles(User $viewer, array $query = []): array
    {
        $response = $this->actingAs($viewer)->get(route('meeting-notes.index', $query))->assertOk();

        return collect($response->viewData('notes')->items())->pluck('title')->sort()->values()->all();
    }

    // ---------------------------------------------------------------- types

    public function test_type_is_persisted_on_create_and_update(): void
    {
        $author = User::factory()->create(['role' => User::ROLE_DEVELOPER]);

        $this->actingAs($author)
            ->post(route('meeting-notes.store'), $this->payload(['type' => 'day_end']))
            ->assertRedirect();

        $note = MeetingNote::first();
        $this->assertSame('day_end', $note->type);
        $this->assertSame('Day End', $note->typeLabel());

        $this->actingAs($author)
            ->put(route('meeting-notes.update', $note), $this->payload(['type' => 'initial_qa_feedback']))
            ->assertRedirect();

        $this->assertSame('initial_qa_feedback', $note->fresh()->type);
    }

    public function test_a_note_created_without_a_type_gets_the_default(): void
    {
        $author = User::factory()->create(['role' => User::ROLE_DEVELOPER]);

        $this->actingAs($author)->post(route('meeting-notes.store'), $this->payload())->assertRedirect();

        $note = MeetingNote::first();
        $this->assertSame(MeetingNote::TYPE_DEFAULT, $note->type);
        $this->assertSame('Meeting', $note->typeLabel());
    }

    public function test_an_update_that_omits_the_type_keeps_the_existing_one(): void
    {
        $author = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $note = $this->note($author, ['type' => 'initial_handover']);

        $this->actingAs($author)
            ->put(route('meeting-notes.update', $note), $this->payload())
            ->assertRedirect();

        $this->assertSame('initial_handover', $note->fresh()->type);
    }

    public function test_an_unknown_type_is_rejected(): void
    {
        $author = User::factory()->create(['role' => User::ROLE_DEVELOPER]);

        $this->actingAs($author)
            ->post(route('meeting-notes.store'), $this->payload(['type' => 'board_game_night']))
            ->assertSessionHasErrors('type');

        $this->assertSame(0, MeetingNote::count());
    }

    public function test_a_retired_type_still_renders(): void
    {
        $author = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $note = $this->note($author, ['type' => 'ancient_ritual']);

        // Not in TYPES any more — the label falls back rather than going blank.
        $this->assertSame('Ancient ritual', $note->typeLabel());
        $this->assertSame('#64748b', $note->typeColor());

        $this->actingAs($author)->get(route('meeting-notes.show', $note))
            ->assertOk()->assertSee('Ancient ritual');
    }

    // -------------------------------------------------------------- filters

    public function test_index_filters_by_type(): void
    {
        $author = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $this->note($author, ['title' => 'Morning standup', 'type' => 'sync_up']);
        $this->note($author, ['title' => 'Wrap up', 'type' => 'day_end']);

        $this->assertSame(['Morning standup'], $this->listedTitles($author, ['type' => 'sync_up']));
        $this->assertSame(['Wrap up'], $this->listedTitles($author, ['type' => 'day_end']));
        $this->assertSame(['Morning standup', 'Wrap up'], $this->listedTitles($author));
    }

    public function test_index_filters_by_author_and_attendee_independently(): void
    {
        $writer = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $other = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $sitter = User::factory()->create(['role' => User::ROLE_QA]);

        $this->note($writer, ['title' => 'Written by writer'], [$sitter->id]);
        $this->note($other, ['title' => 'Written by other'], []);

        $this->assertSame(['Written by writer'], $this->listedTitles($writer, ['author' => $writer->id]));
        $this->assertSame(['Written by other'], $this->listedTitles($writer, ['author' => $other->id]));
        $this->assertSame(['Written by writer'], $this->listedTitles($writer, ['attendee' => $sitter->id]));
    }

    public function test_author_and_attendee_filters_combine(): void
    {
        $writer = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $other = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $sitter = User::factory()->create(['role' => User::ROLE_QA]);

        $this->note($writer, ['title' => 'Both match'], [$sitter->id]);
        $this->note($writer, ['title' => 'Wrong attendee'], []);
        $this->note($other, ['title' => 'Wrong author'], [$sitter->id]);

        $this->assertSame(
            ['Both match'],
            $this->listedTitles($writer, ['author' => $writer->id, 'attendee' => $sitter->id])
        );
    }

    public function test_every_filter_composes(): void
    {
        $release = $this->release();
        $writer = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $sitter = User::factory()->create(['role' => User::ROLE_QA]);

        $this->note($writer, [
            'title' => 'The one', 'type' => 'day_end',
            'release_id' => $release->id, 'meeting_date' => '2026-07-15',
            'body' => 'shipping checklist',
        ], [$sitter->id]);

        // Each of these differs from "The one" in exactly one filtered dimension.
        $this->note($writer, ['title' => 'Wrong type', 'type' => 'sync_up', 'release_id' => $release->id, 'meeting_date' => '2026-07-15', 'body' => 'shipping checklist'], [$sitter->id]);
        $this->note($writer, ['title' => 'Wrong release', 'type' => 'day_end', 'meeting_date' => '2026-07-15', 'body' => 'shipping checklist'], [$sitter->id]);
        $this->note($writer, ['title' => 'Wrong date', 'type' => 'day_end', 'release_id' => $release->id, 'meeting_date' => '2026-07-28', 'body' => 'shipping checklist'], [$sitter->id]);
        $this->note($writer, ['title' => 'Wrong attendee', 'type' => 'day_end', 'release_id' => $release->id, 'meeting_date' => '2026-07-15', 'body' => 'shipping checklist'], []);
        $this->note($writer, ['title' => 'Wrong body', 'type' => 'day_end', 'release_id' => $release->id, 'meeting_date' => '2026-07-15', 'body' => 'unrelated'], [$sitter->id]);

        $this->assertSame(['The one'], $this->listedTitles($writer, [
            'type' => 'day_end',
            'release' => $release->id,
            'author' => $writer->id,
            'attendee' => $sitter->id,
            'search' => 'shipping',
            'from' => '2026-07-14',
            'to' => '2026-07-21',
        ]));
    }

    public function test_clearing_filters_restores_the_full_list(): void
    {
        $author = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $this->note($author, ['title' => 'Alpha', 'type' => 'sync_up']);
        $this->note($author, ['title' => 'Beta', 'type' => 'day_end']);

        $this->assertSame(['Alpha', 'Beta'], $this->listedTitles($author));
    }

    // --------------------------------------------------------------- search

    public function test_search_matches_title_and_body(): void
    {
        $author = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $this->note($author, ['title' => 'Migration plan', 'body' => 'nothing notable']);
        $this->note($author, ['title' => 'Random chat', 'body' => 'we discussed the <em>migration</em> rollback']);
        $this->note($author, ['title' => 'Unrelated', 'body' => 'lunch']);

        $this->assertSame(['Migration plan'], $this->listedTitles($author, ['search' => 'Migration plan']));
        $this->assertSame(['Random chat'], $this->listedTitles($author, ['search' => 'rollback']));
        $this->assertSame(['Migration plan', 'Random chat'], $this->listedTitles($author, ['search' => 'migration']));
    }

    public function test_search_wildcards_are_literal(): void
    {
        $author = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $this->note($author, ['title' => 'Progress 50% done']);
        $this->note($author, ['title' => 'Nothing to report']);

        // A bare "%" would match everything if it were passed through unescaped.
        $this->assertSame(['Progress 50% done'], $this->listedTitles($author, ['search' => '50%']));
        $this->assertSame([], $this->listedTitles($author, ['search' => '%%%']));
    }

    public function test_search_with_no_matches_shows_a_clearable_empty_state(): void
    {
        $author = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $this->note($author, ['title' => 'Standup']);

        $this->actingAs($author)->get(route('meeting-notes.index', ['search' => 'nonexistent']))
            ->assertOk()
            ->assertSee('No meeting notes match these filters')
            ->assertSee('Clear all filters');
    }

    // --------------------------------------------------- visibility safety

    public function test_person_filters_never_reveal_an_attendees_only_note(): void
    {
        $author = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $insider = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $outsider = User::factory()->create(['role' => User::ROLE_VIEWER]);

        $this->note($author, [
            'title' => 'Secret retro', 'visibility' => MeetingNote::VISIBILITY_ATTENDEES,
            'body' => 'confidential incident detail',
        ], [$insider->id]);

        // The outsider is not an attendee, not the author, and not a lead.
        $this->assertSame([], $this->listedTitles($outsider, ['attendee' => $insider->id]));
        $this->assertSame([], $this->listedTitles($outsider, ['author' => $author->id]));
        $this->assertSame([], $this->listedTitles($outsider, ['search' => 'confidential']));

        // …while an attendee filtering the same way still sees it.
        $this->assertSame(['Secret retro'], $this->listedTitles($insider, ['attendee' => $insider->id]));
    }

    // ------------------------------------------------------ pagination & API

    public function test_index_paginates_and_keeps_filters_across_pages(): void
    {
        $author = User::factory()->create(['role' => User::ROLE_DEVELOPER]);

        foreach (range(1, 30) as $i) {
            $this->note($author, ['title' => "Sync {$i}", 'type' => 'sync_up']);
        }
        $this->note($author, ['title' => 'Other type', 'type' => 'day_end']);

        $response = $this->actingAs($author)->get(route('meeting-notes.index', ['type' => 'sync_up']))->assertOk();
        $paginator = $response->viewData('notes');

        $this->assertSame(30, $paginator->total());
        $this->assertSame(24, $paginator->count());
        $this->assertStringContainsString('type=sync_up', $paginator->nextPageUrl());

        // Page two stays inside the filter.
        $page2 = $this->actingAs($author)
            ->get(route('meeting-notes.index', ['type' => 'sync_up', 'page' => 2]))->assertOk();
        $this->assertSame(6, $page2->viewData('notes')->count());
    }

    public function test_meta_publishes_the_meeting_note_types(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_DEVELOPER]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/meta')->assertOk();

        $types = $response->json('data.meeting_note_types');

        $this->assertSame(array_keys(MeetingNote::TYPES), array_column($types, 'value'));
        $this->assertSame(array_values(MeetingNote::TYPES), array_column($types, 'label'));
        $this->assertSame(MeetingNote::TYPE_COLORS[MeetingNote::TYPE_DEFAULT], $types[0]['color']);
    }

    public function test_api_index_accepts_the_same_filters_as_the_web_list(): void
    {
        $author = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $sitter = User::factory()->create(['role' => User::ROLE_QA]);

        $this->note($author, ['title' => 'Kept', 'type' => 'day_end', 'body' => 'rollback plan'], [$sitter->id]);
        $this->note($author, ['title' => 'Dropped', 'type' => 'sync_up', 'body' => 'rollback plan'], [$sitter->id]);

        $filters = ['type' => 'day_end', 'attendee' => $sitter->id, 'search' => 'rollback'];

        $api = $this->actingAs($author, 'sanctum')
            ->getJson('/api/v1/meeting-notes?'.http_build_query($filters))->assertOk();

        $this->assertSame(['Kept'], array_column($api->json('data'), 'title'));
        $this->assertSame('day_end', $api->json('data.0.type'));
        $this->assertSame('Day End', $api->json('data.0.type_label'));

        // The same filters through the web list yield the same notes.
        $this->assertSame(['Kept'], $this->listedTitles($author, $filters));
    }
}
