<?php

namespace Tests\Feature\Api\V1;

use App\Models\PerformanceCompetency;
use App\Models\PerformanceScore;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Covers the performance requirements of the `api-resources` spec: the
 * evaluation grid, the ratings upsert, and the catalog rules.
 */
class PerformanceTest extends TestCase
{
    use RefreshDatabase;

    private function team(?User $lead = null): Team
    {
        return Team::create(['name' => 'Core', 'color' => '#0891b2', 'team_lead_id' => $lead?->id]);
    }

    private function member(Team $team, string $role = User::ROLE_DEVELOPER): User
    {
        $user = User::factory()->create(['role' => $role]);
        $team->members()->attach($user);

        return $user;
    }

    private function competency(array $overrides = []): PerformanceCompetency
    {
        return PerformanceCompetency::create(array_merge([
            'key' => 'c-'.uniqid(),
            'name' => 'Code Quality',
            'category' => PerformanceCompetency::CATEGORY_TECHNICAL,
            'role_scope' => PerformanceCompetency::SCOPE_BOTH,
            'cadence' => PerformanceCompetency::CADENCE_WEEKLY,
            'weight' => 1, 'active' => true, 'position' => 0,
        ], $overrides));
    }

    public function test_grid_returns_members_and_competencies_for_the_cadence(): void
    {
        $team = $this->team();
        $member = $this->member($team);
        $weekly = $this->competency();
        $this->competency(['cadence' => PerformanceCompetency::CADENCE_DAILY, 'name' => 'Daily one']);

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

        $response = $this->getJson("/api/v1/performance/evaluate?team_id={$team->id}&cadence=weekly")
            ->assertOk()
            ->assertJsonPath('data.cadence', 'weekly')
            ->assertJsonPath('data.period.type', 'weekly');

        // Only the weekly competency belongs on a weekly grid.
        $this->assertCount(1, $response->json('data.competencies'));
        $this->assertSame($weekly->id, $response->json('data.competencies.0.id'));
        $this->assertSame($member->id, $response->json('data.rows.0.member.id'));
    }

    public function test_ratings_are_upserted_not_duplicated(): void
    {
        $team = $this->team();
        $member = $this->member($team);
        $competency = $this->competency();

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

        $payload = [
            'team_id' => $team->id,
            'user_id' => $member->id,
            'date' => today()->toDateString(),
            'cadence' => PerformanceCompetency::CADENCE_WEEKLY,
            'scores' => [$competency->id => 4],
            'notes' => [$competency->id => 'Solid work'],
        ];

        $this->putJson('/api/v1/performance/scores', $payload)
            ->assertOk()
            ->assertJsonPath('data.0.score', 4)
            ->assertJsonPath('data.0.score_label', 'Exceeds Expectations');

        $payload['scores'][$competency->id] = 5;
        $this->putJson('/api/v1/performance/scores', $payload)->assertOk();

        $this->assertSame(1, PerformanceScore::count());
        $this->assertSame(5, PerformanceScore::first()->score);
    }

    public function test_blank_cells_are_skipped(): void
    {
        $team = $this->team();
        $member = $this->member($team);
        $rated = $this->competency();
        $unrated = $this->competency(['name' => 'Second']);

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

        $this->putJson('/api/v1/performance/scores', [
            'team_id' => $team->id,
            'user_id' => $member->id,
            'date' => today()->toDateString(),
            'cadence' => PerformanceCompetency::CADENCE_WEEKLY,
            'scores' => [$rated->id => 3, $unrated->id => null],
        ])->assertOk();

        $this->assertSame(1, PerformanceScore::count());
        $this->assertSame($rated->id, PerformanceScore::first()->competency_id);
    }

    public function test_a_future_period_is_rejected(): void
    {
        $team = $this->team();
        $member = $this->member($team);
        $competency = $this->competency();

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

        $this->putJson('/api/v1/performance/scores', [
            'team_id' => $team->id,
            'user_id' => $member->id,
            'date' => Carbon::today()->addMonth()->toDateString(),
            'cadence' => PerformanceCompetency::CADENCE_WEEKLY,
            'scores' => [$competency->id => 4],
        ])->assertStatus(422)->assertJsonValidationErrors('date');
    }

    public function test_a_score_outside_the_scale_is_rejected(): void
    {
        $team = $this->team();
        $member = $this->member($team);
        $competency = $this->competency();

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

        $this->putJson('/api/v1/performance/scores', [
            'team_id' => $team->id,
            'user_id' => $member->id,
            'date' => today()->toDateString(),
            'cadence' => PerformanceCompetency::CADENCE_WEEKLY,
            'scores' => [$competency->id => 6],
        ])->assertStatus(422);
    }

