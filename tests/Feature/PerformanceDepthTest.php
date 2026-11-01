<?php

namespace Tests\Feature;

use App\Models\PerformanceCompetency;
use App\Models\PerformanceScore;
use App\Models\Project;
use App\Models\Release;
use App\Models\Task;
use App\Models\TasksheetEntry;
use App\Models\Team;
use App\Models\User;
use App\Services\PerformanceAnalytics;
use App\Support\PerformancePeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The deeper performance analysis: competency trends, rating spread, change
 * against last week, strengths/growth areas, notes, and the objective record.
 * "Today" is Wednesday 2026-11-11; the selected week is Nov 9 – 15.
 */
class PerformanceDepthTest extends TestCase
{
    use RefreshDatabase;

    private const TODAY = '2026-11-11 10:00:00';

    private Team $team;

    private User $lead;

    private User $dev;

    private User $qa;

    private PerformanceCompetency $code;

    private PerformanceCompetency $comms;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-01 09:00:00'));
        $this->lead = User::factory()->create(['role' => User::ROLE_TEAM_LEAD]);
        $this->team = Team::create(['name' => 'Core', 'color' => '#0891b2', 'team_lead_id' => $this->lead->id]);
        $this->dev = User::factory()->create(['role' => User::ROLE_DEVELOPER, 'name' => 'Dana Dev']);
        $this->qa = User::factory()->create(['role' => User::ROLE_QA, 'name' => 'Quin QA']);
        $this->team->members()->attach([$this->dev->id, $this->qa->id]);

        $comp = fn (string $name, string $category) => PerformanceCompetency::create([
            'key' => str($name)->slug(), 'name' => $name, 'category' => $category,
            'role_scope' => PerformanceCompetency::SCOPE_BOTH, 'cadence' => PerformanceCompetency::CADENCE_WEEKLY,
            'weight' => 1, 'active' => true, 'position' => 0,
        ]);
        $this->code = $comp('Code Quality', PerformanceCompetency::CATEGORY_TECHNICAL);
        $this->comms = $comp('Communication', array_key_last(PerformanceCompetency::CATEGORIES));

