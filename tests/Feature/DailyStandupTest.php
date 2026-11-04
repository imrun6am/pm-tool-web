<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\AiFeatureSetting;
use App\Models\AiInsight;
use App\Models\DailyStandup;
use App\Models\MeetingNote;
use App\Models\TasksheetEntry;
use App\Models\Team;
use App\Models\User;
use App\Services\Ai\AiTransport;
use App\Services\DailyStandupService;
use App\Services\PeopleAnalyticsService;
use App\Support\StandupRun;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FakeAiTransport;
use Tests\TestCase;

class DailyStandupTest extends TestCase
{
    use RefreshDatabase;

    private const TODAY = '2026-11-04'; // a Wednesday

    private const YESTERDAY = '2026-11-03';

    private Team $team;

    private User $ana;

    private User $ben;

    protected function setUp(): void
    {
        parent::setUp();

        config(['planning.weekend_days' => ['friday'], 'planning.alternate_off_day' => '2026-08-01']);
        Carbon::setTestNow(self::TODAY.' 09:00:00');

        $this->team = Team::create(['name' => 'Platform', 'color' => '#0891b2']);
        $this->ana = $this->member('Ana', $this->team);
        $this->ben = $this->member('Ben', $this->team);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function member(string $name, Team $team, string $role = User::ROLE_DEVELOPER): User
    {
        $user = User::factory()->create(['name' => $name, 'role' => $role]);
        $team->members()->attach($user->id);

        return $user;
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    /** @param array<string, mixed> $fields */
    private function row(User $user, string $date, array $fields, ?Team $team = null): TasksheetEntry
    {
        return TasksheetEntry::create(['team_id' => ($team ?? $this->team)->id, 'user_id' => $user->id, 'date' => $date] + $fields);
    }

    private function generate(?string $date = null, bool $restore = false): StandupRun
    {
        return app(DailyStandupService::class)->generate($this->team, Carbon::parse($date ?? self::TODAY), $restore);
    }

    private function standup(?string $date = null): ?DailyStandup
    {
        return DailyStandup::forTeamOn($this->team, Carbon::parse($date ?? self::TODAY))->first();
    }

    private function person(User $user): ?array
    {
        return collect($this->standup()->content['people'])->firstWhere('id', $user->id);
    }

    // Creating

    public function test_creates_one_note_with_what_each_person_did_and_plans(): void
    {
        $this->row($this->ana, self::YESTERDAY, ['result' => '<div>Shipped login</div>', 'tickets' => '<div>PM-12</div>']);
        $this->row($this->ana, self::TODAY, ['plan' => '<div>Start billing</div>']);

        $run = $this->generate();

        $this->assertSame(StandupRun::CREATED, $run->status);
        $note = $run->note;
        $this->assertSame(MeetingNote::TYPE_STANDUP, $note->type);
        $this->assertSame(self::TODAY, $note->meeting_date->toDateString());
        $this->assertStringContainsString('Platform standup', $note->title);
        $this->assertNull($note->created_by);
        $this->assertSame('', $note->body);

        $ana = $this->person($this->ana);
        $this->assertStringContainsString('Shipped login', $ana['previous']['result']);
        $this->assertStringContainsString('PM-12', $ana['previous']['tickets']);
        $this->assertStringContainsString('Start billing', $ana['today']['plan']);
        $this->assertSame(self::YESTERDAY, $this->standup()->content['previous_date']);
    }

    public function test_the_page_shows_the_listing_and_the_list_shows_the_note(): void
    {
        $this->row($this->ana, self::YESTERDAY, ['result' => '<div>Shipped login</div>']);
        $this->row($this->ana, self::TODAY, ['plan' => '<div>Start <strong>billing</strong></div>']);
        $note = $this->generate()->note;

        $this->actingAs($this->ana)->get(route('meeting-notes.show', $note))->assertOk()
            ->assertSee('Generated from the tasksheet')
            ->assertSee('Shipped login')
            ->assertSee('<strong>billing</strong>', false)
            ->assertSee('Ben');

        $this->actingAs($this->user(User::ROLE_ADMIN))->get(route('meeting-notes.index'))->assertOk()
            ->assertSee($note->title)
            ->assertSee('From the tasksheet');
    }

    public function test_search_finds_a_person_and_their_work(): void
    {
        $this->row($this->ana, self::TODAY, ['plan' => '<div>Migrate the invoicing queue</div>']);
        $note = $this->generate()->note;
        $lead = $this->user(User::ROLE_ADMIN);

        $this->actingAs($lead)->get(route('meeting-notes.index', ['search' => 'invoicing']))->assertSee($note->title);
        $this->actingAs($lead)->get(route('meeting-notes.index', ['search' => 'strong']))->assertDontSee($note->title);
    }

    public function test_nothing_is_notified_or_logged(): void
    {
        $this->row($this->ana, self::TODAY, ['plan' => 'Plan']);
        Activity::query()->delete();

        $this->generate();
        TasksheetEntry::first()->update(['plan' => 'New plan']);
        Activity::query()->delete();
        $this->generate();

        $this->assertSame(0, DB::table('notifications')->count());
        $this->assertSame(0, Activity::count());
    }

    // Updating

    public function test_running_again_updates_the_same_note(): void
    {
        $entry = $this->row($this->ana, self::TODAY, ['plan' => '<div>First plan</div>']);
        $note = $this->generate()->note;
        $this->assertSame(StandupRun::UNCHANGED, $this->generate()->status);

        $entry->update(['plan' => '<div>Second plan</div>']);
        $run = $this->generate();

        $this->assertSame(StandupRun::UPDATED, $run->status);
        $this->assertSame($note->id, $run->note->id);
        $this->assertSame(1, MeetingNote::count());
        $this->assertSame(1, DailyStandup::count());
        $this->assertStringContainsString('Second plan', $this->person($this->ana)['today']['plan']);
    }

    public function test_an_unchanged_run_writes_nothing(): void
    {
        $this->row($this->ana, self::TODAY, ['plan' => 'Plan']);
        $this->generate();
        $before = $this->standup()->generated_at;

        Carbon::setTestNow(self::TODAY.' 09:15:00');
        $this->generate();

        $this->assertTrue($this->standup()->generated_at->equalTo($before));
    }

    public function test_a_finished_task_on_the_tasksheet_shows_up_on_the_next_run(): void
    {
        $entry = $this->row($this->ana, self::TODAY, ['plan' => 'Plan']);
        $this->generate();

        // What TasksheetResultSync does when a task moves to Done.
        $entry->result = '<ul><li>Completed: Fix login</li></ul>';
        $entry->save();
        $this->generate();

        $this->assertStringContainsString('Completed: Fix login', $this->person($this->ana)['today']['result']);
    }

    // Hand edits

    public function test_notes_and_title_written_by_hand_are_kept(): void
    {
        $entry = $this->row($this->ana, self::TODAY, ['plan' => '<div>First</div>']);
        $note = $this->generate()->note;

        $this->actingAs($this->ana)->put(route('meeting-notes.update', $note), [
            'title' => 'Our standup',
            'body' => '<div>We agreed to freeze on Thursday</div>',
            'attendees' => $note->attendees()->pluck('users.id')->all(),
        ])->assertRedirect(route('meeting-notes.show', $note));

        $entry->update(['plan' => '<div>Second</div>']);
        $this->assertSame(StandupRun::UPDATED, $this->generate()->status);

        $note->refresh();
        $this->assertSame('Our standup', $note->title);
        $this->assertStringContainsString('freeze on Thursday', $note->body);
        $this->assertStringContainsString('Second', $this->person($this->ana)['today']['plan']);
    }

    public function test_attendees_edited_by_hand_are_left_alone(): void
    {
        $entry = $this->row($this->ana, self::TODAY, ['plan' => 'Plan']);
        $note = $this->generate()->note;
        $this->assertEqualsCanonicalizing([$this->ana->id, $this->ben->id], $note->attendees()->pluck('users.id')->all());

        $this->actingAs($this->ana)->put(route('meeting-notes.update', $note), [
            'title' => $note->title, 'body' => '', 'attendees' => [$this->ana->id],
        ])->assertSessionHasNoErrors();

        $newcomer = $this->member('Cleo', $this->team);
        $entry->update(['plan' => 'Changed']);
        $this->generate();

        $this->assertSame([$this->ana->id], $note->attendees()->pluck('users.id')->all());
        $this->assertNotNull($this->standup()->attendees_edited_at);
        $this->assertNotContains($newcomer->id, $note->attendees()->pluck('users.id'));
    }

    public function test_saving_without_changing_attendees_keeps_them_automatic(): void
    {
        $this->row($this->ana, self::TODAY, ['plan' => 'Plan']);
        $note = $this->generate()->note;

        $this->actingAs($this->ana)->put(route('meeting-notes.update', $note), [
            'title' => $note->title, 'body' => '<div>A note</div>', 'attendees' => $note->attendees()->pluck('users.id')->all(),
        ])->assertSessionHasNoErrors();

        $newcomer = $this->member('Cleo', $this->team);
        $this->generate();

        $this->assertContains($newcomer->id, $note->attendees()->pluck('users.id'));
    }

    public function test_type_date_release_and_visibility_cant_be_changed(): void
    {
        $this->row($this->ana, self::TODAY, ['plan' => 'Plan']);
        $note = $this->generate()->note;

        $this->actingAs($this->ana)->put(route('meeting-notes.update', $note), [
            'title' => 'Renamed', 'body' => '', 'type' => 'sync_up', 'meeting_date' => '2026-01-01',
            'visibility' => MeetingNote::VISIBILITY_ATTENDEES, 'attendees' => [],
        ])->assertSessionHasNoErrors();

        $note->refresh();
        $this->assertSame('Renamed', $note->title);
        $this->assertSame(MeetingNote::TYPE_STANDUP, $note->type);
        $this->assertSame(self::TODAY, $note->meeting_date->toDateString());
        $this->assertSame(MeetingNote::VISIBILITY_EVERYONE, $note->visibility);

        $this->generate();
        $this->assertSame(1, MeetingNote::count());
    }

    public function test_the_edit_form_only_offers_what_can_change(): void
    {
        $this->row($this->ana, self::TODAY, ['plan' => 'Plan']);
        $note = $this->generate()->note;

        $this->actingAs($this->ana)->get(route('meeting-notes.edit', $note))->assertOk()
            ->assertSee('What you write here is kept')
            ->assertDontSee('Related release')
            ->assertDontSee('Meeting type')
            ->assertDontSee('Meeting date');
    }

    public function test_an_ordinary_note_still_needs_a_body_and_can_change_type(): void
    {
        $author = $this->user(User::ROLE_ADMIN);
        $note = MeetingNote::create(['created_by' => $author->id, 'title' => 'Sync', 'meeting_date' => self::TODAY, 'body' => 'x']);

        $this->actingAs($author)->put(route('meeting-notes.update', $note), [
            'title' => 'Sync', 'meeting_date' => self::TODAY, 'body' => '',
        ])->assertSessionHasErrors('body');

        $this->actingAs($author)->put(route('meeting-notes.update', $note), [
            'title' => 'Sync', 'meeting_date' => self::TODAY, 'body' => 'y', 'type' => 'sync_up',
        ])->assertSessionHasNoErrors();
        $this->assertSame('sync_up', $note->refresh()->type);
    }

    // Deleting

    public function test_a_deleted_standup_stays_deleted_on_the_schedule_and_can_be_asked_for_again(): void
    {
        $this->row($this->ana, self::TODAY, ['plan' => 'Plan']);
        $note = $this->generate()->note;
        $lead = $this->user(User::ROLE_ADMIN);

        $this->actingAs($lead)->delete(route('meeting-notes.destroy', $note))->assertRedirect();
        $this->assertSame(0, MeetingNote::count());

        $this->assertSame(StandupRun::DELETED, $this->generate()->status);
        $this->artisan('standup:generate')->assertSuccessful();
        $this->assertSame(0, MeetingNote::count());

        $this->actingAs($lead)->post(route('standups.store'), ['team_id' => $this->team->id, 'date' => self::TODAY])->assertRedirect();
        $this->assertSame(1, MeetingNote::count());
        $this->assertSame(1, DailyStandup::count());
        $this->assertSame(MeetingNote::first()->id, $this->standup()->meeting_note_id);
    }

    public function test_a_member_cant_delete_it(): void
    {
        $this->row($this->ana, self::TODAY, ['plan' => 'Plan']);
        $note = $this->generate()->note;

        $this->actingAs($this->ana)->delete(route('meeting-notes.destroy', $note))->assertForbidden();
    }

    // Working days

    public function test_friday_and_the_alternate_saturday_are_skipped(): void
    {
        Carbon::setTestNow('2026-11-14 09:00:00');
        $this->row($this->ana, '2026-11-06', ['plan' => 'Friday']);
        $this->row($this->ana, '2026-11-07', ['plan' => 'Saturday off']);

        $this->assertSame(StandupRun::DAY_OFF, $this->generate('2026-11-06')->status);
        $this->assertSame(StandupRun::DAY_OFF, $this->generate('2026-11-07')->status);
        $this->artisan('standup:generate', ['--date' => '2026-11-06'])->assertSuccessful();

        $this->assertSame(0, MeetingNote::count());
    }

    public function test_after_the_weekend_did_comes_from_the_last_working_day(): void
    {
        Carbon::setTestNow('2026-11-14 09:00:00'); // a worked Saturday; Friday is off
        $this->row($this->ana, '2026-11-12', ['result' => '<div>Thursday work</div>']);

        $run = $this->generate('2026-11-14');

        $this->assertSame(StandupRun::CREATED, $run->status);
        $this->assertSame('2026-11-12', $run->note->standup->content['previous_date']);
        $this->assertStringContainsString('Thursday work', $run->note->standup->content['people'][0]['previous']['result']);
    }

    public function test_it_is_scheduled_through_working_days_only(): void
    {
        // The time window is read when the schedule is defined, so define it at each time.
        $passesAt = function (string $time): bool {
            Carbon::setTestNow($time);
            require base_path('routes/console.php');
            $event = collect(app(Schedule::class)->events())->last(fn ($e) => str_contains((string) $e->command, 'standup:generate'));

            $this->assertSame('*/15 * * * *', $event->expression);

            return $event->filtersPass(app());
        };

        $this->assertFalse($passesAt(self::TODAY.' 08:30:00'));
        $this->assertTrue($passesAt(self::TODAY.' 08:45:00'));
        $this->assertTrue($passesAt(self::TODAY.' 20:00:00'));
        $this->assertFalse($passesAt(self::TODAY.' 20:15:00'));
        $this->assertFalse($passesAt('2026-11-06 09:00:00')); // Friday
        $this->assertFalse($passesAt('2026-11-07 09:00:00')); // alternate Saturday
        $this->assertTrue($passesAt('2026-11-14 09:00:00')); // worked Saturday
    }

    // Who has something to report

    public function test_a_team_with_nothing_to_report_is_skipped(): void
    {
        $this->assertSame(StandupRun::NOTHING_TO_REPORT, $this->generate()->status);

        // A plan yesterday with no result says nothing about what was done.
        $this->row($this->ana, self::YESTERDAY, ['plan' => 'Old plan']);
        $this->row($this->ben, self::TODAY, ['work_points' => 3]);

        $this->assertSame(StandupRun::NOTHING_TO_REPORT, $this->generate()->status);
        $this->assertSame(0, MeetingNote::count());
        $this->assertSame(0, DailyStandup::count());
    }

    public function test_the_future_has_no_standup(): void
    {
        $this->row($this->ana, self::TODAY, ['plan' => 'Plan']);

        $this->assertSame(StandupRun::NOTHING_TO_REPORT, $this->generate('2026-11-05')->status);
    }

    public function test_the_command_covers_active_teams_and_can_target_one(): void
    {
        $other = Team::create(['name' => 'Mobile', 'color' => '#0891b2']);
        $archived = Team::create(['name' => 'Old', 'color' => '#0891b2', 'archived_at' => now()]);
        $this->row($this->member('Dee', $other), self::TODAY, ['plan' => 'Plan'], $other);
        $this->row($this->member('Eli', $archived), self::TODAY, ['plan' => 'Plan'], $archived);
        $this->row($this->ana, self::TODAY, ['plan' => 'Plan']);

        $this->artisan('standup:generate', ['--team' => $other->id])->assertSuccessful();
        $this->assertSame(1, MeetingNote::count());

        $this->artisan('standup:generate')->assertSuccessful();
        $this->assertSame(2, MeetingNote::count());
        $this->assertSame(0, DailyStandup::where('team_id', $archived->id)->count());

        $this->artisan('standup:generate', ['--team' => $archived->id])->assertFailed();
        $this->artisan('standup:generate', ['--date' => 'not-a-date'])->assertFailed();
    }

    // Leave and not filled

    public function test_full_day_leave_is_named_and_not_an_attendee(): void
    {
        $this->row($this->ana, self::TODAY, ['leave_type' => 'sick']);
        $this->row($this->ben, self::TODAY, ['plan' => 'Plan']);

        $note = $this->generate()->note;
        $content = $this->standup()->content;

        $this->assertSame([['id' => $this->ana->id, 'name' => 'Ana', 'leave' => 'Sick leave', 'full_day' => true]], $content['on_leave']);
        $this->assertSame([], $content['not_filled']);
        $this->assertSame(1, $content['counts']['on_leave']);
        $this->assertNotContains($this->ana->id, $note->attendees()->pluck('users.id'));
        $this->assertContains($this->ben->id, $note->attendees()->pluck('users.id'));
    }

    public function test_going_on_leave_later_updates_the_note_and_attendees(): void
    {
        $entry = $this->row($this->ana, self::TODAY, ['plan' => 'Plan']);
        $this->row($this->ben, self::TODAY, ['plan' => 'Plan']);
        $note = $this->generate()->note;
        $this->assertContains($this->ana->id, $note->attendees()->pluck('users.id'));

        $entry->update(['leave_type' => 'casual', 'plan' => null]);
        $this->assertSame(StandupRun::UPDATED, $this->generate()->status);

        $this->assertNotContains($this->ana->id, $note->attendees()->pluck('users.id'));
        $this->assertSame('Ana', $this->standup()->content['on_leave'][0]['name']);
    }

    public function test_half_day_leave_is_named_and_their_plan_is_listed(): void
    {
        $this->row($this->ana, self::TODAY, ['leave_type' => 'half_day', 'plan' => '<div>Morning only</div>']);

        $note = $this->generate()->note;

        $this->assertStringContainsString('Morning only', $this->person($this->ana)['today']['plan']);
        $this->assertSame('Half day leave', $this->person($this->ana)['today']['leave']);
        $this->assertFalse($this->standup()->content['on_leave'][0]['full_day']);
        $this->assertContains($this->ana->id, $note->attendees()->pluck('users.id'));
    }

    public function test_leave_yesterday_is_not_reported_as_work(): void
    {
        $this->row($this->ana, self::YESTERDAY, ['leave_type' => 'casual']);
        $this->row($this->ben, self::TODAY, ['plan' => 'Plan']);

        $this->generate();

        $this->assertNull($this->person($this->ana));
        $this->assertSame('Ana', $this->standup()->content['not_filled'][0]['name']);
    }

    public function test_someone_who_hasnt_filled_it_in_is_named(): void
    {
        $this->row($this->ana, self::TODAY, ['plan' => 'Plan']);
        $this->row($this->ben, self::YESTERDAY, ['result' => 'Did things']);

        $this->generate();
        $content = $this->standup()->content;

        $this->assertSame([['id' => $this->ben->id, 'name' => 'Ben']], $content['not_filled']);
        $this->assertSame(['people' => 2, 'filled' => 1, 'on_leave' => 0, 'not_filled' => 1], $content['counts']);
        $this->assertStringContainsString('Did things', $this->person($this->ben)['previous']['result']);
    }

    public function test_attendees_are_the_team_and_its_lead_minus_who_missed_it(): void
    {
        $lead = $this->user(User::ROLE_TEAM_LEAD);
        $this->team->update(['team_lead_id' => $lead->id]);
        $gone = $this->member('Gone', $this->team);
        $gone->update(['deactivated_at' => now()->subWeek()]);
        $this->row($this->ana, self::TODAY, ['plan' => 'Plan'])->forceFill(['standup_attended' => false])->save();
        $this->row($this->ben, self::TODAY, ['plan' => 'Plan']);

        $note = $this->generate()->note;

        $this->assertEqualsCanonicalizing([$lead->id, $this->ben->id], $note->attendees()->pluck('users.id')->all());
        $this->assertNotContains('Gone', collect($this->standup()->content['not_filled'])->pluck('name'));
    }

    // Restricted fields

    public function test_feedback_and_scores_never_reach_the_standup(): void
    {
        $entry = $this->row($this->ana, self::TODAY, [
            'plan' => 'Plan', 'feedback' => 'SECRET-FEEDBACK', 'work_points' => 7771, 'ticket_points' => 7772, 'ticket_count' => 7773,
        ]);
        $entry->forceFill(['standup_attended' => false])->save();
        $this->row($this->ana, self::YESTERDAY, ['result' => 'Result', 'feedback' => 'SECRET-OLD', 'work_points' => 7774]);

        $note = $this->generate()->note;
        $standup = $this->standup();
        $stored = json_encode($standup->content).$standup->search_text;

        foreach (['SECRET', '7771', '7772', '7773', '7774', 'standup_attended'] as $needle) {
            $this->assertStringNotContainsString($needle, $stored);
        }

        Sanctum::actingAs($this->user(User::ROLE_ADMIN));
        $this->getJson("/api/v1/meeting-notes/{$note->id}")->assertOk()->assertDontSee('SECRET')->assertDontSee('7771');
    }

    // Visibility

    public function test_visibility_matches_the_teams_tasksheet(): void
    {
        $this->row($this->ana, self::TODAY, ['leave_type' => 'sick']);
        $note = $this->generate()->note;
        $other = Team::create(['name' => 'Mobile', 'color' => '#0891b2']);
        $outsider = $this->member('Outsider', $other);
        $invited = $this->member('Invited', $other, User::ROLE_QA);
        $note->attendees()->attach($invited->id);
        $former = $this->member('Former', $this->team);
        $note->attendees()->attach($former->id);
        $this->team->memberRecords()->updateExistingPivot($former->id, ['left_at' => now()]);

        // On the team, and on leave so not an attendee.
        $this->actingAs($this->ana)->get(route('meeting-notes.show', $note))->assertOk();
        $this->assertTrue(MeetingNote::visibleTo($this->ana)->whereKey($note->id)->exists());

        foreach ([$outsider, $invited, $former] as $user) {
            $this->actingAs($user)->get(route('meeting-notes.show', $note))->assertForbidden();
            $this->assertFalse(MeetingNote::visibleTo($user)->whereKey($note->id)->exists(), $user->name);
        }

        foreach ([User::ROLE_ADMIN, User::ROLE_CTO, User::ROLE_TECH_LEAD, User::ROLE_TEAM_LEAD, User::ROLE_VIEWER] as $role) {
            $this->actingAs($this->user($role))->get(route('meeting-notes.show', $note))->assertOk();
        }
    }

    public function test_the_api_applies_the_same_visibility(): void
    {
        $this->row($this->ana, self::TODAY, ['plan' => 'Plan']);
        $note = $this->generate()->note;
        $outsider = $this->member('Outsider', Team::create(['name' => 'Mobile', 'color' => '#0891b2']));

        Sanctum::actingAs($outsider);
        $this->getJson("/api/v1/meeting-notes/{$note->id}")->assertForbidden();
        $this->assertSame([], $this->getJson('/api/v1/meeting-notes?mine=0')->assertOk()->json('data'));

        Sanctum::actingAs($this->ben);
        $this->getJson("/api/v1/meeting-notes/{$note->id}")->assertOk()
            ->assertJsonPath('data.author', null)
            ->assertJsonPath('data.body', '')
            ->assertJsonPath('data.standup.team_name', 'Platform')
            ->assertJsonPath('data.standup.date', self::TODAY)
            ->assertJsonPath('data.standup.people.0.name', 'Ana')
            ->assertJsonPath('data.standup.can_refresh', true)
            ->assertJsonPath('data.can_edit', true)
            ->assertJsonPath('data.can_delete', false);
    }

    public function test_an_ordinary_notes_visibility_is_unchanged(): void
    {
        $outsider = $this->member('Outsider', Team::create(['name' => 'Mobile', 'color' => '#0891b2']));
        $note = MeetingNote::create(['created_by' => $this->ana->id, 'title' => 'Teammates', 'meeting_date' => self::TODAY, 'body' => 'x']);

        $this->assertTrue(MeetingNote::visibleTo($this->ben)->whereKey($note->id)->exists());
        $this->assertFalse(MeetingNote::visibleTo($outsider)->whereKey($note->id)->exists());
        $note->attendees()->attach($outsider->id);
        $this->assertTrue(MeetingNote::visibleTo($outsider)->whereKey($note->id)->exists());
        $this->assertNull($note->standup);
        $this->assertFalse($outsider->can('update', $note));
    }

    public function test_leads_and_team_members_may_edit_and_others_may_not(): void
    {
        $this->row($this->ana, self::TODAY, ['plan' => 'Plan']);
        $note = $this->generate()->note;
        $outsider = $this->member('Outsider', Team::create(['name' => 'Mobile', 'color' => '#0891b2']));

        $this->assertTrue($this->ben->can('update', $note));
        $this->assertTrue($this->user(User::ROLE_TEAM_LEAD)->can('update', $note));
        $this->assertFalse($this->user(User::ROLE_VIEWER)->can('update', $note));
        $this->assertFalse($outsider->can('update', $note));

        $this->actingAs($outsider)->put(route('meeting-notes.update', $note), ['title' => 'x', 'body' => ''])->assertForbidden();
    }

    // AI

    private function digest(string $status = AiInsight::STATUS_READY, ?array $payload = null): AiInsight
    {
        return AiInsight::create([
            'kind' => AiInsight::KIND_STANDUP_DIGEST,
            'subject_type' => $this->team->getMorphClass(),
            'subject_id' => $this->team->id,
            'for_date' => self::YESTERDAY,
            'status' => $status,
            'payload' => $payload ?? [
                'filled' => 1,
                'summary' => 'The team shipped login.',
                'highlights' => [['member' => 'Ana', 'text' => 'HIGHLIGHT-TEXT']],
                'blockers' => [['member' => 'Ben', 'text' => 'Waiting on the API key']],
                'follow_ups' => ['LEAD-ONLY-FOLLOW-UP'],
                'missed_standup' => ['MISSED-NAME'],
            ],
        ]);
    }

    public function test_it_works_fully_with_ai_unconfigured_and_never_calls_it(): void
    {
        config(['services.anthropic.key' => null]);
        $transport = new FakeAiTransport;
        $this->app->instance(AiTransport::class, $transport);
        $this->row($this->ana, self::TODAY, ['plan' => 'Plan']);
        $this->digest();

        $run = $this->generate();

        $this->assertSame(StandupRun::CREATED, $run->status);
        $this->assertNull($this->standup()->content['ai']);
        $this->assertNotNull($this->person($this->ana));
        $this->assertSame([], $transport->requests);
        $this->actingAs($this->ana)->get(route('meeting-notes.show', $run->note))->assertOk()->assertDontSee('AI summary');
    }

    public function test_it_works_with_the_feature_switched_off(): void
    {
        config(['services.anthropic.key' => 'test-key']);
        AiFeatureSetting::create(['feature' => AiInsight::KIND_STANDUP_DIGEST, 'enabled' => false]);
        $this->row($this->ana, self::TODAY, ['plan' => 'Plan']);
        $this->digest();

        $this->generate();

        $this->assertNull($this->standup()->content['ai']);
        $this->assertNotNull($this->person($this->ana));
    }

    public function test_a_failed_digest_leaves_the_standup_whole(): void
    {
        config(['services.anthropic.key' => 'test-key']);
        $this->row($this->ana, self::TODAY, ['plan' => 'Plan']);
        $this->digest(AiInsight::STATUS_FAILED);

        $this->assertSame(StandupRun::CREATED, $this->generate()->status);
        $this->assertNull($this->standup()->content['ai']);
    }

    public function test_a_ready_digest_adds_its_summary_and_blockers_only_and_makes_no_new_call(): void
    {
        config(['services.anthropic.key' => 'test-key']);
        $transport = new FakeAiTransport;
        $this->app->instance(AiTransport::class, $transport);
        $this->row($this->ana, self::TODAY, ['plan' => 'Plan']);
        $note = $this->generate()->note;
        $this->assertNull($this->standup()->content['ai']);

        // The digest finishes after the first run; the next one picks it up.
        $this->digest();
        $this->assertSame(StandupRun::UPDATED, $this->generate()->status);

        $ai = $this->standup()->content['ai'];
        $this->assertSame(self::YESTERDAY, $ai['covers']);
        $this->assertSame('The team shipped login.', $ai['summary']);
        $this->assertSame([['member' => 'Ben', 'text' => 'Waiting on the API key']], $ai['blockers']);
        $this->assertSame([], $transport->requests);

        $this->actingAs($this->ana)->get(route('meeting-notes.show', $note))->assertOk()
            ->assertSee('AI summary of Tue, Nov 3')
            ->assertSee('Waiting on the API key')
            ->assertDontSee('LEAD-ONLY-FOLLOW-UP')
            ->assertDontSee('HIGHLIGHT-TEXT')
            ->assertDontSee('MISSED-NAME');
    }

    public function test_ai_text_is_escaped(): void
    {
        config(['services.anthropic.key' => 'test-key']);
        $this->row($this->ana, self::TODAY, ['plan' => 'Plan']);
        $this->digest(payload: ['filled' => 1, 'summary' => '<script>alert(1)</script>', 'blockers' => [['member' => '<b>x</b>', 'text' => '<img src=x>']]]);
        $note = $this->generate()->note;

        $this->actingAs($this->ana)->get(route('meeting-notes.show', $note))->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('<img src=x>', false)
            ->assertDontSee('<b>x</b>', false);
    }

    // Web and API

    public function test_web_generate_needs_a_lead_or_a_team_member(): void
    {
        $this->row($this->ana, self::TODAY, ['plan' => 'Plan']);
        $payload = ['team_id' => $this->team->id, 'date' => self::TODAY];
        $outsider = $this->member('Outsider', Team::create(['name' => 'Mobile', 'color' => '#0891b2']));

        $this->actingAs($outsider)->post(route('standups.store'), $payload)->assertForbidden();
        $this->actingAs($this->user(User::ROLE_VIEWER))->post(route('standups.store'), $payload)->assertForbidden();
        $this->assertSame(0, MeetingNote::count());

        $response = $this->actingAs($this->ben)->post(route('standups.store'), $payload);

        $this->assertSame(1, MeetingNote::count());
        $response->assertRedirect(route('meeting-notes.show', MeetingNote::first()));
    }

    public function test_web_generate_explains_when_there_is_nothing_to_make(): void
    {
        $lead = $this->user(User::ROLE_ADMIN);

        $this->actingAs($lead)->from('/tasksheet')->post(route('standups.store'), ['team_id' => $this->team->id])
            ->assertRedirect('/tasksheet')->assertSessionHas('error');
        $this->actingAs($lead)->post(route('standups.store'), ['team_id' => $this->team->id, 'date' => '2026-11-05'])
            ->assertSessionHasErrors('date');
    }

    public function test_the_api_generates_and_refreshes(): void
    {
        $this->row($this->ana, self::TODAY, ['plan' => 'Plan']);
        $payload = ['team_id' => $this->team->id, 'date' => self::TODAY];

        Sanctum::actingAs($this->user(User::ROLE_VIEWER));
        $this->postJson('/api/v1/standups', $payload)->assertForbidden();

        Sanctum::actingAs($this->member('Outsider', Team::create(['name' => 'Mobile', 'color' => '#0891b2'])));
        $this->postJson('/api/v1/standups', $payload)->assertForbidden();

        Sanctum::actingAs($this->ana);
        $this->postJson('/api/v1/standups', $payload)->assertCreated()->assertJsonPath('data.standup.people.0.name', 'Ana');
        $this->postJson('/api/v1/standups', $payload)->assertOk()->assertJsonPath('message', 'The standup is already up to date.');
        $this->postJson('/api/v1/standups', ['team_id' => $this->team->id, 'date' => '2026-11-06'])->assertUnprocessable();
        $this->assertSame(1, MeetingNote::count());
    }

    public function test_the_tasksheet_links_to_the_standup_or_offers_to_generate_it(): void
    {
        $viewer = $this->user(User::ROLE_VIEWER);
        $page = fn (User $user) => $this->actingAs($user)->get(route('tasksheet.index', ['team' => $this->team->id, 'date' => self::TODAY]));

        $page($this->ana)->assertOk()->assertSee('Generate standup');
        $page($viewer)->assertOk()->assertDontSee('Generate standup')->assertDontSee('Open standup');

        $this->row($this->ana, self::TODAY, ['plan' => 'Plan']);
        $note = $this->generate()->note;

        $page($viewer)->assertOk()->assertSee('Open standup')->assertSee(route('meeting-notes.show', $note), false);
    }

    public function test_the_type_is_published_to_clients(): void
    {
        Sanctum::actingAs($this->ana);

        $this->assertContains('standup', array_column($this->getJson('/api/v1/meta')->assertOk()->json('data.meeting_note_types'), 'value'));
    }

    // Elsewhere in the app

    public function test_generated_attendance_doesnt_count_in_analytics(): void
    {
        $this->row($this->ana, self::TODAY, ['plan' => 'Plan']);
        $note = $this->generate()->note;
        $this->assertContains($this->ana->id, $note->attendees()->pluck('users.id'));

        $collaboration = function () {
            $report = app(PeopleAnalyticsService::class)->report([
                'from' => Carbon::parse('2026-11-01'), 'to' => Carbon::parse(self::TODAY), 'team_id' => null,
            ]);

            return collect($report['people'])->first(fn ($r) => $r['user']->is($this->ana))['record']['collaboration'];
        };

        $this->assertSame(0, $collaboration()['notesAttended']);
        $this->assertSame(0, $collaboration()['notesWritten']);

        $written = MeetingNote::create(['created_by' => $this->ben->id, 'title' => 'Real', 'meeting_date' => self::TODAY, 'body' => 'x']);
        $written->attendees()->attach($this->ana->id);

        $this->assertSame(1, $collaboration()['notesAttended']);
    }

    public function test_generated_notes_dont_crowd_written_minutes_out_of_ask_ai(): void
    {
        config(['services.anthropic.key' => 'test-key']);
        $transport = (new FakeAiTransport)->respondUsing(fn () => ['answer' => 'Fine.', 'citations' => [], 'follow_ups' => []]);
        $this->app->instance(AiTransport::class, $transport);
        $this->row($this->ana, self::TODAY, ['plan' => 'Plan']);
        $note = $this->generate()->note;
        MeetingNote::create(['created_by' => $this->ben->id, 'title' => 'Real meeting', 'meeting_date' => self::YESTERDAY, 'body' => '<div>Go with vendor B</div>', 'type' => 'sync_up']);

        $this->actingAs($this->ana)->postJson(route('ai.ask'), ['question' => 'What did we decide in the last meeting?'])->assertOk();

        $this->assertStringContainsString('Go with vendor B', $transport->prompt());
        $this->assertStringNotContainsString($note->title, $transport->prompt());
    }

    public function test_deleting_a_team_keeps_the_note_readable(): void
    {
        $this->row($this->ana, self::TODAY, ['plan' => 'Plan']);
        $note = $this->generate()->note;

        $this->team->delete();

        $this->assertSame(0, DailyStandup::count());
        $this->actingAs($this->user(User::ROLE_ADMIN))->get(route('meeting-notes.show', $note->fresh()))->assertOk();
    }
}
