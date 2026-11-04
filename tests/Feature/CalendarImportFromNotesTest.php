<?php

namespace Tests\Feature;

use App\Jobs\ExtractCalendarEvents;
use App\Models\Event;
use App\Models\EventImport;
use App\Models\MeetingNote;
use App\Models\Note;
use App\Models\User;
use App\Services\Ai\AiFeatures;
use App\Services\Calendar\ClaudeEventExtractor;
use App\Services\Calendar\EventExtractor;
use App\Services\Calendar\EventImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class CalendarImportFromNotesTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array{source: array, context: array}> calls the fake extractor received */
    private array $calls = [];

    /** @var list<array<string, mixed>>|RuntimeException what the fake returns per call */
    private array|RuntimeException $result = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config(['services.anthropic.key' => 'test-key']);

        $this->app->instance(EventExtractor::class, new class($this->calls, $this->result) implements EventExtractor
        {
            public function __construct(private array &$calls, private array|RuntimeException &$result) {}

            public function extract(array $source, array $context): array
            {
                $this->calls[] = ['source' => $source, 'context' => $context];
                if ($this->result instanceof RuntimeException) {
                    throw $this->result;
                }

                return ['events' => $this->result, 'usage' => ['input_tokens' => 10, 'output_tokens' => 5]];
            }
        });
    }

    private function event(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Demo',
            'type' => 'review',
            'start_date' => '2026-10-30',
            'start_time' => '14:00',
            'end_date' => null,
            'end_time' => null,
            'all_day' => false,
            'description' => null,
            'location' => null,
            'attendees' => [],
            'release_hint' => null,
            'confidence' => 0.9,
        ], $overrides);
    }

    private function meetingNote(User $author, array $overrides = []): MeetingNote
    {
        return MeetingNote::create(array_merge([
            'created_by' => $author->id,
            'title' => 'Sprint sync',
            'type' => MeetingNote::TYPE_DEFAULT,
            'meeting_date' => '2026-10-27',
            'body' => '<div>Demo <strong>next Friday</strong> at 2pm.</div>',
            'visibility' => MeetingNote::VISIBILITY_EVERYONE,
        ], $overrides));
    }

    private function dailyNote(User $author, array $overrides = []): Note
    {
        return Note::create(array_merge([
            'user_id' => $author->id,
            'date' => '2026-10-20',
            'body' => '<div>Code freeze tomorrow.</div>',
            'visibility' => Note::VISIBILITY_SHARED,
        ], $overrides));
    }

    private function row(array $overrides = []): array
    {
        return array_merge([
            'include' => '1', 'title' => 'Demo', 'type' => 'review', 'visibility' => Event::VISIBILITY_SHARED,
            'date' => '2026-10-30', 'start_time' => '14:00', 'end_date' => null, 'end_time' => null, 'all_day' => '0',
            'location' => null, 'release_id' => null, 'attendees' => [], 'description' => null,
        ], $overrides);
    }

    public function test_a_meeting_note_goes_through_the_existing_import_and_nothing_is_created_before_confirm(): void
    {
        $user = User::factory()->create();
        $note = $this->meetingNote($user, ['body' => '<h1>Plan</h1><ul><li>Demo <strong>next Friday</strong></li><li>Q&amp;A</li></ul>']);
        Queue::fake();

        $this->actingAs($user)->post(route('meeting-notes.calendar-events', $note), ['timezone' => 'Asia/Dhaka'])
            ->assertRedirect(route('calendar.imports.show', EventImport::sole()));

        $import = EventImport::sole();
        Queue::assertPushed(ExtractCalendarEvents::class);
        $this->assertSame($user->id, $import->user_id);
        $this->assertSame(EventImport::INPUT_NOTE, $import->input);
        $this->assertSame('Asia/Dhaka', $import->timezone);
        $this->assertTrue($import->origin->is($note));
        $this->assertSame(EventImport::STATUS_PENDING, $import->status);

        $this->result = [$this->event()];
        app()->call([new ExtractCalendarEvents($import->id), 'handle']);

        $import->refresh();
        $source = $this->calls[0]['source'];
        $this->assertSame('text', $source['kind']);
        $this->assertSame("Plan\n- Demo next Friday\n- Q&A", $source['text']);
        $this->assertSame('Tuesday, 2026-10-27', $this->calls[0]['context']['written_on']);
        $this->assertStringContainsString(now('Asia/Dhaka')->toDateString(), $this->calls[0]['context']['today']);
        $this->assertSame(EventImport::STATUS_NEEDS_REVIEW, $import->status);
        $this->assertSame(Event::VISIBILITY_SHARED, $import->proposals[0]['visibility']);
        $this->assertSame(0, Event::count());

        $this->actingAs($user)->get(route('calendar.imports.show', $import))->assertOk()
            ->assertSee('Meeting note: Sprint sync')
            ->assertSee('Open note')
            ->assertSee(route('meeting-notes.show', $note), false)
            ->assertSee('27 Oct 2026')
            ->assertSee('Add events to calendar');
        $this->assertSame(0, Event::count());

        $this->actingAs($user)->post(route('calendar.imports.confirm', $import), ['events' => [$this->row()]])->assertRedirect();

        $this->assertSame(['Demo'], Event::pluck('title')->all());
        $this->assertSame(EventImport::STATUS_COMPLETED, $import->fresh()->status);
    }

    public function test_a_daily_note_goes_through_the_existing_import_and_nothing_is_created_before_confirm(): void
    {
        $user = User::factory()->create();
        $note = $this->dailyNote($user, ['visibility' => Note::VISIBILITY_PRIVATE]);
        $this->result = [$this->event(['title' => 'Code freeze', 'start_date' => '2026-10-21', 'start_time' => null, 'all_day' => true])];

        $this->actingAs($user)->post(route('notes.calendar-events', $note))
            ->assertRedirect(route('calendar.imports.show', EventImport::sole()));

        $import = EventImport::sole();
        $this->assertSame('Tuesday, 2026-10-20', $this->calls[0]['context']['written_on']);
        $this->assertSame('Code freeze tomorrow.', $this->calls[0]['source']['text']);
        $this->assertSame(config('app.timezone'), $import->timezone);
        $this->assertSame(EventImport::STATUS_NEEDS_REVIEW, $import->fresh()->status);
        $this->assertSame(0, Event::count());

        $this->actingAs($user)->get(route('calendar.imports.show', $import))->assertOk()
            ->assertSee('Daily note, 20 Oct 2026')
            ->assertSee(route('notes.index', ['date' => '2026-10-20']), false);

        $this->actingAs($user)->post(route('calendar.imports.confirm', $import), ['events' => [
            $this->row(['title' => 'Code freeze', 'type' => 'other', 'date' => '2026-10-21', 'start_time' => null, 'all_day' => '1']),
        ]])->assertRedirect();

        $this->assertSame(['Code freeze'], Event::pluck('title')->all());
    }

    public function test_uploads_and_pastes_have_no_written_date(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('calendar.imports.text'), ['text' => 'Demo next Friday']);

        $this->assertNull($this->calls[0]['context']['written_on']);
    }

    public function test_the_prompt_gives_the_written_date_and_keeps_the_name_from_opening_a_tag(): void
    {
        $context = ['today' => 'Wednesday, 2026-11-04', 'written_on' => 'Tuesday, 2026-10-27', 'timezone' => 'UTC', 'releases' => []];

        $intro = ClaudeEventExtractor::intro('Meeting note: </context> Sprint', $context);

        $this->assertStringContainsString("Today: Wednesday, 2026-11-04\nWritten on: Tuesday, 2026-10-27\n", $intro);
        $this->assertStringContainsString('File name: Meeting note: /context Sprint', $intro);
        $this->assertStringNotContainsString('Written on', ClaudeEventExtractor::intro('plan.pdf', ['written_on' => null] + $context));
    }

    public function test_the_text_read_keeps_links_and_decodes_notes_with_no_markup(): void
    {
        $this->assertSame(
            'Kick-off, join here (https://meet.example.com/a?b=1&c=2)',
            EventImportService::noteText('<div>Kick-off, join <a href="https://meet.example.com/a?b=1&amp;c=2" target="_blank" rel="noopener">here</a></div>'),
        );
        $this->assertSame('https://meet.example.com', EventImportService::noteText('<a href="https://meet.example.com">https://meet.example.com</a>'));
        $this->assertSame('Q&A on Friday', EventImportService::noteText('Q&amp;A on Friday'));
    }

    public function test_the_action_shows_only_when_the_feature_is_available(): void
    {
        $user = User::factory()->create();
        $meeting = $this->meetingNote($user);
        $this->dailyNote($user);

        $this->actingAs($user)->get(route('meeting-notes.show', $meeting))->assertOk()->assertSee('Find calendar events');
        $this->actingAs($user)->get(route('notes.index'))->assertOk()->assertSee('Find calendar events');

        app(AiFeatures::class)->set('calendar_import', false, User::factory()->create(['role' => User::ROLE_ADMIN]));

        $this->actingAs($user)->get(route('meeting-notes.show', $meeting))->assertOk()->assertDontSee('Find calendar events');
        $this->actingAs($user)->get(route('notes.index'))->assertOk()->assertDontSee('Find calendar events');

        app(AiFeatures::class)->set('calendar_import', true, User::factory()->create(['role' => User::ROLE_ADMIN]));
        config(['services.anthropic.key' => null]);

        $this->actingAs($user)->get(route('meeting-notes.show', $meeting))->assertOk()->assertDontSee('Find calendar events');
        $this->actingAs($user)->get(route('notes.index'))->assertOk()->assertDontSee('Find calendar events');
    }

    public function test_the_server_refuses_when_the_feature_is_unavailable(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        app(AiFeatures::class)->set('calendar_import', false, User::factory()->create(['role' => User::ROLE_ADMIN]));

        $this->actingAs($user)->post(route('meeting-notes.calendar-events', $this->meetingNote($user)))->assertSessionHas('error');
        $this->actingAs($user)->post(route('notes.calendar-events', $this->dailyNote($user)))->assertSessionHas('error');

        $this->assertSame(0, EventImport::count());
        Queue::assertNothingPushed();
    }

    public function test_a_reader_of_a_shared_note_is_offered_the_action_without_edit_rights(): void
    {
        $author = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $reader = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->dailyNote($author);

        $this->actingAs($reader)->get(route('notes.index'))->assertOk()
            ->assertSee('Find calendar events')
            ->assertDontSee('Edit note');
    }

    public function test_only_readers_of_an_attendees_only_meeting_note_can_extract_from_it(): void
    {
        Queue::fake();
        $author = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $attendee = User::factory()->create(['role' => User::ROLE_VIEWER]);
        $outsider = User::factory()->create(['role' => User::ROLE_VIEWER]);
        $note = $this->meetingNote($author, ['visibility' => MeetingNote::VISIBILITY_ATTENDEES]);
        $note->attendees()->attach($attendee);

        $this->actingAs($outsider)->post(route('meeting-notes.calendar-events', $note))->assertForbidden();
        $this->assertSame(0, EventImport::count());
        Queue::assertNothingPushed();

        $this->actingAs($attendee)->post(route('meeting-notes.calendar-events', $note))->assertRedirect();
        $this->assertSame($attendee->id, EventImport::sole()->user_id);
    }

    public function test_only_readers_of_a_daily_note_can_extract_from_it(): void
    {
        Queue::fake();
        $author = User::factory()->create(['role' => User::ROLE_VIEWER]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $recipient = User::factory()->create(['role' => User::ROLE_VIEWER]);
        $private = $this->dailyNote($author, ['visibility' => Note::VISIBILITY_PRIVATE]);
        $specific = $this->dailyNote($author, ['visibility' => Note::VISIBILITY_SPECIFIC]);
        $specific->recipients()->attach($recipient);

        // Not even an admin reads someone else's private note.
        $this->actingAs($admin)->post(route('notes.calendar-events', $private))->assertForbidden();
        $this->actingAs($recipient)->post(route('notes.calendar-events', $private))->assertForbidden();
        $this->actingAs($admin)->post(route('notes.calendar-events', $specific))->assertForbidden();
        $this->assertSame(0, EventImport::count());
        Queue::assertNothingPushed();

        $this->actingAs($recipient)->post(route('notes.calendar-events', $specific))->assertRedirect();
        $this->assertSame($recipient->id, EventImport::sole()->user_id);
    }

    public function test_limited_roles_follow_the_notes_team_rules(): void
    {
        Queue::fake();
        $author = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $developer = User::factory()->create(['role' => User::ROLE_DEVELOPER]);

        // Shares no team with the author, so neither note is visible to them.
        $this->actingAs($developer)->post(route('notes.calendar-events', $this->dailyNote($author)))->assertForbidden();
        $this->actingAs($developer)->post(route('meeting-notes.calendar-events', $this->meetingNote($author)))->assertForbidden();

        $this->assertSame(0, EventImport::count());
    }

    public function test_an_import_stays_private_and_an_admin_cannot_read_a_private_note_through_it(): void
    {
        Queue::fake();
        $author = User::factory()->create(['role' => User::ROLE_VIEWER]);
        $other = User::factory()->create(['role' => User::ROLE_TECH_LEAD]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($author)->post(route('notes.calendar-events', $this->dailyNote($author, ['visibility' => Note::VISIBILITY_PRIVATE])));
        $import = EventImport::sole();

        $this->actingAs($other)->get(route('calendar.imports.show', $import))->assertForbidden();
        $this->actingAs($other)->get(route('calendar.imports.status', $import))->assertForbidden();
        $this->actingAs($other)->get(route('calendar.imports.index'))->assertOk()->assertDontSee('Daily note');
        $this->actingAs($admin)->get(route('calendar.imports.show', $import))->assertForbidden();
        $this->actingAs($admin)->get(route('calendar.imports.status', $import))->assertForbidden();
        $this->actingAs($admin)->post(route('calendar.imports.confirm', $import), ['events' => [$this->row()]])->assertForbidden();

        // Removing it reveals nothing, so an admin keeps that.
        $this->actingAs($admin)->delete(route('calendar.imports.destroy', $import))->assertRedirect();
        $this->assertSame(0, EventImport::count());
    }

    public function test_an_admin_may_open_an_import_of_a_note_they_can_read(): void
    {
        Queue::fake();
        $author = User::factory()->create(['role' => User::ROLE_VIEWER]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($author)->post(route('meeting-notes.calendar-events', $this->meetingNote($author)));

        $this->actingAs($admin)->get(route('calendar.imports.show', EventImport::sole()))->assertOk();
    }

    public function test_a_note_with_no_text_gets_a_clear_message_and_no_claude_call(): void
    {
        $user = User::factory()->create();

        foreach (['', '<div><br></div>', '<div>&nbsp;</div>'] as $body) {
            // The mutator cleans the body, so write the stored value directly.
            $meeting = $this->meetingNote($user);
            MeetingNote::whereKey($meeting->id)->update(['body' => $body]);
            $note = $this->dailyNote($user);
            Note::whereKey($note->id)->update(['body' => $body]);

            $this->actingAs($user)->from(route('meeting-notes.show', $meeting))
                ->post(route('meeting-notes.calendar-events', $meeting))
                ->assertRedirect(route('meeting-notes.show', $meeting))
                ->assertSessionHas('error', fn (string $m) => str_contains($m, 'no text to read'));
            $this->actingAs($user)->from(route('notes.index'))
                ->post(route('notes.calendar-events', $note))
                ->assertRedirect(route('notes.index'))
                ->assertSessionHas('error', fn (string $m) => str_contains($m, 'no text to read'));

            $this->actingAs($user)->get(route('meeting-notes.show', $meeting))->assertOk()
                ->assertSee('no written notes yet')
                ->assertDontSee('Find calendar events');
        }

        $this->assertSame(0, EventImport::count());
        $this->assertSame([], $this->calls);
    }

    public function test_the_history_row_names_the_note_and_not_a_file(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $meeting = $this->meetingNote($user);

        $this->actingAs($user)->post(route('meeting-notes.calendar-events', $meeting));
        $this->actingAs($user)->post(route('notes.calendar-events', $this->dailyNote($user)));

        $this->actingAs($user)->get(route('calendar.imports.index'))->assertOk()
            ->assertSee('Meeting note: Sprint sync')
            ->assertSee('Daily note, 20 Oct 2026')
            ->assertSee('Code freeze tomorrow.')
            ->assertSee('characters')
            ->assertDontSee('px-1.5 py-0.5 font-mono', false) // the file-extension badge
            ->assertSee('The note and any events already added stay as they are.');

        // The label outlives the note, and the link back goes with it.
        $import = EventImport::where('origin_type', $meeting->getMorphClass())->sole();
        $meeting->delete();

        $this->actingAs($user)->get(route('calendar.imports.index'))->assertOk()->assertSee('Meeting note: Sprint sync');
        $this->actingAs($user)->get(route('calendar.imports.show', $import))->assertOk()
            ->assertSee('Meeting note: Sprint sync')
            ->assertDontSee('Open note');
    }

    public function test_starting_again_while_running_opens_the_running_import(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $note = $this->meetingNote($user);

        $this->actingAs($user)->post(route('meeting-notes.calendar-events', $note));
        $first = EventImport::sole();
        $this->actingAs($user)->post(route('meeting-notes.calendar-events', $note))->assertRedirect(route('calendar.imports.show', $first));

        $this->assertSame(1, EventImport::count());
        Queue::assertPushed(ExtractCalendarEvents::class, 1);

        // Someone else reading the same note gets their own, and a finished one doesn't block a new look.
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))->post(route('meeting-notes.calendar-events', $note));
        $this->assertSame(2, EventImport::count());

        $first->update(['status' => EventImport::STATUS_NEEDS_REVIEW]);
        $this->actingAs($user)->post(route('meeting-notes.calendar-events', $note));
        $this->assertSame(3, EventImport::count());
    }

    public function test_the_copy_of_the_text_goes_once_read_but_stays_for_a_retry(): void
    {
        $user = User::factory()->create();
        $note = $this->dailyNote($user);
        $this->result = new RuntimeException('Overloaded');

        try {
            $this->actingAs($user)->post(route('notes.calendar-events', $note));
        } catch (RuntimeException) {
            // The sync queue rethrows; the worker would mark it failed after its tries.
        }

        $import = EventImport::sole();
        (new ExtractCalendarEvents($import->id))->failed($this->result);
        $this->assertSame(EventImport::STATUS_FAILED, $import->fresh()->status);
        Storage::disk('local')->assertExists($import->file_path);

        $this->actingAs($user)->get(route('calendar.imports.show', $import))->assertOk()
            ->assertSee('The note couldn’t be read')
            ->assertSee('Back to the note');

        $this->result = [$this->event()];
        $this->actingAs($user)->post(route('calendar.imports.retry', $import))->assertRedirect();

        $this->assertSame(EventImport::STATUS_NEEDS_REVIEW, $import->fresh()->status);
        Storage::disk('local')->assertMissing($import->file_path);
        $this->actingAs($user)->get(route('calendar.imports.show', $import))->assertOk()->assertDontSee('Show pasted text');
    }

    public function test_a_retry_is_refused_once_the_starter_cannot_read_the_note(): void
    {
        Queue::fake();
        $author = User::factory()->create(['role' => User::ROLE_VIEWER]);
        $recipient = User::factory()->create(['role' => User::ROLE_VIEWER]);
        $note = $this->dailyNote($author, ['visibility' => Note::VISIBILITY_SPECIFIC]);
        $note->recipients()->attach($recipient);

        $this->actingAs($recipient)->post(route('notes.calendar-events', $note));
        $import = EventImport::sole();
        $import->update(['status' => EventImport::STATUS_FAILED, 'error' => 'Overloaded']);

        $note->recipients()->detach($recipient);

        $this->actingAs($recipient)->post(route('calendar.imports.retry', $import))->assertForbidden();
        $this->assertSame(EventImport::STATUS_FAILED, $import->fresh()->status);
        $this->actingAs($recipient)->get(route('calendar.imports.index'))->assertOk()->assertDontSee('Try again');
        $this->actingAs($recipient)->get(route('calendar.imports.show', $import))->assertOk()
            ->assertDontSee('Try again')
            ->assertDontSee('Open note')
            ->assertSee('Your imports');
    }

    public function test_no_events_found_points_back_to_the_note(): void
    {
        $user = User::factory()->create();
        $note = $this->meetingNote($user);
        $this->result = [];

        $this->actingAs($user)->post(route('meeting-notes.calendar-events', $note));

        $this->actingAs($user)->get(route('calendar.imports.show', EventImport::sole()))->assertOk()
            ->assertSee('Nothing in this note had a date')
            ->assertSee('Back to the note')
            ->assertDontSee('Upload another file');
    }
}
