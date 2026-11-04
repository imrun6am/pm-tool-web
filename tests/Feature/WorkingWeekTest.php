<?php

namespace Tests\Feature;

use App\Models\AiInsight;
use App\Models\Project;
use App\Models\Release;
use App\Models\ReleaseOffDay;
use App\Models\TasksheetEntry;
use App\Models\Team;
use App\Models\User;
use App\Services\ReleaseOffDayService;
use App\Services\TeamService;
use App\Support\WorkingWeek;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** The weekend comes from one setting, and the whole app follows it. */
class WorkingWeekTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['planning.weekend_days' => ['friday'], 'planning.alternate_off_day' => null]);
    }

    private function release(string $start, string $end): Release
    {
        return Release::create([
            'project_id' => Project::create(['name' => 'Shop', 'color' => '#0891b2'])->id,
            'team_id' => Team::create(['name' => 'Core', 'color' => '#0891b2'])->id,
            'name' => 'Checkout', 'year' => 2026, 'quarter' => 4,
            'start_date' => $start, 'end_date' => $end,
        ]);
    }

    public function test_friday_is_the_weekend_and_saturday_and_sunday_are_not(): void
    {
        $this->assertTrue(WorkingWeek::isWeekend(Carbon::parse('2026-11-06')));
        $this->assertFalse(WorkingWeek::isWeekend(Carbon::parse('2026-11-07')));
        $this->assertTrue(WorkingWeek::isWorkingDay(Carbon::parse('2026-11-08')));
        $this->assertSame('Friday', WorkingWeek::weekendLabel());
        $this->assertSame([5], WorkingWeek::weekendDayNumbers());
    }

    #[DataProvider('neighbours')]
    public function test_it_finds_the_working_day_before_and_after(string $day, string $previous, string $next): void
    {
        $from = Carbon::parse($day);

        $this->assertSame($previous, WorkingWeek::previous($from)->toDateString());
        $this->assertSame($next, WorkingWeek::next($from)->toDateString());
        $this->assertSame($day, $from->toDateString(), 'The date passed in is left alone.');
    }

    public static function neighbours(): array
    {
        return [
            'Thursday' => ['2026-11-05', '2026-11-04', '2026-11-07'],
            'Friday' => ['2026-11-06', '2026-11-05', '2026-11-07'],
            'Saturday' => ['2026-11-07', '2026-11-05', '2026-11-08'],
        ];
    }

    public function test_previous_defaults_to_today(): void
    {
        $this->travelTo('2026-11-07 09:00:00');

        $this->assertSame('2026-11-05', WorkingWeek::previous()->toDateString());
    }

    public function test_more_than_one_weekend_day_works(): void
    {
        config(['planning.weekend_days' => ['friday', 'saturday']]);

        $this->assertSame('2026-11-08', WorkingWeek::next(Carbon::parse('2026-11-05'))->toDateString());
        $this->assertSame([5, 6], WorkingWeek::weekendDayNumbers());
        $this->assertSame('Friday and Saturday', WorkingWeek::weekendLabel());
    }

    public function test_a_setting_of_every_day_cant_hang_the_app(): void
    {
        config(['planning.weekend_days' => ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday']]);

        $this->assertSame([], WorkingWeek::weekendDays());
        $this->assertSame('2026-11-07', WorkingWeek::next(Carbon::parse('2026-11-06'))->toDateString());
    }

    public function test_mark_weekends_marks_the_configured_days(): void
    {
        $release = $this->release('2026-11-02', '2026-11-15');

        $this->assertSame(2, app(ReleaseOffDayService::class)->markWeekends($release));

        $this->assertSame(
            ['2026-11-06', '2026-11-13'],
            ReleaseOffDay::where('release_id', $release->id)->orderBy('date')->get()->map(fn ($o) => $o->date->toDateString())->all(),
        );
    }

    public function test_the_release_form_marks_the_configured_days_too(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
            ->get(route('releases.create'))
            ->assertOk()
            ->assertSee("const weekly = JSON.parse('[5]');", false)
            ->assertSee('const alternate = null;', false);

        config(['planning.alternate_off_day' => '2026-08-01']);
        $this->get(route('releases.create'))->assertSee("const alternate = '2026-08-01';", false);
    }

    #[DataProvider('saturdays')]
    public function test_every_second_saturday_is_off_either_side_of_the_date(string $day, bool $off): void
    {
        config(['planning.alternate_off_day' => '2026-08-01']);

        $this->assertSame($off, WorkingWeek::isWeekend(Carbon::parse($day.' 15:30:00')));
    }

    public static function saturdays(): array
    {
        return [
            'the date itself' => ['2026-08-01', true],
            'two weeks on' => ['2026-08-15', true],
            'this coming one' => ['2026-11-07', true],
            'two weeks before' => ['2026-07-18', true],
            'a worked one after' => ['2026-08-08', false],
            'a worked one before' => ['2026-07-25', false],
            'the last worked one' => ['2026-10-31', false],
            'a Sunday' => ['2026-08-02', false],
            'a Friday still off' => ['2026-08-07', true],
        ];
    }

    public function test_the_alternate_day_can_be_switched_off_and_a_typo_is_harmless(): void
    {
        foreach ([null, '', 'not a date'] as $setting) {
            config(['planning.alternate_off_day' => $setting]);

            $this->assertNull(WorkingWeek::alternateOffDay());
            $this->assertFalse(WorkingWeek::isWeekend(Carbon::parse('2026-08-01')));
        }
    }

    public function test_it_steps_over_a_saturday_off_but_not_a_worked_one(): void
    {
        config(['planning.alternate_off_day' => '2026-08-01']);

        $this->assertSame('2026-11-08', WorkingWeek::next(Carbon::parse('2026-11-05'))->toDateString());
        $this->assertSame('2026-11-05', WorkingWeek::previous(Carbon::parse('2026-11-08'))->toDateString());
        $this->assertSame('2026-10-31', WorkingWeek::next(Carbon::parse('2026-10-29'))->toDateString());
        $this->assertSame('Friday and every second Saturday', WorkingWeek::weekendLabel());
    }

    public function test_mark_weekends_includes_the_saturdays_off(): void
    {
        config(['planning.alternate_off_day' => '2026-08-01']);
        $release = $this->release('2026-11-02', '2026-11-15');

        $this->assertSame(3, app(ReleaseOffDayService::class)->markWeekends($release));

        $this->assertSame(
            ['2026-11-06', '2026-11-07', '2026-11-13'],
            ReleaseOffDay::where('release_id', $release->id)->orderBy('date')->get()->map(fn ($o) => $o->date->toDateString())->all(),
        );
    }

    public function test_it_lists_the_alternate_days_off_in_a_period(): void
    {
        config(['planning.alternate_off_day' => '2026-08-01']);

        $this->assertSame(
            ['2026-11-07', '2026-11-21'],
            WorkingWeek::alternateDaysOffBetween(Carbon::parse('2026-11-01'), Carbon::parse('2026-11-30')),
        );
    }

    public function test_the_digest_skips_a_saturday_off_and_runs_on_a_worked_one(): void
    {
        config(['planning.alternate_off_day' => '2026-08-01']);
        $digest = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains((string) $event->command, 'ai:standup-digests'));

        $this->travelTo('2026-11-07 08:30:00');
        $this->assertFalse($digest->filtersPass($this->app));

        $this->travelTo('2026-11-14 08:30:00');
        $this->assertTrue($digest->filtersPass($this->app));
    }

    public function test_standup_attendance_shows_working_days_only(): void
    {
        $team = Team::create(['name' => 'Core', 'color' => '#0891b2']);
        $today = Carbon::parse('2026-11-08'); // Sunday, a working day

        $days = collect(app(TeamService::class)->standupAttendance($team, $today)['days'])->map(fn ($d) => $d['date']->format('D'));

        $this->assertNotContains('Fri', $days);
        $this->assertContains('Sat', $days);
        $this->assertContains('Sun', $days);
    }

    public function test_the_team_page_says_no_sheet_is_expected_on_the_weekend(): void
    {
        $team = Team::create(['name' => 'Core', 'color' => '#0891b2']);
        $team->members()->attach(User::factory()->create(['role' => User::ROLE_DEVELOPER]));
        $lead = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->travelTo('2026-11-06 09:00:00');
        $this->actingAs($lead)->get(route('teams.show', $team))->assertOk()->assertSee('no sheet is expected today');

        $this->travelTo('2026-11-07 09:00:00');
        $this->actingAs($lead)->get(route('teams.show', $team))->assertOk()->assertDontSee('no sheet is expected today');
    }

    public function test_the_standup_digest_runs_on_working_days_only(): void
    {
        $digest = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains((string) $event->command, 'ai:standup-digests'));

        $this->assertNotNull($digest);

        $this->travelTo('2026-11-06 08:30:00');
        $this->assertFalse($digest->filtersPass($this->app));

        $this->travelTo('2026-11-07 08:30:00');
        $this->assertTrue($digest->filtersPass($this->app));
        $this->assertTrue($digest->isDue($this->app));
    }

    public function test_the_morning_digest_after_the_weekend_covers_the_last_working_day(): void
    {
        $team = Team::create(['name' => 'Core', 'color' => '#0891b2']);
        $member = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $team->members()->attach($member);
        config(['services.anthropic.key' => 'test-key']);
        TasksheetEntry::create(['user_id' => $member->id, 'team_id' => $team->id, 'date' => '2026-11-05', 'yesterday_work' => 'Fixed login', 'today_plan' => 'More']);

        Queue::fake();
        $this->travelTo('2026-11-07 08:30:00');
        $this->artisan('ai:standup-digests')->assertSuccessful();

        $this->assertSame('2026-11-05', AiInsight::sole()->for_date->toDateString());
    }
}
