<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\MeetingNote;
use App\Models\Note;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Developers and QA open Notes, Meetings and the Calendar on their own items,
 * and can still reach everything they're allowed to see. Other roles keep
 * seeing everything.
 */
class MineDefaultTest extends TestCase
{
    use RefreshDatabase;

    private User $member;

    private User $teammate;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-10 09:00:00');

        $this->team = Team::create(['name' => 'Core', 'color' => '#0891b2']);
        $this->member = $this->user(User::ROLE_DEVELOPER);
        $this->teammate = $this->user(User::ROLE_DEVELOPER);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['role' => $role]);
        $user->teams()->attach($this->team);

        return $user;
    }

    private function note(string $body, User $author, string $visibility = Note::VISIBILITY_SHARED, array $recipients = []): void
    {
        $note = Note::create(['user_id' => $author->id, 'date' => '2026-11-10', 'body' => "<p>{$body}</p>", 'visibility' => $visibility]);
        $note->recipients()->attach($recipients);
    }

    private function meeting(string $title, User $author, array $attendees = []): void
    {
        $note = MeetingNote::create([
            'title' => $title, 'body' => '<p>Discussion</p>', 'created_by' => $author->id, 'meeting_date' => '2026-11-10',
            'type' => array_key_first(MeetingNote::TYPES), 'visibility' => MeetingNote::VISIBILITY_EVERYONE,
        ]);
        $note->attendees()->attach($attendees);
    }

    private function event(string $title, User $creator, array $attendees = []): void
    {
        $event = Event::create([
            'title' => $title, 'created_by' => $creator->id, 'type' => 'meeting',
            'visibility' => Event::VISIBILITY_SHARED, 'starts_at' => '2026-11-10 10:00:00',
        ]);
        $event->attendees()->attach($attendees);
    }

    /** Seeds one page's items: the viewer's own, one addressed to them, and a teammate's. */
    private function seedPage(string $page): void
    {
        match ($page) {
            'notes.index' => [
                $this->note('Own item', $this->member),
                $this->note('Addressed item', $this->teammate, Note::VISIBILITY_SPECIFIC, [$this->member->id]),
                $this->note('Teammate item', $this->teammate),
            ],
            'meeting-notes.index' => [
                $this->meeting('Own item', $this->member),
                $this->meeting('Addressed item', $this->teammate, [$this->member->id]),
                $this->meeting('Teammate item', $this->teammate),
            ],
            'calendar.index' => [
                $this->event('Own item', $this->member),
                $this->event('Addressed item', $this->teammate, [$this->member->id]),
                $this->event('Teammate item', $this->teammate),
            ],
        };
    }

    /** @return list<string> */
    private function itemsOn(string $page, User $viewer, array $query = []): array
    {
        $content = $this->actingAs($viewer)->get(route($page, $query))->assertOk()->getContent();

        return collect(['Own item', 'Addressed item', 'Teammate item'])
            ->filter(fn (string $item) => str_contains($content, $item))
            ->values()
            ->all();
    }

    /** @return array<string, array{string, array<string, string>}> */
    public static function pages(): array
    {
        return [
            'notes' => ['notes.index', ['mine' => '0']],
            'meetings' => ['meeting-notes.index', ['mine' => '0']],
            'calendar' => ['calendar.index', ['show' => 'all']],
        ];
    }

    #[DataProvider('pages')]
    public function test_developers_and_qa_land_on_what_is_theirs_or_addressed_to_them(string $page): void
    {
        $this->seedPage($page);

        $this->assertSame(['Own item', 'Addressed item'], $this->itemsOn($page, $this->member));
    }

    #[DataProvider('pages')]
    public function test_a_qa_user_gets_the_same_default(string $page): void
    {
        $this->seedPage($page);

        $this->assertSame([], $this->itemsOn($page, $this->user(User::ROLE_QA)));
    }

    #[DataProvider('pages')]
    public function test_they_can_still_see_everything_allowed(string $page, array $everyone): void
    {
        $this->seedPage($page);

        $this->assertSame(['Own item', 'Addressed item', 'Teammate item'], $this->itemsOn($page, $this->member, $everyone));
    }

    #[DataProvider('pages')]
    public function test_leads_still_land_on_everything(string $page): void
    {
        $this->seedPage($page);

        $items = $this->itemsOn($page, $this->user(User::ROLE_TECH_LEAD));

        // Other people's items are listed. A note addressed only to someone else stays theirs.
        $this->assertContains('Own item', $items);
        $this->assertContains('Teammate item', $items);
    }

    public function test_a_filter_is_not_narrowed_on_notes_or_meetings(): void
    {
        $this->seedPage('notes.index');
        $this->seedPage('meeting-notes.index');

        $this->assertContains('Teammate item', $this->itemsOn('notes.index', $this->member, ['from' => '2026-11-01']));
        $this->assertContains('Teammate item', $this->itemsOn('meeting-notes.index', $this->member, ['search' => 'item']));
    }

    public function test_my_notes_never_includes_someone_elses_private_note(): void
    {
        $this->note('Teammate item', $this->teammate, Note::VISIBILITY_PRIVATE);

        $this->assertSame([], $this->itemsOn('notes.index', $this->member));
        $this->assertSame([], $this->itemsOn('notes.index', $this->member, ['mine' => '0']));
    }

    public function test_changing_month_keeps_the_chosen_calendar_tab(): void
    {
        $default = $this->actingAs($this->member)->get(route('calendar.index'))->assertOk();
        $default->assertDontSee('show=mine', false);
        $default->assertSee('show=all', false);

        // The previous, next and Today links all stay on All.
        $all = $this->actingAs($this->member)->get(route('calendar.index', ['show' => 'all']))->assertOk();
        $all->assertSee('month=10&amp;show=all', false);
        $all->assertSee('month=12&amp;show=all', false);

        // A lead's default is All, so it needs no parameter and Mine does.
        $lead = $this->actingAs($this->user(User::ROLE_TECH_LEAD))->get(route('calendar.index'))->assertOk();
        $lead->assertDontSee('show=all', false);
        $lead->assertSee('show=mine', false);
    }

    public function test_the_api_only_narrows_when_asked(): void
    {
        $this->seedPage('notes.index');
        $this->seedPage('meeting-notes.index');
        Sanctum::actingAs($this->member);

        $this->assertCount(3, $this->getJson('/api/v1/notes')->assertOk()->json('data'));
        $this->assertCount(2, $this->getJson('/api/v1/notes?mine=1')->assertOk()->json('data'));
        $this->assertCount(3, $this->getJson('/api/v1/meeting-notes')->assertOk()->json('data'));
        $this->assertCount(2, $this->getJson('/api/v1/meeting-notes?mine=1')->assertOk()->json('data'));
    }
}
