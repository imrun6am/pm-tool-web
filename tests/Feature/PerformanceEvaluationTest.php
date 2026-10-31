<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\PerformanceCompetency;
use App\Models\PerformanceScore;
use App\Models\Project;
use App\Models\Release;
use App\Models\TasksheetEntry;
use App\Models\Team;
use App\Models\User;
use App\Services\PerformanceAnalytics;
use App\Support\PerformancePeriod;
use Database\Seeders\PerformanceCompetencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PerformanceEvaluationTest extends TestCase
{
    use RefreshDatabase;

    private function team(?User $lead = null): Team
    {
        return Team::create([
            'name' => 'Core', 'color' => '#0891b2', 'team_lead_id' => $lead?->id,
        ]);
    }

    private function member(Team $team, string $role = User::ROLE_DEVELOPER): User
    {
        $user = User::factory()->create(['role' => $role]);
        $team->members()->attach($user);

        return $user;
    }

    private function weeklyComp(array $o = []): PerformanceCompetency
    {
        return PerformanceCompetency::create(array_merge([
            'key' => 'c-'.uniqid(), 'name' => 'Comp '.uniqid(),
            'category' => PerformanceCompetency::CATEGORY_TECHNICAL,
            'role_scope' => PerformanceCompetency::SCOPE_BOTH,
            'cadence' => PerformanceCompetency::CADENCE_WEEKLY,
            'weight' => 1, 'active' => true, 'position' => 0,
        ], $o));
    }

    private function scorePayload(Team $team, User $member, array $scores, string $cadence, ?string $date = null, array $notes = []): array
    {
        return [
            'team_id' => $team->id, 'user_id' => $member->id,
            'date' => $date ?? today()->toDateString(),
            'cadence' => $cadence, 'scores' => $scores, 'notes' => $notes,
        ];
    }

    private function scoreDirect(Team $team, User $member, PerformanceCompetency $c, int $value, ?Carbon $date = null, ?User $evaluator = null): PerformanceScore
    {
        $period = PerformancePeriod::normalize($c->cadence, $date ?? today());

        return PerformanceScore::create([
            'team_id' => $team->id, 'user_id' => $member->id,
            'evaluator_id' => $evaluator?->id,
            'competency_id' => $c->id,
            'period_type' => $period['type'],
            'period_start' => $period['start']->toDateString(),
            'period_end' => $period['end']->toDateString(),
            'score' => $value,
        ]);
    }

    // ---- 8.1 access ---------------------------------------------------------

    public function test_developers_qa_viewers_are_blocked_from_every_performance_route(): void
    {
        $team = $this->team();
        $dev = $this->member($team);
        $comp = $this->weeklyComp();

        foreach ([User::ROLE_DEVELOPER, User::ROLE_QA, User::ROLE_VIEWER] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get(route('performance.index'))->assertForbidden();
            $this->actingAs($user)->get(route('performance.evaluate'))->assertForbidden();
            $this->actingAs($user)->get(route('performance.members.show', $dev))->assertForbidden();
            $this->actingAs($user)->get(route('performance.competencies.index'))->assertForbidden();
            $this->actingAs($user)->put(route('performance.scores.upsert'),
                $this->scorePayload($team, $dev, [$comp->id => 4], 'weekly'))->assertForbidden();
        }
    }

    public function test_leads_reach_the_section_and_nav_entry_is_lead_only(): void
    {
        $lead = User::factory()->create(['role' => User::ROLE_TEAM_LEAD]);
        $this->actingAs($lead)->get(route('performance.index'))->assertOk();

        // Nav shows Performance for a lead, not for a developer.
        $this->actingAs($lead)->get(route('dashboard'))->assertSee('>Performance<', false);
        $dev = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $this->actingAs($dev)->get(route('dashboard'))->assertDontSee('>Performance<', false);
    }

    // ---- 8.2 scoping --------------------------------------------------------

    public function test_team_lead_is_scoped_to_their_own_team(): void
    {
        $leadA = User::factory()->create(['role' => User::ROLE_TEAM_LEAD]);
        $leadB = User::factory()->create(['role' => User::ROLE_TEAM_LEAD]);
        $teamA = $this->team($leadA);
        $teamB = $this->team($leadB);
        $devA = $this->member($teamA);
        $devB = $this->member($teamB);
        $comp = $this->weeklyComp();

        // leadA rates their own team member — ok.
        $this->actingAs($leadA)->put(route('performance.scores.upsert'),
            $this->scorePayload($teamA, $devA, [$comp->id => 4], 'weekly'))->assertRedirect();

        // leadA cannot rate teamB's member — forbidden.
        $this->actingAs($leadA)->put(route('performance.scores.upsert'),
            $this->scorePayload($teamB, $devB, [$comp->id => 4], 'weekly'))->assertForbidden();

        // leadA cannot view teamB member's scorecard.
        $this->actingAs($leadA)->get(route('performance.members.show', ['user' => $devB, 'team' => $teamB->id]))
            ->assertForbidden();

        // An admin can rate any team.
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($admin)->put(route('performance.scores.upsert'),
            $this->scorePayload($teamB, $devB, [$comp->id => 5], 'weekly'))->assertRedirect();
    }

    // ---- 8.3 scoring --------------------------------------------------------

    public function test_scoring_upserts_records_evaluator_and_validates(): void
    {
        $lead = User::factory()->create(['role' => User::ROLE_TEAM_LEAD]);
        $team = $this->team($lead);
        $dev = $this->member($team);
        $comp = $this->weeklyComp();

        // Create.
        $this->actingAs($lead)->put(route('performance.scores.upsert'),
            $this->scorePayload($team, $dev, [$comp->id => 4], 'weekly'))->assertRedirect();
        $this->assertSame(1, PerformanceScore::count());
        $this->assertSame($lead->id, PerformanceScore::first()->evaluator_id);

        // Re-rate — updates, no duplicate.
        $this->actingAs($lead)->put(route('performance.scores.upsert'),
            $this->scorePayload($team, $dev, [$comp->id => 5], 'weekly'))->assertRedirect();
        $this->assertSame(1, PerformanceScore::count());
        $this->assertSame(5, PerformanceScore::first()->score);

        // Out of range rejected.
        $this->actingAs($lead)->put(route('performance.scores.upsert'),
            $this->scorePayload($team, $dev, [$comp->id => 6], 'weekly'))
            ->assertSessionHasErrors('scores.'.$comp->id);

        // Future period rejected.
        $daily = $this->weeklyComp(['cadence' => 'daily']);
        $this->actingAs($lead)->put(route('performance.scores.upsert'),
            $this->scorePayload($team, $dev, [$daily->id => 4], 'daily', today()->addDay()->toDateString()))
            ->assertSessionHasErrors('date');

        // Non-member target rejected.
        $stranger = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $this->actingAs($lead)->put(route('performance.scores.upsert'),
            $this->scorePayload($team, $stranger, [$comp->id => 4], 'weekly'))
            ->assertSessionHasErrors('user_id');
    }

    // ---- 8.4 cadence / period ----------------------------------------------

    public function test_weekly_score_normalizes_to_the_iso_week(): void
    {
        $lead = User::factory()->create(['role' => User::ROLE_TEAM_LEAD]);
        $team = $this->team($lead);
        $dev = $this->member($team);
        $comp = $this->weeklyComp();

        // A non-Monday day in a past week.
        $wednesday = today()->subWeek()->startOfWeek(Carbon::MONDAY)->addDays(2);
        $this->assertTrue($wednesday->isWednesday());

        $this->actingAs($lead)->put(route('performance.scores.upsert'),
            $this->scorePayload($team, $dev, [$comp->id => 3], 'weekly', $wednesday->toDateString()))->assertRedirect();

        $score = PerformanceScore::first();
        $this->assertSame('weekly', $score->period_type);
        $this->assertTrue($score->period_start->isMonday());
        $this->assertTrue($score->period_end->isSunday());
        $this->assertTrue($score->period_start->lte($wednesday) && $wednesday->lte($score->period_end));
    }

    public function test_daily_score_is_keyed_to_the_date(): void
    {
        $lead = User::factory()->create(['role' => User::ROLE_TEAM_LEAD]);
        $team = $this->team($lead);
        $dev = $this->member($team);
        $comp = $this->weeklyComp(['cadence' => 'daily']);
        $day = today()->subDays(3);

        $this->actingAs($lead)->put(route('performance.scores.upsert'),
            $this->scorePayload($team, $dev, [$comp->id => 4], 'daily', $day->toDateString()))->assertRedirect();

        $score = PerformanceScore::first();
        $this->assertSame('daily', $score->period_type);
        $this->assertTrue($score->period_start->isSameDay($day));
        $this->assertTrue($score->period_end->isSameDay($day));
    }

    // ---- 8.5 privacy --------------------------------------------------------

    public function test_scores_never_reach_the_activity_feed(): void
    {
        $lead = User::factory()->create(['role' => User::ROLE_TEAM_LEAD]);
        $team = $this->team($lead);
        $dev = $this->member($team);
        $comp = $this->weeklyComp();

        $this->actingAs($lead)->put(route('performance.scores.upsert'),
            $this->scorePayload($team, $dev, [$comp->id => 4], 'weekly', null, [$comp->id => 'secret note']))->assertRedirect();

        $this->assertSame(0, Activity::where('subject_type', 'like', '%Performance%')->count());
        $this->assertSame(0, Activity::where('description', 'like', '%secret note%')->count());
    }

    // ---- 8.6 analytics ------------------------------------------------------

    public function test_weighted_headline_uses_rated_competencies_only(): void
    {
        $team = $this->team();
        $dev = $this->member($team);
        $c1 = $this->weeklyComp(['weight' => 1]);
        $c3 = $this->weeklyComp(['weight' => 3]);
        $this->weeklyComp(['weight' => 5]); // applicable but unrated — must not count as zero

        $this->scoreDirect($team, $dev, $c1, 2);
        $this->scoreDirect($team, $dev, $c3, 4);

        $card = app(PerformanceAnalytics::class)->memberScorecard($dev, $team, today());
        // (2*1 + 4*3) / (1+3) = 3.5
        $this->assertSame(3.5, $card['overall']);
    }

    public function test_unevaluated_member_has_no_score_and_no_division_by_zero(): void
    {
        $team = $this->team();
        $dev = $this->member($team);
        $this->weeklyComp();

        $card = app(PerformanceAnalytics::class)->memberScorecard($dev, $team, today());
        $this->assertNull($card['overall']);

        $overview = app(PerformanceAnalytics::class)->teamOverview($team, today());
        $this->assertNull($overview['teamAverage']);
        // One unrated member → 0 of 1 expected = 0% (divide-by-zero-safe, not an error).
        $this->assertSame(0, $overview['coverage']['pct']);
    }

    public function test_coverage_excludes_members_on_leave_all_week(): void
    {
        $team = $this->team();
        $dev = $this->member($team);
        $this->weeklyComp();

        // On leave every day of this week, no working entries.
        $start = today()->startOfWeek(Carbon::MONDAY);
        for ($i = 0; $i < 5; $i++) {
            TasksheetEntry::create([
                'team_id' => $team->id, 'user_id' => $dev->id,
                'date' => $start->copy()->addDays($i)->toDateString(), 'leave_type' => 'sick',
            ]);
        }

        $overview = app(PerformanceAnalytics::class)->teamOverview($team, today());
        $row = collect($overview['rows'])->firstWhere('member.id', $dev->id);
        $this->assertTrue($row['onLeave']);
        $this->assertSame(0, $row['expected']);
        $this->assertNotContains($dev->name, $overview['coverage']['unrated']);
    }

    public function test_needs_attention_flags_low_scorers(): void
    {
        $team = $this->team();
        $dev = $this->member($team);
        $comp = $this->weeklyComp();
        $this->scoreDirect($team, $dev, $comp, 2); // below 3.0

        $overview = app(PerformanceAnalytics::class)->teamOverview($team, today());
        $this->assertTrue($overview['needsAttention']->contains(fn ($r) => $r['member']->id === $dev->id));
    }

    public function test_memberless_team_overview_is_empty_not_erroring(): void
    {
        $team = $this->team();
        $overview = app(PerformanceAnalytics::class)->teamOverview($team, today());
        $this->assertTrue($overview['members']->isEmpty());
        $this->assertNull($overview['teamAverage']);
    }

    // ---- 8.7 catalog --------------------------------------------------------

    public function test_org_leads_manage_catalog_but_team_leads_cannot(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $teamLead = User::factory()->create(['role' => User::ROLE_TEAM_LEAD]);

        // Team lead: forbidden.
        $this->actingAs($teamLead)->get(route('performance.competencies.index'))->assertForbidden();
        $this->actingAs($teamLead)->post(route('performance.competencies.store'), [
            'name' => 'X', 'category' => 'technical', 'role_scope' => 'both', 'cadence' => 'weekly', 'weight' => 1,
        ])->assertForbidden();

        // Admin: creates.
        $this->actingAs($admin)->post(route('performance.competencies.store'), [
            'name' => 'Craftsmanship', 'category' => 'technical', 'role_scope' => 'both', 'cadence' => 'weekly', 'weight' => 2, 'active' => 1,
        ])->assertRedirect();
        $this->assertDatabaseHas('performance_competencies', ['name' => 'Craftsmanship', 'weight' => 2]);

        // Invalid weight rejected.
        $this->actingAs($admin)->post(route('performance.competencies.store'), [
            'name' => 'Bad', 'category' => 'technical', 'role_scope' => 'both', 'cadence' => 'weekly', 'weight' => 0,
        ])->assertSessionHasErrors('weight');
    }

    public function test_deactivated_competency_leaves_grid_but_keeps_history(): void
    {
        $lead = User::factory()->create(['role' => User::ROLE_TEAM_LEAD]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $team = $this->team($lead);
        $dev = $this->member($team);
        $comp = $this->weeklyComp(['name' => 'Retired Skill']);
        $this->scoreDirect($team, $dev, $comp, 4);

        // Deactivate.
        $this->actingAs($admin)->post(route('performance.competencies.toggle', $comp))->assertRedirect();
        $this->assertFalse($comp->fresh()->active);

        // Off the weekly evaluation grid.
        $this->actingAs($lead)->get(route('performance.evaluate', ['team' => $team->id, 'cadence' => 'weekly']))
            ->assertDontSee('Retired Skill');

        // History still counts in analytics.
        $card = app(PerformanceAnalytics::class)->memberScorecard($dev, $team, today());
        $this->assertNotEmpty($card['history']);
    }

    public function test_scored_competency_cannot_be_deleted(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $team = $this->team();
        $dev = $this->member($team);
        $comp = $this->weeklyComp();
        $this->scoreDirect($team, $dev, $comp, 3);

        $this->actingAs($admin)->delete(route('performance.competencies.destroy', $comp))
            ->assertSessionHas('error');
        $this->assertDatabaseHas('performance_competencies', ['id' => $comp->id]);
    }

    public function test_seeded_catalog_spans_roles_categories_and_cadences(): void
    {
        $this->seed(PerformanceCompetencySeeder::class);

        $this->assertGreaterThanOrEqual(1, PerformanceCompetency::where('cadence', 'daily')->count());
        $this->assertGreaterThanOrEqual(1, PerformanceCompetency::where('cadence', 'weekly')->count());
        $this->assertSame(4, PerformanceCompetency::distinct('category')->count('category'));
        $this->assertTrue(PerformanceCompetency::where('role_scope', 'qa')->exists());
    }

    public function test_evaluation_grid_renders_with_members_but_no_scores(): void
    {
        $lead = User::factory()->create(['role' => User::ROLE_TEAM_LEAD]);
        $team = $this->team($lead);
        $dev = $this->member($team);
        $this->weeklyComp();

        // No scores exist for this team/period — the grid must still render.
        $this->actingAs($lead)->get(route('performance.evaluate', ['team' => $team->id, 'cadence' => 'weekly']))
            ->assertOk()
            ->assertSee($dev->name);
    }

    public function test_all_performance_pages_render(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $lead = User::factory()->create(['role' => User::ROLE_TEAM_LEAD]);
        $team = $this->team($lead);
        $dev = $this->member($team);
        $comp = $this->weeklyComp();
        $this->scoreDirect($team, $dev, $comp, 4, today(), $lead);

        $this->actingAs($lead)->get(route('performance.index', ['team' => $team->id]))->assertOk()->assertSee($team->name);
        $this->actingAs($lead)->get(route('performance.evaluate', ['team' => $team->id]))->assertOk();
        $this->actingAs($lead)->get(route('performance.members.show', ['user' => $dev, 'team' => $team->id]))->assertOk()->assertSee($dev->name);
        $this->actingAs($admin)->get(route('performance.competencies.index'))->assertOk();
        $this->actingAs($admin)->get(route('performance.competencies.create'))->assertOk();
        $this->actingAs($admin)->get(route('performance.competencies.edit', $comp))->assertOk();
    }

    // ---- 8.8 objective panels ----------------------------------------------

    public function test_objective_panels_reflect_tasksheet_and_board(): void
    {
        $team = $this->team();
        $dev = $this->member($team);

        $start = today()->startOfWeek(Carbon::MONDAY);
        TasksheetEntry::create([
            'team_id' => $team->id, 'user_id' => $dev->id, 'date' => $start->toDateString(),
            'work_points' => 5, 'ticket_count' => 2, 'ticket_points' => 3,
        ]);
        TasksheetEntry::create([
            'team_id' => $team->id, 'user_id' => $dev->id, 'date' => $start->copy()->addDay()->toDateString(),
            'work_points' => 4, 'ticket_count' => 1, 'ticket_points' => 1,
        ]);

        // Board: 3 tasks (1 done, 1 recheck, 1 open).
        $project = Project::create(['name' => 'P', 'color' => '#000']);
        $release = Release::create([
            'project_id' => $project->id, 'team_id' => $team->id, 'name' => 'R',
            'year' => (int) now()->year, 'quarter' => 1,
            'start_date' => today()->toDateString(), 'end_date' => today()->addDays(10)->toDateString(),
        ]);
        foreach (['done', 'recheck', 'in_progress'] as $i => $status) {
            $release->tasks()->create([
                'title' => 'T'.$i, 'status' => $status, 'assignee_id' => $dev->id, 'created_by' => $dev->id, 'position' => $i,
            ]);
        }

        $card = app(PerformanceAnalytics::class)->memberScorecard($dev, $team, today());
        $this->assertSame(9, $card['tasksheet']['workPoints']);
        $this->assertSame(3, $card['tasksheet']['ticketCount']);
        $this->assertSame(3, $card['board']['assigned']);
        $this->assertSame(1, $card['board']['done']);
        $this->assertSame(1, $card['board']['rework']);
    }
}
