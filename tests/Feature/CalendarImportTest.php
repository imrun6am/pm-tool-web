<?php

namespace Tests\Feature;

use App\Jobs\ExtractCalendarEvents;
use App\Models\Event;
use App\Models\EventImport;
use App\Models\Project;
use App\Models\Release;
use App\Models\Team;
use App\Models\User;
use App\Services\Calendar\ClaudeEventExtractor;
use App\Services\Calendar\DocumentTextConverter;
use App\Services\Calendar\EventExtractor;
use App\Services\Calendar\EventImportService;
use App\Services\Calendar\TextChunker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class CalendarImportTest extends TestCase
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
            'title' => 'Sprint review',
            'type' => 'review',
            'start_date' => '2026-11-12',
            'start_time' => '14:00',
            'end_date' => null,
            'end_time' => '15:00',
            'all_day' => false,
            'description' => 'Demo the new checkout.',
            'location' => 'Room 4',
            'attendees' => [],
            'release_hint' => null,
            'confidence' => 0.9,
        ], $overrides);
    }

    private function release(string $name): Release
    {
        $project = Project::create(['name' => 'P '.$name, 'color' => '#4f46e5']);
        $team = Team::create(['name' => 'T '.$name, 'color' => '#0891b2']);

        return Release::create([
            'project_id' => $project->id, 'team_id' => $team->id, 'name' => $name,
            'year' => 2026, 'quarter' => 4, 'start_date' => '2026-10-01', 'end_date' => '2026-12-15',
        ]);
    }

    /** An import whose file is already on the (fake) disk, as the upload leaves it. */
    private function import(User $user, string $name, string $contents, array $attributes = []): EventImport
    {
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $path = "event-imports/{$user->id}/".uniqid().".{$extension}";
        Storage::disk('local')->put($path, $contents);

        return EventImport::create(array_merge([
            'user_id' => $user->id,
            'original_name' => $name,
            'file_path' => $path,
            'extension' => $extension,
            'mime_type' => $extension === 'pdf' ? 'application/pdf' : 'text/plain',
            'size' => strlen($contents),
            'timezone' => 'Asia/Dhaka',
            'status' => EventImport::STATUS_PENDING,
        ], $attributes));
    }

    /** An import ready for review with the given (stored-shape) proposals. */
    private function reviewable(User $user, array $proposals): EventImport
    {
        $import = $this->import($user, 'plan.pdf', '%PDF-1.4');
        $import->update([
            'status' => EventImport::STATUS_NEEDS_REVIEW,
            'proposals' => app(EventImportService::class)->normalize($proposals),
        ]);

        return $import->fresh();
    }

    public function test_upload_stores_the_file_queues_the_job_and_returns_at_once(): void
    {
        Queue::fake();
        $user = User::factory()->create(['role' => User::ROLE_DEVELOPER]);

        $response = $this->actingAs($user)->post(route('calendar.imports.store'), [
            'document' => UploadedFile::fake()->create('release-plan.pdf', 300, 'application/pdf'),
            'timezone' => 'Europe/Berlin',
        ]);

        $import = EventImport::sole();
        $response->assertRedirect(route('calendar.imports.show', $import));
        $this->assertSame(EventImport::STATUS_PENDING, $import->status);
        $this->assertSame('release-plan.pdf', $import->original_name);
        $this->assertSame('Europe/Berlin', $import->timezone);
        $this->assertSame('pdf', $import->extension);
        Storage::disk('local')->assertExists($import->file_path);
        Queue::assertPushed(ExtractCalendarEvents::class, fn ($job) => $job->importId === $import->id);
        $this->assertSame(0, Event::count());
        $this->assertSame([], $this->calls);

        $this->actingAs($user)->get(route('calendar.imports.status', $import))
            ->assertOk()->assertJson(['status' => 'pending', 'running' => true]);
        $this->actingAs($user)->get(route('calendar.imports.show', $import))
            ->assertOk()->assertSee('Queued');
    }

    public function test_upload_rejects_unsupported_files_and_bad_timezones(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('calendar.imports.store'), [
            'document' => UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload'),
        ])->assertSessionHasErrors('document');

        $this->actingAs($user)->post(route('calendar.imports.store'), [
            'document' => UploadedFile::fake()->create('plan.pdf', 10, 'application/pdf'),
            'timezone' => 'Mars/Olympus',
        ])->assertSessionHasErrors('timezone');

        $this->actingAs($user)->post(route('calendar.imports.store'), [
            'document' => UploadedFile::fake()->create('photo.png', 6000, 'image/png'),
        ])->assertSessionHasErrors('document');

        $this->assertSame(0, EventImport::count());
        Queue::assertNothingPushed();
    }

    public function test_job_sends_a_pdf_natively_and_stores_matched_proposals(): void
    {
        $user = User::factory()->create();
        $sara = User::factory()->create(['name' => 'Sara Khan', 'email' => 'sara@example.com']);
        $omar = User::factory()->create(['name' => 'Omar Faruk']);
        $release = $this->release('Release 2.4');
        $import = $this->import($user, 'plan.pdf', '%PDF-1.4 fake');

        $this->result = [
            $this->event(['attendees' => ['sara@example.com', 'Omar', 'Jane Outsider'], 'release_hint' => '2.4']),
            $this->event(['title' => 'Code freeze', 'type' => 'deadline', 'start_date' => '2026-11-20', 'start_time' => null, 'end_time' => null, 'all_day' => true, 'confidence' => 7]),
            $this->event(['title' => 'No date here', 'start_date' => 'next sprint']), // dropped
            $this->event(['title' => 'Bad type', 'type' => 'party', 'start_date' => '2026-11-25', 'start_time' => '25:00']),
        ];

        ExtractCalendarEvents::dispatchSync($import->id);

        $import->refresh();
        $this->assertSame(EventImport::STATUS_NEEDS_REVIEW, $import->status);
        $this->assertSame(EventImport::SOURCE_CLAUDE, $import->source);
        $this->assertSame(0, Event::count(), 'nothing is written to events before confirm');

        $this->assertCount(1, $this->calls);
        $this->assertSame('file', $this->calls[0]['source']['kind']);
        $this->assertSame('application/pdf', $this->calls[0]['source']['mime']);
        $this->assertSame('Asia/Dhaka', $this->calls[0]['context']['timezone']);
        $this->assertStringContainsString(now('Asia/Dhaka')->toDateString(), $this->calls[0]['context']['today']);
        $this->assertContains('Release 2.4', $this->calls[0]['context']['releases']);

        $proposals = collect($import->proposals)->keyBy('title');
        $this->assertSame(['Sprint review', 'Code freeze', 'Bad type'], $proposals->keys()->all());

        $review = $proposals['Sprint review'];
        $this->assertEqualsCanonicalizing([$sara->id, $omar->id], $review['attendee_ids']);
        $this->assertSame(['Jane Outsider'], $review['unmatched_attendees']);
        $this->assertSame($release->id, $review['release_id']);
        $this->assertSame('14:00', $review['start_time']);

        $this->assertTrue($proposals['Code freeze']['all_day']);
        $this->assertSame(1.0, (float) $proposals['Code freeze']['confidence']);
        $this->assertSame('other', $proposals['Bad type']['type']);
        $this->assertTrue($proposals['Bad type']['all_day'], 'an invalid time is treated as no time');

        $this->actingAs($user)->get(route('calendar.imports.show', $import))
            ->assertOk()->assertSee('Sprint review')->assertSee('Jane Outsider')->assertSee('Not matched to a user');
    }

    public function test_long_text_documents_are_chunked_and_merged(): void
    {
        $user = User::factory()->create();
        $paragraph = str_repeat('Planning notes for the quarter. ', 800); // ~25k chars
        $text = implode("\n\n", [$paragraph, $paragraph, $paragraph]);
        $import = $this->import($user, 'agenda.txt', $text);

        // The same event from every chunk (e.g. a recurring heading) is kept once.
        $this->result = [$this->event(), $this->event(['title' => 'Kick-off', 'start_date' => '2026-11-03'])];

        ExtractCalendarEvents::dispatchSync($import->id);

        $import->refresh();
        $expected = count(TextChunker::split($text));
        $this->assertGreaterThan(1, $expected);
        $this->assertCount($expected, $this->calls);
        foreach ($this->calls as $i => $call) {
            $this->assertSame('text', $call['source']['kind']);
            $this->assertSame($i + 1, $call['source']['part']);
            $this->assertSame($expected, $call['source']['parts']);
            $this->assertLessThanOrEqual(TextChunker::DEFAULT_SIZE, mb_strlen($call['source']['text']));
        }

        $this->assertSame(EventImport::STATUS_NEEDS_REVIEW, $import->status);
        $this->assertSame($expected, $import->chunks_total);
        $this->assertSame($expected, $import->chunks_done);
        $this->assertSame(['Kick-off', 'Sprint review'], collect($import->proposals)->pluck('title')->all());
        $this->assertSame($expected * 10, $import->input_tokens);
    }

    public function test_a_retry_resumes_after_the_finished_chunks(): void
    {
        $user = User::factory()->create();
        $paragraph = str_repeat('Schedule line. ', 2000); // ~30k chars, so two per chunk won't fit
        $text = implode("\n\n", [$paragraph, $paragraph, $paragraph]);
        $import = $this->import($user, 'agenda.txt', $text, [
            'status' => EventImport::STATUS_PROCESSING,
            'chunks_total' => 3,
            'chunks_done' => 2,
            'proposals' => [$this->event(['title' => 'From chunk one'])],
        ]);
        $this->result = [$this->event(['title' => 'From chunk three'])];

        ExtractCalendarEvents::dispatchSync($import->id);

        $this->assertCount(1, $this->calls);
        $this->assertSame(3, $this->calls[0]['source']['part']);
        $this->assertEqualsCanonicalizing(['From chunk one', 'From chunk three'], collect($import->fresh()->proposals)->pluck('title')->all());
    }

    public function test_an_ics_file_is_parsed_without_claude(): void
    {
        $user = User::factory()->create();
        $sara = User::factory()->create(['name' => 'Sara Khan', 'email' => 'sara@example.com']);
        $this->result = new RuntimeException('Claude must not be called for an .ics file');

        $ics = implode("\r\n", [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'BEGIN:VEVENT',
            'UID:1',
            'SUMMARY:Sprint planning\, Q4',
            'DTSTART:20261105T030000Z',
            'DTEND:20261105T040000Z',
            'LOCATION:Zoom',
            'DESCRIPTION:Bring your\nestimates and a long line that is folded acr',
            ' oss two lines',
            'ATTENDEE;CN="Sara Khan";ROLE=REQ-PARTICIPANT:mailto:sara@example.com',
            'ATTENDEE;CN=Guest:mailto:guest@elsewhere.test',
            'BEGIN:VALARM',
            'TRIGGER:-PT15M',
            'DESCRIPTION:Reminder',
            'END:VALARM',
            'END:VEVENT',
            'BEGIN:VEVENT',
            'UID:2',
            'SUMMARY:Release 2.4 go-live',
            'DTSTART;VALUE=DATE:20261120',
            'DTEND;VALUE=DATE:20261122',
            'END:VEVENT',
            'BEGIN:VEVENT',
            'UID:3',
            'SUMMARY:Cancelled sync',
            'STATUS:CANCELLED',
            'DTSTART;TZID=Europe/Berlin:20261110T100000',
            'END:VEVENT',
            'BEGIN:VEVENT',
            'UID:4',
            'SUMMARY:Berlin review',
            'DTSTART;TZID=Europe/Berlin:20261110T100000',
            'DURATION:PT1H30M',
            'END:VEVENT',
            'END:VCALENDAR',
        ]);
        $import = $this->import($user, 'invite.ics', $ics);

        ExtractCalendarEvents::dispatchSync($import->id);

        $import->refresh();
        $this->assertSame([], $this->calls);
        $this->assertSame(EventImport::STATUS_NEEDS_REVIEW, $import->status);
        $this->assertSame(EventImport::SOURCE_ICS, $import->source);

        $p = collect($import->proposals)->keyBy('title');
        $this->assertSame(['Sprint planning, Q4', 'Berlin review', 'Release 2.4 go-live'], $p->keys()->all());

        // 03:00Z is 09:00 in Dhaka (UTC+6).
        $planning = $p['Sprint planning, Q4'];
        $this->assertSame('2026-11-05', $planning['date']);
        $this->assertSame('09:00', $planning['start_time']);
        $this->assertSame('10:00', $planning['end_time']);
        $this->assertSame('meeting', $planning['type']);
        $this->assertSame('Zoom', $planning['location']);
        $this->assertSame("Bring your\nestimates and a long line that is folded across two lines", $planning['description']);
        $this->assertSame([$sara->id], $planning['attendee_ids']);
        $this->assertSame(['Guest <guest@elsewhere.test>'], $planning['unmatched_attendees']);

        // 10:00 Berlin (UTC+1 in November) is 15:00 in Dhaka.
        $this->assertSame('15:00', $p['Berlin review']['start_time']);
        $this->assertSame('16:30', $p['Berlin review']['end_time']);
        $this->assertSame('review', $p['Berlin review']['type']);

        // DTEND is exclusive for all-day events.
        $release = $p['Release 2.4 go-live'];
        $this->assertTrue($release['all_day']);
        $this->assertSame('2026-11-20', $release['date']);
        $this->assertSame('2026-11-21', $release['end_date']);
        $this->assertSame('release', $release['type']);
    }

    public function test_confirm_creates_only_the_selected_events_through_the_event_service(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_QA]);
        $sara = User::factory()->create(['name' => 'Sara Khan']);
        $release = $this->release('Release 2.4');
        $release->team->members()->attach([$user->id, $sara->id]); // QA links their own team's release and people
        $import = $this->reviewable($user, [
            $this->event(['title' => 'Kick-off', 'start_date' => '2026-11-03', 'start_time' => '10:00', 'end_time' => '11:00']),
            $this->event(['title' => 'Release window', 'type' => 'release', 'start_date' => '2026-11-20', 'end_date' => '2026-11-21', 'start_time' => null, 'end_time' => null, 'all_day' => true]),
            $this->event(['title' => 'Skip me', 'start_date' => '2026-11-25']),
        ]);

        $response = $this->actingAs($user)->post(route('calendar.imports.confirm', $import), ['events' => [
            0 => ['include' => '1', 'title' => 'Project kick-off', 'type' => 'meeting', 'date' => '2026-11-03', 'start_time' => '10:30',
                'end_time' => '11:30', 'all_day' => '0', 'location' => 'Room 1', 'release_id' => $release->id, 'attendees' => [$sara->id]],
            1 => ['include' => '1', 'title' => 'Release window', 'type' => 'release', 'date' => '2026-11-20', 'end_date' => '2026-11-21', 'all_day' => '1'],
            // Excluded rows are not validated: a blank title must not block the confirm.
            2 => ['include' => '0', 'title' => '', 'type' => 'meeting', 'date' => 'nope'],
        ]]);

        $response->assertRedirect(route('calendar.index', ['year' => 2026, 'month' => 11]))->assertSessionHas('success');

        $this->assertSame(2, Event::count());
        $kickoff = Event::where('title', 'Project kick-off')->sole();
        $this->assertSame($user->id, $kickoff->created_by);
        $this->assertSame('2026-11-03 10:30:00', $kickoff->starts_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-03 11:30:00', $kickoff->ends_at->format('Y-m-d H:i:s'));
        $this->assertSame('Room 1', $kickoff->location);
        $this->assertSame($release->id, $kickoff->release_id);
        $this->assertSame([$sara->id], $kickoff->attendees->pluck('id')->all());

        $window = Event::where('title', 'Release window')->sole();
        $this->assertTrue($window->all_day);
        $this->assertSame('2026-11-20 00:00:00', $window->starts_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-21 23:59:59', $window->ends_at->format('Y-m-d H:i:s'));

        $import->refresh();
        $this->assertSame(EventImport::STATUS_COMPLETED, $import->status);
        $this->assertSame(2, $import->events_created);
        $this->assertSame($kickoff->id, $import->proposals[0]['event_id']);
        $this->assertNull($import->proposals[2]['event_id']);

        // Re-review: an added row can't be created twice, the skipped one still can.
        $this->actingAs($user)->post(route('calendar.imports.confirm', $import), ['events' => [
            0 => ['include' => '1', 'title' => 'Project kick-off', 'type' => 'meeting', 'date' => '2026-11-03', 'start_time' => '10:30'],
        ]])->assertSessionHas('error');
        $this->assertSame(2, Event::count());

        $this->actingAs($user)->post(route('calendar.imports.confirm', $import), ['events' => [
            2 => ['include' => '1', 'title' => 'Skip me after all', 'type' => 'other', 'date' => '2026-11-25', 'start_time' => '09:00'],
        ]])->assertSessionHas('success');
        $this->assertSame(3, Event::count());
        $this->assertSame(3, $import->fresh()->events_created);
    }

    public function test_confirm_validates_included_rows(): void
    {
        $user = User::factory()->create();
        $import = $this->reviewable($user, [$this->event()]);

        $this->actingAs($user)->post(route('calendar.imports.confirm', $import), ['events' => [
            0 => ['include' => '1', 'title' => '', 'type' => 'meeting', 'date' => '2026-11-12', 'start_time' => '14:00'],
        ]])->assertSessionHasErrors('events.0.title');

        $this->actingAs($user)->post(route('calendar.imports.confirm', $import), ['events' => [
            0 => ['include' => '1', 'title' => 'Review', 'type' => 'meeting', 'date' => '2026-11-12', 'start_time' => '14:00', 'end_time' => '13:00'],
        ]])->assertSessionHasErrors('events.0.end_date');

        $this->assertSame(0, Event::count());
        $this->assertSame(EventImport::STATUS_NEEDS_REVIEW, $import->fresh()->status);
    }

    public function test_only_the_uploader_can_review_and_admins_can_only_view_or_delete(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create(['role' => User::ROLE_TEAM_LEAD]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $import = $this->reviewable($owner, [$this->event()]);
        $row = ['events' => [0 => ['include' => '1', 'title' => 'Hijack', 'type' => 'meeting', 'date' => '2026-11-12', 'start_time' => '14:00']]];

        $this->actingAs($other)->get(route('calendar.imports.show', $import))->assertForbidden();
        $this->actingAs($other)->get(route('calendar.imports.status', $import))->assertForbidden();
        $this->actingAs($other)->post(route('calendar.imports.confirm', $import), $row)->assertForbidden();
        $this->actingAs($other)->delete(route('calendar.imports.destroy', $import))->assertForbidden();
        $this->actingAs($other)->get(route('calendar.imports.index'))->assertOk()->assertDontSee('plan.pdf');

        $this->actingAs($admin)->get(route('calendar.imports.show', $import))->assertOk()->assertSee('can confirm these events');
        $this->actingAs($admin)->post(route('calendar.imports.confirm', $import), $row)->assertForbidden();
        $this->assertSame(0, Event::count());

        $this->actingAs($admin)->delete(route('calendar.imports.destroy', $import))->assertRedirect(route('calendar.imports.index'));
        $this->assertSame(0, EventImport::count());
        Storage::disk('local')->assertMissing($import->file_path);
    }

    public function test_an_extraction_failure_is_recorded_and_can_be_retried(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $import = $this->import($user, 'plan.pdf', '%PDF-1.4');
        $this->result = new RuntimeException('Claude returned an unreadable response.');

        // After the final attempt the job's failed() hook records the error.
        $job = new ExtractCalendarEvents($import->id);
        try {
            $job->handle(app(EventImportService::class), app(EventExtractor::class));
            $this->fail('The extractor error should propagate so the queue can retry.');
        } catch (RuntimeException $e) {
            $job->failed($e);
        }

        $import->refresh();
        $this->assertSame(EventImport::STATUS_FAILED, $import->status);
        $this->assertSame('Claude returned an unreadable response.', $import->error);

        $this->actingAs($user)->get(route('calendar.imports.show', $import))
            ->assertOk()->assertSee('couldn’t be read', false)->assertSee('Try again');

        $this->actingAs($user)->post(route('calendar.imports.retry', $import))->assertRedirect(route('calendar.imports.show', $import));
        $this->assertSame(EventImport::STATUS_PENDING, $import->fresh()->status);
        $this->assertNull($import->fresh()->error);
        Queue::assertPushed(ExtractCalendarEvents::class);
    }

    public function test_a_missing_api_key_fails_the_import_at_once(): void
    {
        $this->app->instance(EventExtractor::class, new ClaudeEventExtractor(null, 'claude-opus-5-5', 'medium'));

        $user = User::factory()->create();
        $import = $this->import($user, 'plan.pdf', '%PDF-1.4');

        ExtractCalendarEvents::dispatchSync($import->id);

        $import->refresh();
        $this->assertSame(EventImport::STATUS_FAILED, $import->status);
        $this->assertStringContainsString('ANTHROPIC_API_KEY', $import->error);
    }

    public function test_docx_and_csv_are_converted_to_text(): void
    {
        $dir = sys_get_temp_dir().'/'.uniqid('import-test-');
        mkdir($dir);

        $docx = "{$dir}/plan.docx";
        $zip = new ZipArchive;
        $zip->open($docx, ZipArchive::CREATE);
        $zip->addFromString('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'
            .'<w:p><w:r><w:t>Kick-off: </w:t></w:r><w:r><w:t>3 November 10:00</w:t></w:r></w:p>'
            .'<w:tbl><w:tr><w:tc><w:p><w:r><w:t>Code freeze</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>20 Nov</w:t></w:r></w:p></w:tc></w:tr></w:tbl>'
            .'</w:body></w:document>');
        $zip->close();

        $csv = "{$dir}/schedule.csv";
        file_put_contents($csv, "Event,Date\nDemo,2026-11-12\n\"Retro, team\",2026-11-13\n");

        $converter = new DocumentTextConverter;
        $docText = $converter->convert($docx, 'docx');
        $this->assertStringContainsString('Kick-off: 3 November 10:00', $docText);
        $this->assertStringContainsString('Code freeze', $docText);
        $this->assertStringContainsString('20 Nov', $docText);

        $csvText = $converter->convert($csv, 'csv');
        $this->assertStringContainsString('Demo | 2026-11-12', $csvText);
        $this->assertStringContainsString('Retro, team | 2026-11-13', $csvText);

        array_map('unlink', glob("{$dir}/*"));
        rmdir($dir);
    }

    public function test_the_calendar_links_to_the_import_page(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_VIEWER]);

        $this->actingAs($user)->get(route('calendar.index'))->assertOk()->assertSee(route('calendar.imports.index'));
        $this->actingAs($user)->get(route('calendar.imports.index'))->assertOk()->assertSee('No imports yet');
    }
}