    public function test_a_team_lead_may_evaluate_only_their_own_team(): void
    {
        $lead = User::factory()->create(['role' => User::ROLE_TEAM_LEAD]);
        $ownTeam = $this->team($lead);
        $otherTeam = Team::create(['name' => 'Other', 'color' => '#111111']);

        $ownMember = $this->member($ownTeam);
        $otherMember = $this->member($otherTeam);
        $competency = $this->competency();

        Sanctum::actingAs($lead);

        $this->putJson('/api/v1/performance/scores', [
            'team_id' => $ownTeam->id,
            'user_id' => $ownMember->id,
            'date' => today()->toDateString(),
            'cadence' => PerformanceCompetency::CADENCE_WEEKLY,
            'scores' => [$competency->id => 4],
        ])->assertOk();

        $this->putJson('/api/v1/performance/scores', [
            'team_id' => $otherTeam->id,
            'user_id' => $otherMember->id,
            'date' => today()->toDateString(),
            'cadence' => PerformanceCompetency::CADENCE_WEEKLY,
            'scores' => [$competency->id => 4],
        ])->assertForbidden();
    }

    public function test_the_evaluator_is_recorded(): void
    {
        $team = $this->team();
        $member = $this->member($team);
        $competency = $this->competency();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        Sanctum::actingAs($admin);

        $this->putJson('/api/v1/performance/scores', [
            'team_id' => $team->id,
            'user_id' => $member->id,
            'date' => today()->toDateString(),
            'cadence' => PerformanceCompetency::CADENCE_WEEKLY,
            'scores' => [$competency->id => 4],
        ])->assertOk()->assertJsonPath('data.0.evaluator.id', $admin->id);
    }

    public function test_a_scored_competency_cannot_be_deleted_only_deactivated(): void
    {
        $team = $this->team();
        $member = $this->member($team);
        $competency = $this->competency();

        PerformanceScore::create([
            'team_id' => $team->id, 'user_id' => $member->id,
            'evaluator_id' => $member->id, 'competency_id' => $competency->id,
            'period_type' => 'weekly',
            'period_start' => today()->startOfWeek(Carbon::MONDAY)->toDateString(),
            'period_end' => today()->endOfWeek(Carbon::SUNDAY)->toDateString(),
            'score' => 4,
        ]);

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

        $this->deleteJson("/api/v1/performance/competencies/{$competency->id}")->assertStatus(422);
        $this->assertDatabaseHas('performance_competencies', ['id' => $competency->id]);

        $this->postJson("/api/v1/performance/competencies/{$competency->id}/toggle")
            ->assertOk()
            ->assertJsonPath('data.active', false);
    }

    public function test_an_unscored_competency_can_be_deleted(): void
    {
        $competency = $this->competency();
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

        $this->deleteJson("/api/v1/performance/competencies/{$competency->id}")->assertOk();
        $this->assertDatabaseMissing('performance_competencies', ['id' => $competency->id]);
    }

    public function test_creating_a_competency_derives_a_unique_key(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

        $first = $this->postJson('/api/v1/performance/competencies', [
            'name' => 'Attention to Detail',
            'category' => PerformanceCompetency::CATEGORY_TECHNICAL,
            'role_scope' => PerformanceCompetency::SCOPE_QA,
            'cadence' => PerformanceCompetency::CADENCE_DAILY,
            'weight' => 3,
            'active' => true,
        ])->assertCreated();

        $second = $this->postJson('/api/v1/performance/competencies', [
            'name' => 'Attention to Detail',
            'category' => PerformanceCompetency::CATEGORY_TECHNICAL,
            'role_scope' => PerformanceCompetency::SCOPE_QA,
            'cadence' => PerformanceCompetency::CADENCE_DAILY,
            'weight' => 3,
            'active' => true,
        ])->assertCreated();

        $this->assertSame('attention-to-detail', $first->json('data.key'));
        $this->assertSame('attention-to-detail-2', $second->json('data.key'));
    }

    public function test_competency_active_flag_accepts_a_json_boolean(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

        // The web form sends a checkbox; a JSON client sends a real boolean.
        // Both must land on the same stored value.
        $this->postJson('/api/v1/performance/competencies', [
            'name' => 'Retired dimension',
            'category' => PerformanceCompetency::CATEGORY_GROWTH,
            'role_scope' => PerformanceCompetency::SCOPE_BOTH,
            'cadence' => PerformanceCompetency::CADENCE_WEEKLY,
            'weight' => 2,
            'active' => false,
        ])->assertCreated()->assertJsonPath('data.active', false);
    }

    public function test_member_scorecard_is_returned_for_an_accessible_team(): void
    {
        $team = $this->team();
        $member = $this->member($team);
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

        $this->getJson("/api/v1/performance/members/{$member->id}?team_id={$team->id}")
            ->assertOk()
            ->assertJsonPath('data.member.id', $member->id)
            ->assertJsonPath('data.team.id', $team->id)
            ->assertJsonStructure(['data' => ['scorecard', 'week' => ['label', 'prev', 'next']]]);
    }

    public function test_team_overview_is_returned(): void
    {
        $team = $this->team();
        $this->member($team);
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

        $this->getJson("/api/v1/performance/overview?team_id={$team->id}")
            ->assertOk()
            ->assertJsonPath('data.team.id', $team->id)
            ->assertJsonStructure(['data' => ['teams', 'week', 'overview']]);
    }
}
