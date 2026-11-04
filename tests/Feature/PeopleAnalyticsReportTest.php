<?php

namespace Tests\Feature;

use App\Models\AiUsageLog;
use App\Models\Event;
use App\Models\KpiReport;
use App\Models\KpiReportMember;
use App\Models\MeetingNote;
use App\Models\PerformanceCompetency;
use App\Models\PerformanceScore;
use App\Models\Task;
use App\Models\TasksheetEntry;
use App\Models\TaskTimeLog;
use App\Models\Team;
use App\Models\TriageItem;
use App\Models\User;
use App\Services\PeopleAnalyticsService;
use App\Services\TaskDependencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** The admin's per-team and per-person report: who can see it, and that its numbers are right. */
class PeopleAnalyticsReportTest extends TestCase
{
    use RefreshDatabase;

    /** A Wednesday. Tests pin the weekend to Saturday and Sunday. */
    private const NOW = '2026-11-04 10:00:00';

    private Team $core;

    private Team $other;

    private User $admin;

    private User $dana;

    private User $quinn;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(self::NOW);
        $this->core = Team::create(['name' => 'Core', 'color' => '#0891b2']);
        $this->other = Team::create(['name' => 'Other', 'color' => '#db2777']);
        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'name' => 'Ada Admin']);
        $this->dana = User::factory()->create(['role' => User::ROLE_DEVELOPER, 'name' => 'Dana Dev']);
        $this->quinn = User::factory()->create(['role' => User::ROLE_QA, 'name' => 'Quinn QA']);

        $this->join($this->dana, $this->core, '2026-01-01');
        $this->join($this->quinn, $this->core, '2026-01-01');
        $this->join($this->quinn, $this->other, '2026-01-01');
    }

    private function join(User $user, Team $team, string $on, ?string $left = null): void
    {
        DB::table('team_user')->insert([
            'team_id' => $team->id, 'user_id' => $user->id,
            'created_at' => $on, 'updated_at' => $on, 'left_at' => $left,
        ]);
    }

    private function filters(string $from = '2026-10-26', string $to = '2026-11-04', ?Team $team = null): array
    {
        return ['from' => Carbon::parse($from), 'to' => Carbon::parse($to), 'team_id' => $team?->id];
    }

    private function report(array $filters = []): array
    {
        return app(PeopleAnalyticsService::class)->report($filters ?: $this->filters());
    }

    /** A person's record from the report. */
    private function recordFor(User $user, array $filters = []): array
    {
        $row = collect($this->report($filters)['people'])->first(fn ($r) => $r['user']->is($user));
        $this->assertNotNull($row, "{$user->name} should be on the report");

        return $row['record'];
    }

    private function teamRecord(string $name, array $filters = []): array
    {
        return collect($this->report($filters)['teams'])->firstWhere('name', $name)['record'];
    }

    /**
     * A task finished on $finished after being created and started earlier,
     * with its history written at the right moments.
     */
    private function finish(User $user, ?Team $team, string $started, string $finished, array $attrs = []): Task
    {
        $this->travelTo(Carbon::parse($started)->subDay());
        $task = Task::create($attrs + [
            'title' => 'Work', 'status' => 'todo', 'position' => 0,
            'assignee_id' => $user->id, 'team_id' => $team?->id,
        ]);

        $this->travelTo($started);
        $task->update(['status' => 'in_progress']);

        $this->travelTo($finished);
        $task->update(['status' => 'done']);

        $this->travelTo(self::NOW);

        return $task;
    }

    private function open(User $user, ?Team $team, array $attrs = []): Task
    {
        return Task::create($attrs + [
            'title' => 'Open work', 'status' => 'todo', 'position' => 0,
            'assignee_id' => $user->id, 'team_id' => $team?->id,
        ]);
    }

    private function sheet(User $user, Team $team, string $date, array $attrs = []): TasksheetEntry
    {
        $this->travelTo($date.' 17:00:00');
        $entry = TasksheetEntry::create($attrs + [
            'team_id' => $team->id, 'user_id' => $user->id, 'date' => $date,
            'plan' => '<p>Plan</p>', 'result' => '<p>Result</p>', 'comment' => '<p>Note</p>', 'tickets' => '<p>1234</p>',
            'work_points' => 3, 'ticket_count' => 2, 'ticket_points' => 4,
        ]);
        $this->travelTo(self::NOW);

        return $entry;
    }

    // ---- access -------------------------------------------------------------

    private function webRoutes(): array
    {
        return [
            route('reports.people'),
            route('reports.people.export'),
            route('reports.people.pdf'),
            route('reports.people.show', $this->dana),
            route('reports.people.tasks', $this->dana),
            route('reports.people.person-pdf', $this->dana),
        ];
    }

    public function test_only_admins_reach_the_page_and_its_exports(): void
    {
        foreach ([User::ROLE_CTO, User::ROLE_TECH_LEAD, User::ROLE_TEAM_LEAD, User::ROLE_DEVELOPER, User::ROLE_QA, User::ROLE_VIEWER] as $role) {
            $user = User::factory()->create(['role' => $role]);

            foreach ($this->webRoutes() as $url) {
                $this->actingAs($user)->get($url)->assertForbidden();
            }
        }

        foreach ($this->webRoutes() as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
        }
    }

    public function test_only_admins_reach_the_api(): void
    {
        $urls = ['/api/v1/reports/people', '/api/v1/reports/people/export', '/api/v1/reports/people/'.$this->dana->id];

        foreach ([User::ROLE_CTO, User::ROLE_TECH_LEAD, User::ROLE_TEAM_LEAD, User::ROLE_DEVELOPER, User::ROLE_QA, User::ROLE_VIEWER] as $role) {
            Sanctum::actingAs(User::factory()->create(['role' => $role]));
            foreach ($urls as $url) {
                $this->getJson($url)->assertForbidden();
            }
        }

        Sanctum::actingAs($this->admin);
        foreach ($urls as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_guests_are_sent_to_log_in(): void
    {
        $this->get(route('reports.people'))->assertRedirect(route('login'));
    }

    public function test_only_admins_see_it_in_the_menu_and_tabs(): void
    {
        $this->actingAs($this->admin)->get(route('reports.flow'))
            ->assertSee('People analytics')
            ->assertSee(route('reports.people'), false);

        $this->actingAs(User::factory()->create(['role' => User::ROLE_CTO]))->get(route('reports.flow'))
            ->assertOk()
            ->assertDontSee('People analytics');
    }

    // ---- delivery -----------------------------------------------------------

    public function test_delivery_figures_come_from_known_tasks(): void
    {
        // Cycle times of 1, 2, 3 and 4 days: median 2.5, average 2.5.
        $this->finish($this->dana, $this->core, '2026-10-26 09:00:00', '2026-10-27 09:00:00', ['due_date' => '2026-10-28']);
        $this->finish($this->dana, $this->core, '2026-10-26 09:00:00', '2026-10-28 09:00:00', ['due_date' => '2026-10-28']);
        $this->finish($this->dana, $this->core, '2026-10-26 09:00:00', '2026-10-29 09:00:00', ['due_date' => '2026-10-28']);
        $this->finish($this->dana, $this->core, '2026-10-26 09:00:00', '2026-10-30 09:00:00');

        $record = $this->recordFor($this->dana);
        $delivery = $record['delivery'];

        $this->assertSame(4, $delivery['completed']);
        $this->assertSame(3, $delivery['dated']);
        $this->assertSame(2, $delivery['onTimeCount']);
        $this->assertSame(67, $delivery['onTime']['value']);
        $this->assertTrue($delivery['onTime']['enough']);
        $this->assertEquals(2.5, $delivery['cycle']['value']);
        $this->assertSame(4, $delivery['cycle']['sample']);
        $this->assertEquals(2.5, $delivery['cycleAverage']);
        // Created a day before starting: 2, 3, 4 and 5 days.
        $this->assertEquals(3.5, $delivery['lead']['value']);
        // 4 tasks over 8 working days.
        $this->assertEquals(0.5, $delivery['perDay']);
    }

    public function test_work_outside_the_period_reopened_or_archived_does_not_count(): void
    {
        $this->finish($this->dana, $this->core, '2026-10-20 09:00:00', '2026-10-21 09:00:00');

        $reopened = $this->finish($this->dana, $this->core, '2026-10-26 09:00:00', '2026-10-27 09:00:00');
        $reopened->update(['status' => 'in_progress']);

        $archived = $this->finish($this->dana, $this->core, '2026-10-26 09:00:00', '2026-10-27 09:00:00');
        $archived->update(['status' => 'archive']);

        $trashed = $this->finish($this->dana, $this->core, '2026-10-26 09:00:00', '2026-10-27 09:00:00');
        $trashed->delete();

        $record = $this->recordFor($this->dana);

        $this->assertSame(0, $record['delivery']['completed']);
        // The reopened task is open again; the archived one isn't.
        $this->assertSame(1, $record['workload']['open']);
    }

    public function test_rework_counts_finished_tasks_that_went_back_to_recheck(): void
    {
        $this->finish($this->dana, $this->core, '2026-10-26 09:00:00', '2026-10-27 09:00:00');
        $this->finish($this->dana, $this->core, '2026-10-26 09:00:00', '2026-10-27 09:00:00');

        $reworked = $this->finish($this->dana, $this->core, '2026-10-26 09:00:00', '2026-10-27 09:00:00');
        $reworked->update(['status' => 'recheck']);
        $reworked->update(['status' => 'done']);

        $quality = $this->recordFor($this->dana)['quality'];

        $this->assertSame(1, $quality['reworked']);
        $this->assertSame(33, $quality['reworkRate']['value']);
    }

    // ---- too little data ----------------------------------------------------

    public function test_a_rate_with_too_little_data_is_not_quoted(): void
    {
        $this->finish($this->dana, $this->core, '2026-10-26 09:00:00', '2026-10-27 09:00:00', ['due_date' => '2026-10-28']);
        $this->finish($this->dana, $this->core, '2026-10-26 09:00:00', '2026-10-27 09:00:00', ['due_date' => '2026-10-28']);

        $onTime = $this->recordFor($this->dana)['delivery']['onTime'];

        $this->assertSame(100, $onTime['value']);
        $this->assertSame(2, $onTime['sample']);
        $this->assertFalse($onTime['enough']);

        $query = ['from' => '2026-10-26', 'to' => '2026-11-04'];

        $this->actingAs($this->admin)->get(route('reports.people', $query))
            ->assertOk()
            ->assertSee('Too few (2)')
            ->assertDontSee('>100%<', false);

        // The CSV leaves the rate blank and keeps the counts behind it.
        $csv = $this->actingAs($this->admin)->get(route('reports.people.export', $query))->streamedContent();
        $rows = array_map(str_getcsv(...), array_filter(explode("\n", $csv)));
        $headings = $rows[0];
        $dana = collect($rows)->first(fn ($r) => $r[0] === 'Dana Dev');

        $this->assertSame('', $dana[array_search('On time %', $headings, true)]);
        $this->assertSame('2', $dana[array_search('Finished with a due date', $headings, true)]);
        $this->assertSame('2', $dana[array_search('Finished on time', $headings, true)]);
    }

    // ---- teams --------------------------------------------------------------

    public function test_work_counts_for_one_team_and_team_rows_add_up_to_the_total(): void
    {
        // Dana is on one team, so her team-less work is Core's.
        $this->finish($this->dana, $this->core, '2026-10-26 09:00:00', '2026-10-27 09:00:00');
        $this->finish($this->dana, null, '2026-10-26 09:00:00', '2026-10-27 09:00:00');
        // Dana worked on Other without being a member.
        $this->finish($this->dana, $this->other, '2026-10-26 09:00:00', '2026-10-27 09:00:00');
        // Quinn is on two, so her team-less work has no team to go to.
        $this->finish($this->quinn, null, '2026-10-26 09:00:00', '2026-10-27 09:00:00');
        $this->finish($this->quinn, $this->other, '2026-10-26 09:00:00', '2026-10-27 09:00:00');

        $report = $this->report();
        $byTeam = collect($report['teams'])->mapWithKeys(fn ($t) => [$t['name'] => $t['record']['delivery']['completed']])->all();

        $this->assertSame(['Core' => 2, 'Other' => 2, 'No team' => 1], $byTeam);
        $this->assertSame(5, array_sum($byTeam));
        $this->assertSame(5, $report['summary']['delivery']['completed']);

        // One person's work splits across the teams it belongs to.
        $this->assertSame(3, $this->recordFor($this->dana)['delivery']['completed']);
        $this->assertSame(2, $this->recordFor($this->dana, $this->filters(team: $this->core))['delivery']['completed']);
        $this->assertSame(1, $this->recordFor($this->dana, $this->filters(team: $this->other))['delivery']['completed']);
    }

    public function test_a_team_rate_comes_from_summed_parts_not_an_average_of_rates(): void
    {
        // Dana: 3 of 3 on time (100%). Quinn: 0 of 1 (0%). An average of rates would say 50%.
        foreach (range(1, 3) as $i) {
            $this->finish($this->dana, $this->core, '2026-10-26 09:00:00', '2026-10-27 09:00:00', ['due_date' => '2026-10-28']);
        }
        $this->finish($this->quinn, $this->core, '2026-10-26 09:00:00', '2026-10-29 09:00:00', ['due_date' => '2026-10-28']);

        $this->assertSame(75, $this->teamRecord('Core')['delivery']['onTime']['value']);
    }

    public function test_the_team_view_tags_people_who_are_not_plain_members(): void
    {
        $lee = User::factory()->create(['role' => User::ROLE_DEVELOPER, 'name' => 'Lee Left']);
        $this->join($lee, $this->core, '2026-01-01', '2026-10-28 12:00:00');
        $guest = User::factory()->create(['role' => User::ROLE_DEVELOPER, 'name' => 'Guest Dev']);
        $this->finish($guest, $this->core, '2026-10-26 09:00:00', '2026-10-27 09:00:00');

        $rows = collect($this->report($this->filters(team: $this->core))['people'])->keyBy(fn ($r) => $r['user']->name);

        $this->assertNull($rows['Dana Dev']['tag']);
        $this->assertSame('Left', $rows['Lee Left']['tag']);
        $this->assertSame('Not a member', $rows['Guest Dev']['tag']);
    }

    public function test_a_member_with_no_work_still_shows_and_an_outsider_with_none_does_not(): void
    {
        $idle = User::factory()->create(['role' => User::ROLE_VIEWER, 'name' => 'No Team Nina']);

        $names = collect($this->report()['people'])->map(fn ($r) => $r['user']->name);

        $this->assertTrue($names->contains('Dana Dev'));
        $this->assertFalse($names->contains($idle->name));
        $this->assertFalse($names->contains('Ada Admin'));
    }

    public function test_people_on_a_team_count_once_on_its_row_and_departed_people_do_not(): void
    {
        $gone = User::factory()->create(['role' => User::ROLE_DEVELOPER, 'deactivated_at' => '2026-10-27 00:00:00']);
        $this->join($gone, $this->core, '2026-01-01');

        $core = collect($this->report()['teams'])->firstWhere('name', 'Core');

        $this->assertSame(2, $core['people']);
    }

    // ---- workload -----------------------------------------------------------

    public function test_open_work_is_counted_as_of_today(): void
    {
        $this->open($this->dana, $this->core, ['due_date' => '2026-11-01']);
        $this->open($this->dana, $this->core, ['due_date' => '2026-11-04', 'status' => 'in_progress', 'estimate_minutes' => 60]);
        $this->open($this->dana, $this->core, ['status' => 'in_review']);
        $this->open($this->dana, $this->core, ['status' => 'recheck', 'due_date' => '2026-10-01']);

        // A period long past doesn't move what's open today.
        $workload = $this->recordFor($this->dana, $this->filters('2026-06-01', '2026-06-30'))['workload'];

        $this->assertSame(4, $workload['open']);
        $this->assertSame(1, $workload['todo']);
        $this->assertSame(1, $workload['inProgress']);
        $this->assertSame(1, $workload['inReview']);
        $this->assertSame(1, $workload['recheck']);
        $this->assertSame(2, $workload['overdue']);
        $this->assertSame(3, $workload['unestimated']);
    }

    public function test_blocked_time_is_read_from_the_flag_and_its_history(): void
    {
        $service = app(TaskDependencyService::class);

        // Flagged for two whole days, then cleared.
        $this->travelTo('2026-10-27 09:00:00');
        $cleared = $this->open($this->dana, $this->core);
        $service->flag($cleared, 'Waiting on a key');
        $this->travelTo('2026-10-29 09:00:00');
        $service->clearFlag($cleared);

        // Flagged on Monday and still flagged: two days and ten hours so far.
        $this->travelTo('2026-11-02 00:00:00');
        $flagged = $this->open($this->dana, $this->core);
        $service->flag($flagged, 'Still waiting');

        // Waiting on an unfinished task is blocked now, with no time behind it.
        $this->travelTo(self::NOW);
        $waiting = $this->open($this->dana, $this->core);
        $waiting->blockers()->attach($this->open($this->quinn, $this->core));

        $workload = $this->recordFor($this->dana)['workload'];

        $this->assertSame(2, $workload['blockedNow']);
        $this->assertEquals(4.4, $workload['blockedDays']);

        $clipped = $this->recordFor($this->dana, $this->filters('2026-11-03', '2026-11-04'))['workload'];
        $this->assertEquals(1.4, $clipped['blockedDays']);
    }

    // ---- time ---------------------------------------------------------------

    public function test_time_and_estimates_add_up(): void
    {
        $compared = $this->finish($this->dana, $this->core, '2026-10-26 09:00:00', '2026-10-27 09:00:00', ['estimate_minutes' => 120]);
        TaskTimeLog::create(['task_id' => $compared->id, 'user_id' => $this->dana->id, 'minutes' => 90, 'logged_on' => '2026-10-26']);
        // Logged by someone else: the estimate comparison counts it, Dana's logged time doesn't.
        TaskTimeLog::create(['task_id' => $compared->id, 'user_id' => $this->quinn->id, 'minutes' => 60, 'logged_on' => '2026-10-27']);
        // Outside the period: still part of the task's total, but not of the period's time.
        TaskTimeLog::create(['task_id' => $compared->id, 'user_id' => $this->dana->id, 'minutes' => 30, 'logged_on' => '2026-10-20']);

        // Estimated with nothing logged: nothing to compare.
        $this->finish($this->dana, $this->core, '2026-10-26 09:00:00', '2026-10-27 09:00:00', ['estimate_minutes' => 500]);

        $time = $this->recordFor($this->dana)['time'];

        $this->assertSame(90, $time['loggedMinutes']);
        $this->assertSame(1, $time['comparedTasks']);
        $this->assertSame(120, $time['estimatedMinutes']);
        $this->assertSame(180, $time['comparedLogged']);
        $this->assertSame(150, $time['ratio']['value']);
        $this->assertFalse($time['ratio']['enough']);
    }

    // ---- tasksheet ----------------------------------------------------------

    public function test_tasksheet_days_follow_the_working_week_and_leave(): void
    {
        $this->sheet($this->dana, $this->core, '2026-10-26');
        $this->sheet($this->dana, $this->core, '2026-10-27');
        $this->sheet($this->dana, $this->core, '2026-10-28', ['leave_type' => 'sick', 'plan' => null, 'result' => null, 'comment' => null, 'tickets' => null, 'work_points' => null, 'ticket_count' => null, 'ticket_points' => null]);
        // A plan and no result: part filled.
        $this->sheet($this->dana, $this->core, '2026-10-29', ['result' => null]);
        $this->sheet($this->dana, $this->core, '2026-10-30', ['leave_type' => 'half_day']);

        $sheet = $this->recordFor($this->dana)['tasksheet'];

        // Oct 26 to Nov 3 are 7 working days, less 1 on leave. Today isn't owed until filled.
        $this->assertSame(6, $sheet['owed']);
        $this->assertSame(3, $sheet['filled']);
        $this->assertSame(50, $sheet['rate']['value']);
        $this->assertTrue($sheet['rate']['enough']);
        $this->assertSame(1, $sheet['leaveDays']);
        $this->assertSame(1, $sheet['halfDays']);
        $this->assertSame(1, $sheet['partial']);
        $this->assertSame(4, $sheet['planned']);
        $this->assertSame(3, $sheet['planWithResult']);
        $this->assertSame(75, $sheet['planRate']['value']);
        $this->assertFalse($sheet['planRate']['enough']);
        $this->assertSame(12, $sheet['workPoints']);

        // Filling today makes it owed.
        $this->sheet($this->dana, $this->core, '2026-11-04');
        $sheet = $this->recordFor($this->dana)['tasksheet'];
        $this->assertSame(7, $sheet['owed']);
        $this->assertSame(4, $sheet['filled']);

        // 8 working days, less 1 on leave, less half a day: 6.5 days at 8 hours.
        $this->assertSame(3120, $this->recordFor($this->dana)['time']['availableMinutes']);
    }

    public function test_someone_on_two_teams_owes_each_sheet_but_a_day_counts_once_overall(): void
    {
        foreach (['2026-10-26', '2026-10-27', '2026-10-28', '2026-10-29', '2026-10-30'] as $day) {
            $this->sheet($this->quinn, $this->core, $day);
        }
        $this->sheet($this->quinn, $this->other, '2026-10-26');

        $period = fn (?Team $team) => $this->filters('2026-10-26', '2026-10-30', $team);

        $all = $this->recordFor($this->quinn, $period(null))['tasksheet'];
        $this->assertSame(5, $all['owed']);
        $this->assertSame(5, $all['filled']);

        $this->assertSame(5, $this->recordFor($this->quinn, $period($this->core))['tasksheet']['filled']);

        $other = $this->recordFor($this->quinn, $period($this->other))['tasksheet'];
        $this->assertSame(5, $other['owed']);
        $this->assertSame(1, $other['filled']);
    }

    public function test_tasksheet_days_stop_when_someone_leaves_and_start_when_they_join(): void
    {
        $late = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $this->join($late, $this->core, '2026-10-29');
        $lee = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $this->join($lee, $this->core, '2026-01-01', '2026-10-27 12:00:00');

        $filters = $this->filters('2026-10-26', '2026-10-30');

        $this->assertSame(2, $this->recordFor($late, $filters)['tasksheet']['owed']);
        $this->assertSame(2, $this->recordFor($lee, $filters)['tasksheet']['owed']);
    }

    public function test_leads_and_viewers_owe_no_tasksheet_days(): void
    {
        $lead = User::factory()->create(['role' => User::ROLE_TEAM_LEAD]);
        $this->join($lead, $this->core, '2026-01-01');

        $record = $this->recordFor($lead);

        $this->assertSame(0, $record['tasksheet']['owed']);
        // They still have time available.
        $this->assertGreaterThan(0, $record['time']['availableMinutes']);
    }

    // ---- requests, meetings, comments, AI -----------------------------------

    public function test_requests_are_counted_for_who_raised_and_who_decided(): void
    {
        $this->travelTo('2026-10-27 09:00:00');
        $accepted = TriageItem::create(['title' => 'A', 'reporter_id' => $this->dana->id, 'team_id' => $this->core->id]);
        $declined = TriageItem::create(['title' => 'B', 'reporter_id' => $this->dana->id, 'team_id' => $this->core->id]);
        TriageItem::create(['title' => 'C', 'reporter_id' => $this->dana->id, 'team_id' => $this->core->id]);

        $this->travelTo('2026-10-28 09:00:00');
        $accepted->forceFill(['status' => TriageItem::ACCEPTED, 'decided_by' => $this->quinn->id, 'decided_at' => now()])->save();
        $declined->forceFill(['status' => TriageItem::DECLINED, 'decided_by' => $this->quinn->id, 'decided_at' => now()])->save();

        // Raised before the period, decided inside it.
        $this->travelTo('2026-10-01 09:00:00');
        $earlier = TriageItem::create(['title' => 'D', 'reporter_id' => $this->dana->id, 'team_id' => $this->core->id]);
        $this->travelTo('2026-10-28 09:00:00');
        $earlier->forceFill(['status' => TriageItem::DECLINED, 'decided_by' => $this->quinn->id, 'decided_at' => now()])->save();
        $this->travelTo(self::NOW);

        $dana = $this->recordFor($this->dana)['requests'];
        $this->assertSame(3, $dana['raised']);
        $this->assertSame(1, $dana['accepted']);
        $this->assertSame(1, $dana['declined']);
        $this->assertSame(1, $dana['pending']);

        $this->assertSame(3, $this->recordFor($this->quinn)['requests']['decided']);
    }

    public function test_meetings_leave_out_private_events_and_add_up_their_time(): void
    {
        $meeting = Event::create(['title' => 'Sync', 'type' => 'meeting', 'starts_at' => '2026-10-27 10:00:00', 'ends_at' => '2026-10-27 11:30:00', 'created_by' => $this->admin->id]);
        $meeting->attendees()->attach($this->dana);
        $private = Event::create(['title' => 'One to one', 'type' => 'meeting', 'starts_at' => '2026-10-27 12:00:00', 'ends_at' => '2026-10-27 13:00:00', 'created_by' => $this->admin->id, 'visibility' => Event::VISIBILITY_PRIVATE]);
        $private->attendees()->attach($this->dana);
        $review = Event::create(['title' => 'Review', 'type' => 'review', 'starts_at' => '2026-10-27 15:00:00', 'ends_at' => '2026-10-27 16:00:00', 'created_by' => $this->admin->id]);
        $review->attendees()->attach($this->dana);

        $note = MeetingNote::create(['title' => 'Notes', 'body' => '<p>Notes</p>', 'meeting_date' => '2026-10-27', 'created_by' => $this->dana->id]);
        $note->attendees()->attach($this->quinn);

        $dana = $this->recordFor($this->dana)['collaboration'];
        $this->assertSame(1, $dana['meetings']);
        $this->assertSame(90, $dana['meetingMinutes']);
        $this->assertSame(1, $dana['notesWritten']);
        $this->assertSame(1, $this->recordFor($this->quinn)['collaboration']['notesAttended']);
    }

    public function test_comments_and_successful_ai_uses_are_counted(): void
    {
        $task = $this->open($this->dana, $this->core);
        $this->travelTo('2026-10-27 09:00:00');
        $task->comments()->create(['user_id' => $this->dana->id, 'body' => 'Hi']);
        AiUsageLog::create(['feature' => 'ask', 'user_id' => $this->dana->id, 'model' => 'm', 'status' => 'ok']);
        AiUsageLog::create(['feature' => 'ask', 'user_id' => $this->dana->id, 'model' => 'm', 'status' => 'error']);
        $this->travelTo(self::NOW);

        $collaboration = $this->recordFor($this->dana)['collaboration'];

        $this->assertSame(1, $collaboration['comments']);
        $this->assertSame(1, $collaboration['aiUses']);
    }

    // ---- ratings and KPI ----------------------------------------------------

    private function competency(string $key, int $weight, string $category = 'delivery'): PerformanceCompetency
    {
        return PerformanceCompetency::create([
            'key' => $key, 'name' => ucfirst($key), 'category' => $category, 'role_scope' => 'both',
            'cadence' => 'weekly', 'weight' => $weight, 'active' => true,
        ]);
    }

    private function rate(User $user, Team $team, PerformanceCompetency $competency, int $score, string $note = ''): void
    {
        PerformanceScore::create([
            'team_id' => $team->id, 'user_id' => $user->id, 'evaluator_id' => $this->admin->id,
            'competency_id' => $competency->id, 'period_type' => 'weekly',
            'period_start' => '2026-10-26', 'period_end' => '2026-11-01', 'score' => $score, 'note' => $note,
        ]);
    }

    public function test_a_rating_is_weighted_and_needs_half_the_competencies(): void
    {
        PerformanceCompetency::query()->update(['active' => false]);
        $a = $this->competency('a', 3);
        $b = $this->competency('b', 1);
        $this->competency('c', 1);
        $this->competency('d', 1);

        // 2 of 4 rated: (5×3 + 1×1) / 4.
        $this->rate($this->dana, $this->core, $a, 5);
        $this->rate($this->dana, $this->core, $b, 1);
        // 1 of 4 rated: too few.
        $this->rate($this->quinn, $this->core, $a, 2);

        $dana = $this->recordFor($this->dana)['ratings'];
        $this->assertEquals(4.0, $dana['overall']['value']);
        $this->assertTrue($dana['overall']['enough']);
        $this->assertSame(2, $dana['rated']);
        $this->assertSame(4, $dana['applicable']);

        $this->assertFalse($this->recordFor($this->quinn)['ratings']['overall']['enough']);

        // A team's rating averages the members rated enough to quote.
        $this->assertEquals(4.0, $this->teamRecord('Core')['ratings']['overall']['value']);
    }

    public function test_the_latest_kpi_review_overlapping_the_period_is_used(): void
    {
        $days = fn (int $planned, int $done, int $count) => array_fill(0, $count, ['planned' => $planned, 'added' => 0, 'completed' => $done]);

        foreach ([
            ['2026-09-01', '2026-09-30', $days(2, 1, 10)],
            ['2026-10-01', '2026-10-31', $days(2, 2, 6)],
        ] as [$from, $to, $analysis]) {
            $report = KpiReport::create(['requested_by' => $this->admin->id, 'scope' => 'all', 'period_start' => $from, 'period_end' => $to, 'format' => 'pdf', 'status' => KpiReport::STATUS_COMPLETED]);
            KpiReportMember::create(['kpi_report_id' => $report->id, 'user_id' => $this->dana->id, 'status' => KpiReportMember::STATUS_COMPLETED, 'analysis' => ['days' => $analysis, 'overall' => ['suggested_rating' => ['score' => 4]]]]);
        }

        $kpi = $this->recordFor($this->dana)['kpi'];
        $this->assertSame(12, $kpi['planned']);
        $this->assertSame(12, $kpi['completed']);
        $this->assertEquals(100.0, $kpi['percent']['value']);
        $this->assertTrue($kpi['percent']['enough']);
        $this->assertSame('Excellent', $kpi['status']);
        $this->assertSame(4, $kpi['suggestedRating']);

        // No review overlaps this period.
        $this->assertNull($this->recordFor($this->dana, $this->filters('2026-06-01', '2026-06-30'))['kpi']['planned']);
    }

    // ---- filters ------------------------------------------------------------

    public function test_it_opens_on_the_last_30_days_and_all_teams(): void
    {
        $response = $this->actingAs($this->admin)->get(route('reports.people'))->assertOk();
        $report = $response->viewData('report');

        $this->assertSame('2026-10-06', $report['from']->toDateString());
        $this->assertSame('2026-11-04', $report['to']->toDateString());
        $this->assertNull($report['teamId']);
    }

    public function test_the_period_must_be_sensible(): void
    {
        $this->actingAs($this->admin);

        $this->getJson(route('reports.people', ['from' => '2026-11-04', 'to' => '2026-11-01']))->assertUnprocessable();
        $this->getJson(route('reports.people', ['from' => '2026-11-05']))->assertUnprocessable();
        $this->getJson(route('reports.people', ['from' => '2024-01-01', 'to' => '2026-01-01']))->assertUnprocessable();
        $this->getJson(route('reports.people', ['team_id' => 99999]))->assertUnprocessable();

        // Days to come would read as days missed, so the end stops at today.
        $this->assertSame(
            '2026-11-04',
            $this->get(route('reports.people', ['from' => '2026-11-01', 'to' => '2026-12-01']))->viewData('report')['to']->toDateString(),
        );
    }

    public function test_changes_compare_with_the_same_length_of_time_just_before(): void
    {
        foreach (range(1, 2) as $i) {
            $this->finish($this->dana, $this->core, '2026-10-20 09:00:00', '2026-10-22 09:00:00');
        }
        $this->finish($this->dana, $this->core, '2026-10-26 09:00:00', '2026-10-27 09:00:00');

        $report = $this->report($this->filters('2026-10-26', '2026-11-01'));

        $this->assertSame('2026-10-19', $report['previous']['from']->toDateString());
        $this->assertSame('2026-10-25', $report['previous']['to']->toDateString());
        $this->assertSame(1, $report['compare']['completed']['current']);
        $this->assertSame(2, $report['compare']['completed']['previous']);
        $this->assertSame(-1, $report['compare']['completed']['change']);
    }

    public function test_a_long_period_goes_by_the_month(): void
    {
        $this->finish($this->dana, $this->core, '2026-10-26 09:00:00', '2026-10-27 09:00:00');

        $week = $this->report($this->filters('2026-10-26', '2026-11-04'))['trend'];
        $this->assertSame('week', $week['unit']);
        $this->assertCount(2, $week['buckets']);
        $this->assertSame(1, $week['buckets'][0]['completed']);

        $month = $this->report($this->filters('2026-01-01', '2026-11-04'))['trend'];
        $this->assertSame('month', $month['unit']);
        $this->assertCount(11, $month['buckets']);
        $this->assertSame(1, $month['buckets'][9]['completed']);
    }

    // ---- cost ---------------------------------------------------------------

    public function test_the_query_count_does_not_grow_with_the_head_count(): void
    {
        $count = function () {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->report();
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $queries;
        };

        $this->finish($this->dana, $this->core, '2026-10-26 09:00:00', '2026-10-27 09:00:00');
        $before = $count();

        foreach (range(1, 15) as $i) {
            $user = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
            $this->join($user, $this->core, '2026-01-01');
            $this->finish($user, $this->core, '2026-10-26 09:00:00', '2026-10-27 09:00:00');
            $this->sheet($user, $this->core, '2026-10-26');
        }

        $this->assertSame($before, $count());
    }

    // ---- exports ------------------------------------------------------------

    public function test_the_csv_neutralises_formulas_and_has_each_person(): void
    {
        $this->dana->update(['name' => '=HYPERLINK("x")']);
        $this->finish($this->dana, $this->core, '2026-10-26 09:00:00', '2026-10-27 09:00:00');

        $response = $this->actingAs($this->admin)->get(route('reports.people.export', ['from' => '2026-10-26', 'to' => '2026-11-04']))->assertOk();
        $csv = $response->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString('people_analytics_2026-10-26_2026-11-04.csv', $response->headers->get('Content-Disposition'));
    }

    public function test_the_team_csv_has_a_row_per_team_with_the_same_figures(): void
    {
        $this->finish($this->dana, $this->core, '2026-10-26 09:00:00', '2026-10-27 09:00:00');

        $csv = $this->actingAs($this->admin)
            ->get(route('reports.people.export', ['from' => '2026-10-26', 'to' => '2026-11-04', 'level' => 'teams']))
            ->streamedContent();
        $rows = array_map(str_getcsv(...), array_filter(explode("\n", $csv)));
        $core = collect($rows)->first(fn ($r) => $r[0] === 'Core');

        $this->assertSame('Team', $rows[0][0]);
        $this->assertSame('1', $core[array_search('Finished', $rows[0], true)]);
    }

    public function test_a_persons_tasks_csv_lists_finished_and_open_work(): void
    {
        $this->finish($this->dana, $this->core, '2026-10-26 09:00:00', '2026-10-27 09:00:00', ['title' => 'Shipped thing', 'due_date' => '2026-10-28']);
        $this->open($this->dana, $this->core, ['title' => 'Next thing']);

        $csv = $this->actingAs($this->admin)
            ->get(route('reports.people.tasks', ['user' => $this->dana, 'from' => '2026-10-26', 'to' => '2026-11-04']))
            ->streamedContent();

        $this->assertStringContainsString('Finished,', $csv);
        $this->assertStringContainsString('Shipped thing', $csv);
        $this->assertStringContainsString('Open,', $csv);
        $this->assertStringContainsString('Next thing', $csv);
    }

    public function test_pdfs_download(): void
    {
        $this->finish($this->dana, $this->core, '2026-10-26 09:00:00', '2026-10-27 09:00:00');

        $this->actingAs($this->admin)->get(route('reports.people.pdf', ['from' => '2026-10-26', 'to' => '2026-11-04']))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($this->admin)->get(route('reports.people.person-pdf', ['user' => $this->dana, 'from' => '2026-10-26', 'to' => '2026-11-04']))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    // ---- pages --------------------------------------------------------------

    public function test_the_report_page_links_to_each_person_and_team(): void
    {
        $this->finish($this->dana, $this->core, '2026-10-26 09:00:00', '2026-10-27 09:00:00');

        $this->actingAs($this->admin)->get(route('reports.people', ['from' => '2026-10-26', 'to' => '2026-11-04']))
            ->assertOk()
            ->assertSee('Dana Dev')
            ->assertSee('Core')
            ->assertSee(route('reports.people.show', ['user' => $this->dana, 'from' => '2026-10-26', 'to' => '2026-11-04']))
            ->assertSee(route('reports.people', ['team_id' => $this->core->id, 'from' => '2026-10-26', 'to' => '2026-11-04']));
    }

    public function test_a_persons_page_shows_their_figures_and_tasks(): void
    {
        $this->finish($this->dana, $this->core, '2026-10-26 09:00:00', '2026-10-27 09:00:00', ['title' => 'Shipped thing']);
        $this->finish($this->dana, $this->other, '2026-10-26 09:00:00', '2026-10-27 09:00:00', ['title' => 'Helped out']);
        $this->open($this->dana, $this->core, ['title' => 'Next thing', 'due_date' => '2026-11-01']);

        $query = ['user' => $this->dana, 'from' => '2026-10-26', 'to' => '2026-11-04'];

        $this->actingAs($this->admin)->get(route('reports.people.show', $query))
            ->assertOk()
            ->assertSee('Shipped thing')
            ->assertSee('Helped out')
            ->assertSee('Next thing')
            ->assertSee('overdue')
            ->assertSee('Split by team');

        // On one team, only that team's work.
        $this->actingAs($this->admin)->get(route('reports.people.show', $query + ['team_id' => $this->core->id]))
            ->assertOk()
            ->assertSee('Shipped thing')
            ->assertDontSee('Helped out')
            ->assertDontSee('Split by team');
    }

    public function test_a_deleted_persons_history_stays_reachable(): void
    {
        $this->finish($this->dana, $this->core, '2026-10-26 09:00:00', '2026-10-27 09:00:00');
        $this->dana->delete();

        $this->actingAs($this->admin)->get(route('reports.people.show', ['user' => $this->dana->id, 'from' => '2026-10-26', 'to' => '2026-11-04']))
            ->assertOk()
            ->assertSee('Deleted user');
    }

    public function test_lead_only_text_is_never_read(): void
    {
        $this->sheet($this->dana, $this->core, '2026-10-27', ['feedback' => '<p>Private lead feedback</p>']);
        $competency = $this->competency('a', 1);
        $this->rate($this->dana, $this->core, $competency, 3, 'Private rating note');

        $query = ['user' => $this->dana, 'from' => '2026-10-26', 'to' => '2026-11-04'];
        $page = $this->actingAs($this->admin)->get(route('reports.people.show', $query));

        $page->assertDontSee('Private lead feedback')->assertDontSee('Private rating note');

        Sanctum::actingAs($this->admin);
        $json = $this->getJson('/api/v1/reports/people/'.$this->dana->id.'?from=2026-10-26&to=2026-11-04')->getContent();
        $this->assertStringNotContainsString('Private', $json);
    }

    // ---- API ----------------------------------------------------------------

    public function test_the_api_and_the_page_agree(): void
    {
        $this->finish($this->dana, $this->core, '2026-10-26 09:00:00', '2026-10-27 09:00:00', ['due_date' => '2026-10-28']);
        $this->open($this->dana, $this->core);
        $this->sheet($this->dana, $this->core, '2026-10-27');

        $query = 'from=2026-10-26&to=2026-11-04';
        $web = $this->actingAs($this->admin)->get(route('reports.people').'?'.$query)->viewData('report');

        Sanctum::actingAs($this->admin);
        $api = $this->getJson('/api/v1/reports/people?'.$query)->assertOk()->json('data');

        $this->assertSame('2026-10-26', $api['from']);
        $this->assertEquals($web['summary']['delivery']['completed'], $api['summary']['delivery']['completed']);
        $this->assertEquals($web['summary']['workload']['open'], $api['summary']['workload']['open']);
        $this->assertSame(array_map(fn ($t) => $t['name'], $web['teams']), array_column($api['teams'], 'name'));

        $webDana = collect($web['people'])->first(fn ($r) => $r['user']->is($this->dana));
        $apiDana = collect($api['people'])->firstWhere('id', $this->dana->id);

        $this->assertSame(['Core'], $apiDana['teams']);
        $this->assertEquals($webDana['record']['tasksheet']['owed'], $apiDana['record']['tasksheet']['owed']);
        $this->assertEquals(['value', 'sample', 'enough'], array_keys($apiDana['record']['delivery']['onTime']));

        $person = $this->getJson('/api/v1/reports/people/'.$this->dana->id.'?'.$query)->assertOk()->json('data');
        $this->assertSame($this->dana->id, $person['id']);
        $this->assertCount(1, $person['finished']);
        $this->assertCount(1, $person['open']);
        $this->assertEquals($webDana['record']['delivery']['completed'], $person['record']['delivery']['completed']);
    }
}