        $this->travelTo(Carbon::parse(self::TODAY));
    }

    private function score(User $member, PerformanceCompetency $c, int $value, string $date, ?string $note = null): void
    {
        $period = PerformancePeriod::normalize($c->cadence, Carbon::parse($date));
        PerformanceScore::create([
            'team_id' => $this->team->id, 'user_id' => $member->id, 'evaluator_id' => $this->lead->id,
            'competency_id' => $c->id, 'period_type' => $period['type'],
            'period_start' => $period['start']->toDateString(), 'period_end' => $period['end']->toDateString(),
            'score' => $value, 'note' => $note,
        ]);
    }

    private function overview(): array
    {
        return app(PerformanceAnalytics::class)->teamOverview($this->team, today());
    }

    // -- access -------------------------------------------------------------

    public function test_access_rules_are_unchanged(): void
    {
        foreach ([User::ROLE_DEVELOPER, User::ROLE_QA, User::ROLE_VIEWER] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get(route('performance.index'))->assertForbidden();
            $this->actingAs($user)->get(route('performance.members.show', $this->dev))->assertForbidden();
            Sanctum::actingAs($user);
            $this->getJson('/api/v1/performance/overview')->assertForbidden();
            $this->app['auth']->forgetGuards();
        }

        // A team lead of another team cannot open this team or its member.
        $otherLead = User::factory()->create(['role' => User::ROLE_TEAM_LEAD]);
        Team::create(['name' => 'Other', 'color' => '#000000', 'team_lead_id' => $otherLead->id]);
        $this->actingAs($otherLead)->get(route('performance.members.show', ['user' => $this->dev, 'team' => $this->team->id]))->assertForbidden();
        $this->actingAs($otherLead)->get(route('performance.index', ['team' => $this->team->id]))
            ->assertOk()->assertDontSee('Dana Dev');

        foreach ([$this->lead, User::factory()->create(['role' => User::ROLE_CTO])] as $viewer) {
            $this->actingAs($viewer)->get(route('performance.index', ['team' => $this->team->id]))
                ->assertOk()->assertSee('Competency trends')->assertSee('Objective record')->assertSee('Rating spread');
        }
    }

    // -- team ---------------------------------------------------------------

    public function test_competency_trends_spread_and_change_against_last_week(): void
    {
        $this->score($this->dev, $this->code, 3, '2026-11-03');
        $this->score($this->dev, $this->code, 5, '2026-11-10');
        $this->score($this->qa, $this->code, 3, '2026-11-10');
        $this->score($this->dev, $this->comms, 2, '2026-11-10');

        $o = $this->overview();

        $code = collect($o['competencyTrends'])->firstWhere('competency.id', $this->code->id);
        $this->assertSame(4.0, $code['current']);     // (5 + 3) / 2 this week
        $this->assertSame(3.0, $code['previous']);
        $this->assertSame(1.0, $code['change']);
        $this->assertCount(PerformanceAnalytics::TREND_WEEKS, $code['points']);

        $this->assertSame(3, $o['distribution']['total']);
        $this->assertSame([1 => 0, 2 => 1, 3 => 1, 4 => 0, 5 => 1], $o['distribution']['counts']);
        $this->assertSame(33, $o['distribution']['pct'][2]);

        // Dev 3.5 (5 & 2), QA 3.0 → team 3.25; last week only the dev, at 3.0.
        $this->assertSame(['current' => 3.25, 'previous' => 3.0, 'change' => 0.3], $o['averageChange']);
        $rows = collect($o['rows'])->keyBy(fn ($r) => $r['member']->id);
        $this->assertSame(0.25, $rows[$this->dev->id]['vsTeam']);
        $this->assertSame(-0.25, $rows[$this->qa->id]['vsTeam']);
    }

    public function test_objective_record_counts_own_team_work_and_never_archived(): void
    {
        $this->travelTo(Carbon::parse('2026-10-01 09:00:00'));
        $project = Project::create(['name' => 'P', 'color' => '#4f46e5']);
        $other = Team::create(['name' => 'Elsewhere', 'color' => '#000000']);
        $mine = Release::create(['project_id' => $project->id, 'team_id' => $this->team->id, 'name' => 'Mine',
            'year' => 2026, 'quarter' => 4, 'start_date' => '2026-10-01', 'end_date' => '2026-12-31']);
        $theirs = Release::create(['project_id' => $project->id, 'team_id' => $other->id, 'name' => 'Theirs',
            'year' => 2026, 'quarter' => 4, 'start_date' => '2026-10-01', 'end_date' => '2026-12-31']);

        $move = function (Task $t, string $status, string $at) {
            $this->travelTo(Carbon::parse($at));
            $t->update(['status' => $status]);
        };
        $task = fn (Release $r, array $o = []) => Task::create($o + ['title' => 'T', 'release_id' => $r->id, 'assignee_id' => $this->dev->id]);

        // Finished in the window: one clean (2 days in flight, on time), one reworked.
        $this->travelTo(Carbon::parse('2026-11-02 09:00:00'));
        $clean = $task($mine, ['due_date' => '2026-11-06']);
        $move($clean, 'in_progress', '2026-11-03 09:00:00');
        $move($clean, 'done', '2026-11-05 09:00:00');
        $this->travelTo(Carbon::parse('2026-11-02 09:00:00'));
        $reworked = $task($mine);
        $move($reworked, 'recheck', '2026-11-04 09:00:00');
        $move($reworked, 'done', '2026-11-06 09:00:00');
        // Not counted: archived after finishing, and another team's work.
        $this->travelTo(Carbon::parse('2026-11-02 09:00:00'));
        $filed = $task($mine, ['due_date' => '2026-11-01']);
        $move($filed, 'done', '2026-11-03 09:00:00');
        $move($filed, 'archive', '2026-11-04 09:00:00');
        $this->travelTo(Carbon::parse('2026-11-02 09:00:00'));
        $foreign = $task($theirs, ['due_date' => '2026-11-01']);
        $move($foreign, 'done', '2026-11-03 09:00:00');
        // Overdue now: one open on this team, one archived (ignored).
        $this->travelTo(Carbon::parse('2026-11-02 09:00:00'));
        $task($mine, ['due_date' => '2026-11-09']);
        $task($mine, ['due_date' => '2026-11-09', 'status' => 'archive']);

        // Tasksheet: two complete days, one sick day, standup 1 of 2.
        $full = ['plan' => 'P', 'result' => 'R', 'comment' => 'C', 'tickets' => 'T', 'work_points' => 1, 'ticket_count' => 1, 'ticket_points' => 1];
        TasksheetEntry::create(['team_id' => $this->team->id, 'user_id' => $this->dev->id, 'date' => '2026-11-09'] + $full)->forceFill(['standup_attended' => true])->save();
        TasksheetEntry::create(['team_id' => $this->team->id, 'user_id' => $this->dev->id, 'date' => '2026-11-10'] + $full)->forceFill(['standup_attended' => false])->save();
        TasksheetEntry::create(['team_id' => $this->team->id, 'user_id' => $this->dev->id, 'date' => '2026-11-06', 'leave_type' => 'sick']);

        $this->travelTo(Carbon::parse(self::TODAY));
        $o = $this->overview();
        $r = $o['records'][$this->dev->id];

        $this->assertSame('2026-10-19', $o['recordWindow']['start']->toDateString()); // 4 weeks back from Nov 9
        $this->assertSame(2, $r['completed']);
        $this->assertSame(100, $r['onTimeRate']);
        $this->assertSame(2.0, $r['cycleDays']);
        $this->assertSame(50, $r['reworkRate']);
        $this->assertSame(1, $r['overdue']);
        // Weekdays Oct 19 – Nov 10 = 17, less the sick day = 16; today isn't owed yet.
        $this->assertSame([2, 16, 13], [$r['daysLogged'], $r['daysExpected'], $r['tasksheetRate']]);
        $this->assertSame(50, $r['standupRate']);
        $this->assertSame(2, $o['recordTotals']['completed']);
        $this->assertSame(1, $o['recordTotals']['overdue']);
    }

    public function test_tasksheet_days_are_owed_only_from_joining_the_team(): void
    {
        $this->travelTo(Carbon::parse('2026-11-09 09:00:00'));
        $newcomer = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $this->team->members()->attach($newcomer); // joined Monday
        $full = ['plan' => 'P', 'result' => 'R', 'comment' => 'C', 'tickets' => 'T', 'work_points' => 1, 'ticket_count' => 1, 'ticket_points' => 1];
        TasksheetEntry::create(['team_id' => $this->team->id, 'user_id' => $newcomer->id, 'date' => '2026-11-09'] + $full);

        $this->travelTo(Carbon::parse(self::TODAY));
        $r = $this->overview()['records'][$newcomer->id];

        // Mon + Tue owed (today not yet), one logged.
        $this->assertSame([1, 2, 50], [$r['daysLogged'], $r['daysExpected'], $r['tasksheetRate']]);
    }

    public function test_a_team_with_no_history_renders_empty_states(): void
    {
        $o = $this->overview();

        $this->assertNull($o['averageChange']['current']);
        $this->assertSame(0, $o['distribution']['total']);
        // Owed days with nothing logged are a real 0%, unlike rates with no denominator.
        $this->assertSame(0, $o['records'][$this->dev->id]['tasksheetRate']);
        $this->assertNull($o['records'][$this->dev->id]['onTimeRate']);
        $this->assertNull($o['recordTotals']['cycleDays']);

        $this->actingAs($this->lead)->get(route('performance.index', ['team' => $this->team->id]))
            ->assertOk()->assertSee('No ratings this week yet.');
        $this->actingAs($this->lead)->get(route('performance.members.show', ['user' => $this->dev, 'team' => $this->team->id]))
            ->assertOk()
            ->assertSee('None stand out yet.')
            ->assertSee('No notes yet.')
            ->assertSee('Competency history');
    }

    // -- member ---------------------------------------------------------------

    public function test_member_strengths_growth_notes_and_change(): void
    {
        $this->score($this->dev, $this->code, 4, '2026-11-03');
        $this->score($this->dev, $this->code, 5, '2026-11-10', 'Clean refactor of the payment adapter.');
        $this->score($this->dev, $this->comms, 3, '2026-11-03');
        $this->score($this->dev, $this->comms, 2, '2026-11-10', 'Missed two standup updates.');

        $card = app(PerformanceAnalytics::class)->memberScorecard($this->dev, $this->team, today());

        $this->assertSame(['Code Quality'], collect($card['strengths'])->pluck('competency.name')->all());
        $this->assertSame(4.5, $card['strengths'][0]['average']);
        $this->assertSame(1.0, $card['strengths'][0]['direction']);
        $this->assertSame(['Communication'], collect($card['growthAreas'])->pluck('competency.name')->all());
        $this->assertSame(['current' => 3.5, 'previous' => 3.5, 'change' => 0.0], $card['overallChange']);
        $this->assertCount(2, $card['notes']);
        $this->assertArrayHasKey('tasksheetRate', $card['record']);
        $this->assertArrayHasKey('change', $card['record']['completed']);

        $this->actingAs($this->lead)->get(route('performance.members.show', ['user' => $this->dev, 'team' => $this->team->id]))
            ->assertOk()
            ->assertSee('Strengths')
            ->assertSee('Growth areas')
            ->assertSee('Clean refactor of the payment adapter.')
            ->assertSee('Objective record · last 4 weeks');
    }

    public function test_query_count_does_not_grow_with_members(): void
    {
        $count = function () {
            DB::flushQueryLog();
            DB::enableQueryLog();
            app(PerformanceAnalytics::class)->teamOverview($this->team->fresh(), today());

            return count(DB::getQueryLog());
        };

        $few = $count();
        foreach (range(1, 5) as $i) {
            $this->team->members()->attach(User::factory()->create(['role' => User::ROLE_DEVELOPER])->id);
        }
        $this->assertSame($few, $count());
    }

    public function test_api_overview_and_member_carry_the_same_analysis(): void
    {
        $this->score($this->dev, $this->code, 4, '2026-11-10', 'Solid.');

        $web = $this->actingAs($this->lead)->get(route('performance.index', ['team' => $this->team->id]))->viewData('overview');
        $webCard = $this->actingAs($this->lead)->get(route('performance.members.show', ['user' => $this->dev, 'team' => $this->team->id]))->viewData('card');

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($this->lead);
        $api = $this->getJson('/api/v1/performance/overview?team_id='.$this->team->id)->assertOk()->json('data.overview');
        $apiCard = $this->getJson("/api/v1/performance/members/{$this->dev->id}?team_id={$this->team->id}")->assertOk()->json('data.scorecard');

        $this->assertEquals($web['averageChange'], $api['averageChange']);
        $this->assertEquals($web['distribution']['counts'], $api['distribution']['counts']);
        $this->assertEquals($web['records'][$this->dev->id], $api['records'][$this->dev->id]);
        $this->assertEquals(
            collect($web['competencyTrends'])->map(fn ($c) => [$c['competency']->id, $c['current']])->all(),
            collect($api['competencyTrends'])->map(fn ($c) => [$c['competency']['id'], $c['current']])->all()
        );
        $this->assertEquals($webCard['record'], $apiCard['record']);
        $this->assertSame('Solid.', $apiCard['notes'][0]['note']);
    }
}
