<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\MeetingNote;
use App\Models\Note;
use App\Models\Project;
use App\Models\Release;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Developers and QA are kept out of other teams' data beyond tasks: release
 * pages, tasksheets, calendar events, meeting notes, shared notes and the
 * pickers that list releases and people. Leads and viewers are unaffected.
 */
class CrossTeamScopingTest extends TestCase
{
    use RefreshDatabase;

    private Team $core;

    private Team $growth;

    private Release $coreRelease;

    private Release $growthRelease;

    private User $dev;

    private User $teammate;

    private User $stranger;

    private User $lead;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lead = User::factory()->create(['role' => User::ROLE_TEAM_LEAD, 'name' => 'Lena Lead']);
        $this->core = Team::create(['name' => 'Core', 'color' => '#0891b2', 'team_lead_id' => $this->lead->id]);
        $this->growth = Team::create(['name' => 'Growth', 'color' => '#e11d48']);
        $this->coreRelease = $this->release($this->core, 'Core R1');
        $this->growthRelease = $this->release($this->growth, 'Growth R1');

        $this->dev = User::factory()->create(['role' => User::ROLE_DEVELOPER, 'name' => 'Dana Dev']);
        $this->teammate = User::factory()->create(['role' => User::ROLE_QA, 'name' => 'Tim Teammate']);
        $this->stranger = User::factory()->create(['role' => User::ROLE_DEVELOPER, 'name' => 'Sam Stranger']);
        $this->core->members()->attach([$this->dev->id, $this->teammate->id]);
        $this->growth->members()->attach($this->stranger);
    }

    private function release(Team $team, string $name): Release
    {
        $project = Project::firstOrCreate(['name' => 'Checkout'], ['color' => '#4f46e5']);

        return Release::create([
            'project_id' => $project->id, 'team_id' => $team->id, 'name' => $name,
            'year' => (int) today()->year, 'quarter' => (int) today()->quarter,
            'start_date' => today()->subDays(5)->toDateString(), 'end_date' => today()->addDays(20)->toDateString(),
        ]);
    }

    private function event(string $title, User $creator, array $attrs = [], array $attendees = []): Event
    {
        $event = Event::create($attrs + [
            'title' => $title, 'type' => 'meeting', 'starts_at' => today()->setTime(10, 0), 'created_by' => $creator->id,
        ]);
        $event->attendees()->sync($attendees);

        return $event;
    }

    private function meetingNote(string $title, User $author, array $attrs = [], array $attendees = []): MeetingNote
    {
        $note = MeetingNote::create($attrs + [
            'title' => $title, 'type' => array_key_first(MeetingNote::TYPES), 'meeting_date' => today(),
            'body' => '<p>Minutes</p>', 'visibility' => MeetingNote::VISIBILITY_EVERYONE, 'created_by' => $author->id,
        ]);
        $note->attendees()->sync($attendees);

        return $note;
    }

    // ── Releases ───────────────────────────────────────────────────────────

    public function test_other_teams_release_pages_documents_and_comments_are_forbidden(): void
    {
        Storage::fake('local');
        $doc = $this->growthRelease->documents()->create([
            'original_name' => 'plan.pdf', 'path' => 'release-documents/plan.pdf', 'mime_type' => 'application/pdf',
            'size' => 10, 'uploaded_by' => $this->lead->id,
        ]);
        $r = $this->growthRelease;

        $this->actingAs($this->dev)->get(route('releases.show', $r))->assertForbidden();
        $this->actingAs($this->dev)->get(route('releases.documents.download', [$r, $doc]))->assertForbidden();
        $this->actingAs($this->dev)->post(route('releases.comments.store', $r), ['body' => 'Hi'])->assertForbidden();
        $this->actingAs($this->dev)->post(route('releases.documents.store', $r), [
            'document' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
        ])->assertForbidden();
        $this->actingAs($this->dev)->get(route('releases.show', $this->coreRelease))->assertOk();

        Sanctum::actingAs($this->dev);
        foreach (['', '/conflicts', '/phases', '/off-days', '/documents', '/comments', '/tasks'] as $suffix) {
            $this->getJson("/api/v1/releases/{$r->id}{$suffix}")->assertForbidden();
        }
        $this->getJson("/api/v1/releases/{$this->coreRelease->id}")->assertOk();
        $this->app['auth']->forgetGuards();

        // Leads and viewers are unaffected.
        foreach ([User::ROLE_TEAM_LEAD, User::ROLE_VIEWER] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get(route('releases.show', $r))->assertOk();
        }
    }

    public function test_release_pickers_list_only_their_teams_releases(): void
    {
        $this->actingAs($this->dev);

        $calendar = $this->get(route('calendar.index'))->assertOk();
        $this->assertSame(['Core R1'], $calendar->viewData('releases')->pluck('name')->all());
        $this->assertEqualsCanonicalizing(['Dana Dev', 'Tim Teammate', 'Lena Lead'], $calendar->viewData('users')->pluck('name')->all());

        $notes = $this->get(route('meeting-notes.index'))->assertOk();
        $this->assertSame(['Core R1'], $notes->viewData('releases')->pluck('name')->all());
        $this->assertNotContains('Sam Stranger', $notes->viewData('users')->pluck('name')->all());

        $this->get(route('meeting-notes.create'))->assertOk()
            ->assertSee('Core R1')->assertDontSee('Growth R1')->assertDontSee('Sam Stranger');

        // The quick-links drawer, on every page.
        $this->get(route('notes.index'))->assertOk()->assertDontSee('Growth R1');

        // Leads still see every release.
        $this->actingAs($this->lead);
        $this->assertCount(2, $this->get(route('calendar.index'))->viewData('releases'));
    }

    public function test_links_events_and_notes_cannot_be_attached_to_another_teams_release_or_people(): void
    {
        Sanctum::actingAs($this->dev);

        $this->postJson('/api/v1/quick-links', ['label' => 'Spec', 'url' => 'https://example.com', 'release_id' => $this->growthRelease->id])
            ->assertUnprocessable()->assertJsonValidationErrors('release_id');
        $this->postJson('/api/v1/events', ['title' => 'Sync', 'type' => 'meeting', 'starts_at' => now()->toDateTimeString(), 'release_id' => $this->growthRelease->id])
            ->assertUnprocessable()->assertJsonValidationErrors('release_id');
        $this->postJson('/api/v1/events', ['title' => 'Sync', 'type' => 'meeting', 'starts_at' => now()->toDateTimeString(), 'attendees' => [$this->stranger->id]])
            ->assertUnprocessable()->assertJsonValidationErrors('attendees.0');
        $this->postJson('/api/v1/meeting-notes', ['title' => 'M', 'type' => array_key_first(MeetingNote::TYPES), 'meeting_date' => today()->toDateString(),
            'body' => '<p>x</p>', 'release_id' => $this->growthRelease->id, 'attendees' => [$this->stranger->id]])
            ->assertUnprocessable()->assertJsonValidationErrors(['release_id', 'attendees.0']);
        $this->postJson('/api/v1/notes', ['date' => today()->toDateString(), 'body' => '<p>x</p>',
            'visibility' => Note::VISIBILITY_SPECIFIC, 'recipients' => [$this->stranger->id]])
            ->assertUnprocessable()->assertJsonValidationErrors('recipients.0');

        // Their own team's release and people are fine (the team's lead included).
        $this->postJson('/api/v1/events', ['title' => 'Sync', 'type' => 'meeting', 'starts_at' => now()->toDateTimeString(),
            'release_id' => $this->coreRelease->id, 'attendees' => [$this->teammate->id, $this->lead->id]])
            ->assertCreated();
    }

    // ── Tasksheet ──────────────────────────────────────────────────────────

    public function test_a_developer_only_reads_their_own_teams_tasksheet(): void
    {
        $this->actingAs($this->dev)->get(route('tasksheet.index', ['team' => $this->growth->id]))
            ->assertOk()
            ->assertSee('Dana Dev')          // falls back to their own team
            ->assertDontSee('Sam Stranger');
        $this->assertSame(['Core'], $this->get(route('tasksheet.index'))->viewData('teams')->pluck('name')->all());

        Sanctum::actingAs($this->dev);
        $this->getJson("/api/v1/tasksheet?team_id={$this->growth->id}")->assertOk()
            ->assertJsonPath('data.team.id', $this->core->id)
            ->assertJsonCount(1, 'data.teams');
        $this->app['auth']->forgetGuards();

        $this->actingAs($this->lead)->get(route('tasksheet.index', ['team' => $this->growth->id]))
            ->assertOk()->assertSee('Sam Stranger');
    }

    // ── Calendar ───────────────────────────────────────────────────────────

    public function test_calendar_shows_a_developer_only_their_teams_events(): void
    {
        $this->event('Core release sync', $this->lead, ['release_id' => $this->coreRelease->id]);
        $this->event('Teammate focus block', $this->teammate);
        $this->event('Teammate 1:1 with lead', $this->teammate, attendees: [$this->lead->id]); // attendees only
        $this->event('Invited to Dana', $this->stranger, attendees: [$this->dev->id]);
        $this->event('Company holiday', $this->lead, ['type' => 'other']);
        $growthSecret = $this->event('Growth planning', $this->stranger, ['release_id' => $this->growthRelease->id]);
        $this->event('Stranger standup', $this->stranger);
        $this->event('Lead meets Growth', $this->lead, attendees: [$this->stranger->id]);

        $visible = ['Company holiday', 'Core release sync', 'Invited to Dana', 'Teammate focus block'];

        // (A day cell shows only a few events, so the full visible set is checked via the API below.)
        $page = $this->actingAs($this->dev)->get(route('calendar.index'))->assertOk();
        foreach (['Growth planning', 'Stranger standup', 'Lead meets Growth', 'Teammate 1:1 with lead'] as $hidden) {
            $page->assertDontSee($hidden);
        }
        $this->actingAs($this->dev)->get(route('events.show', $growthSecret))->assertForbidden();
        $this->actingAs($this->dev)->get(route('meeting-notes.create', ['event' => $growthSecret->id]))
            ->assertOk()->assertDontSee('Growth planning');

        Sanctum::actingAs($this->dev);
        $titles = collect($this->getJson('/api/v1/events')->assertOk()->json('data.events'))->pluck('title')->sort()->values()->all();
        $this->assertSame($visible, $titles);
        $this->getJson("/api/v1/events/{$growthSecret->id}")->assertForbidden();
        $this->app['auth']->forgetGuards();

        $this->actingAs($this->lead)->get(route('events.show', $growthSecret))->assertOk();
    }

    // ── Meeting notes and notes ────────────────────────────────────────────

    public function test_everyone_meeting_notes_are_limited_to_their_teams(): void
    {
        $this->meetingNote('Core retro', $this->lead, ['release_id' => $this->coreRelease->id]);
        $this->meetingNote('Teammate sync', $this->teammate);
        $this->meetingNote('All-hands', $this->lead);
        $secret = $this->meetingNote('Growth retro', $this->stranger, ['release_id' => $this->growthRelease->id]);
        $this->meetingNote('Stranger sync', $this->stranger);
        $this->meetingNote('Dana was there', $this->stranger, attendees: [$this->dev->id]);

        $page = $this->actingAs($this->dev)->get(route('meeting-notes.index'))->assertOk()
            ->assertSee(['Core retro', 'Teammate sync', 'All-hands', 'Dana was there']);
        $page->assertDontSee('Growth retro')->assertDontSee('Stranger sync');
        $this->actingAs($this->dev)->get(route('meeting-notes.show', $secret))->assertForbidden();
        // Filtering by another team's release returns nothing of theirs.
        $this->actingAs($this->dev)->get(route('meeting-notes.index', ['release' => $this->growthRelease->id]))
            ->assertOk()->assertDontSee('Growth retro');

        Sanctum::actingAs($this->dev);
        $this->getJson("/api/v1/meeting-notes/{$secret->id}")->assertForbidden();
        $this->app['auth']->forgetGuards();

        $this->actingAs($this->lead)->get(route('meeting-notes.show', $secret))->assertOk();
    }

    public function test_shared_notes_are_limited_to_teammates(): void
    {
        $mine = Note::create(['user_id' => $this->teammate->id, 'date' => today(), 'body' => '<p>Teammate log</p>', 'visibility' => Note::VISIBILITY_SHARED]);
        $theirs = Note::create(['user_id' => $this->stranger->id, 'date' => today(), 'body' => '<p>Stranger log</p>', 'visibility' => Note::VISIBILITY_SHARED]);

        $page = $this->actingAs($this->dev)->get(route('notes.index', ['date' => today()->toDateString()]))->assertOk()
            ->assertSee('Teammate log')->assertDontSee('Stranger log');
        $this->assertNotContains('Sam Stranger', $page->viewData('users')->pluck('name')->all());
        $this->assertNotContains('Sam Stranger', $page->viewData('authors')->pluck('name')->all());

        Sanctum::actingAs($this->dev);
        $this->getJson("/api/v1/notes/{$mine->id}")->assertOk();
        $this->getJson("/api/v1/notes/{$theirs->id}")->assertNotFound();
        $this->app['auth']->forgetGuards();

        $this->actingAs($this->lead)->get(route('notes.index', ['date' => today()->toDateString()]))
            ->assertOk()->assertSee('Stranger log');
    }
}
