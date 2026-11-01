<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\TasksheetEntry;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The analytics half of the developer/QA dashboard, on both surfaces.
 * Metric arithmetic is covered by MemberAnalyticsServiceTest; here it is the
 * rendering, the framing, and the API contract.
 */
class MemberDashboardAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private function memberWithHistory(string $role): User
    {
        $member = User::factory()->create(['role' => $role, 'name' => 'Morgan Member']);

        $this->travelTo(Carbon::parse('2026-11-02 09:00:00'));
        $task = Task::create(['title' => 'Ship the login fix', 'status' => 'todo', 'assignee_id' => $member->id, 'due_date' => '2026-11-06']);
        $task->update(['status' => 'in_progress']);
        $this->travelTo(Carbon::parse('2026-11-04 09:00:00'));
        $task->update(['status' => 'done']);

        Task::create(['title' => 'Write regression notes', 'status' => 'in_progress', 'assignee_id' => $member->id]);

        $this->travelTo(Carbon::parse('2026-11-11 10:00:00'));

        return $member;
    }

    public function test_developer_sees_their_analytics(): void
    {
        $dev = $this->memberWithHistory(User::ROLE_DEVELOPER);

        $this->actingAs($dev)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('My day')
            ->assertSee("You've finished 1 task this month — 1 more than at this point last month.")
            ->assertSee('This month at a glance')
            ->assertSee('Completed per week')
            ->assertSee('My tasks by status')
            ->assertSee('Your activity')
            ->assertSee('Focus today')
            ->assertSee('Recently completed')
            ->assertSee('Ship the login fix')
            ->assertSee('100%')   // on-time: finished the 4th, due the 6th
            ->assertSee('2d')     // cycle time: In Progress Nov 2 → Done Nov 4
            ->assertSee('role="img"', false)
            ->assertDontSee('bg-danger-50/50', false);
    }

    public function test_qa_sees_their_analytics_for_the_chosen_period(): void
    {
        $qa = $this->memberWithHistory(User::ROLE_QA);

        $this->actingAs($qa)->get(route('dashboard', ['period' => 'week']))
            ->assertOk()
            ->assertSee('This week at a glance')
            ->assertSee('A fresh week — let&#039;s get it going.', false)
            ->assertSee('value="week" checked', false);
    }

    public function test_an_unknown_period_falls_back_to_the_month_on_the_web(): void
    {
        $dev = User::factory()->create(['role' => User::ROLE_DEVELOPER]);

        $this->actingAs($dev)->get(route('dashboard', ['period' => 'decade']))
            ->assertOk()
            ->assertSee('This month at a glance');
    }

    public function test_a_brand_new_member_gets_encouraging_empty_states(): void
    {
        $dev = User::factory()->create(['role' => User::ROLE_DEVELOPER, 'name' => 'Nova Newbie']);

        $this->actingAs($dev)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Welcome aboard, Nova!')
            ->assertSee('Your weekly trend starts with your first finished task.')
            ->assertSee('Your finished work will show up here.')
            ->assertSee('Nothing urgent right now.')
            ->assertSee('Join a team to start your tasksheet')
            ->assertSee("we'll learn your usual weekly rhythm", false)
            ->assertDontSee('>0%<', false);
    }

    public function test_leads_keep_the_planning_dashboard(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_TEAM_LEAD]))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Completed per week');
    }

    public function test_api_returns_member_analytics_and_validates_the_period(): void
    {
        $dev = $this->memberWithHistory(User::ROLE_DEVELOPER);
        Sanctum::actingAs($dev);

        $this->getJson('/api/v1/dashboard?period=month')
            ->assertOk()
            ->assertJsonPath('data.mode', 'member')
            ->assertJsonPath('data.analytics.period.key', 'month')
            ->assertJsonPath('data.analytics.period.previous_end', '2026-10-11')
            ->assertJsonPath('data.analytics.kpis.completed', ['current' => 1, 'previous' => 0, 'change' => 1])
            ->assertJsonPath('data.analytics.kpis.on_time_rate.current', 100)
            ->assertJsonPath('data.analytics.kpis.cycle_days.current', 2)
            ->assertJsonPath('data.analytics.focus.0.reason', 'in_progress')
            ->assertJsonPath('data.analytics.focus.0.task.title', 'Write regression notes')
            ->assertJsonPath('data.analytics.recent.0.task.title', 'Ship the login fix')
            ->assertJsonPath('data.analytics.has_history', true)
            ->assertJsonCount(12, 'data.analytics.weekly')
            ->assertJsonStructure(['data' => [
                'my_tasks', 'overdue_count', 'tasksheet_today', 'upcoming_meetings',
                'analytics' => ['today' => ['open', 'overdue', 'due_soon', 'in_progress', 'in_review'],
                    'pace' => ['this_week', 'goal', 'pct'], 'streak' => ['current', 'best', 'active_today'],
                    'heatmap' => ['total', 'active_days', 'days'], 'lead_time_trend', 'status_counts', 'best_week'],
            ]]);

        $this->getJson('/api/v1/dashboard?period=year')
            ->assertStatus(422)
            ->assertJsonValidationErrors('period');
    }

    public function test_api_never_leaks_lead_feedback(): void
    {
        $dev = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $team = Team::create(['name' => 'Alpha', 'color' => '#0891b2']);
        $team->members()->attach($dev);
        TasksheetEntry::create([
            'team_id' => $team->id, 'user_id' => $dev->id, 'date' => today()->subDay()->toDateString(),
            'plan' => 'Plan', 'feedback' => 'Private lead note',
        ]);

        Sanctum::actingAs($dev);
        $this->assertStringNotContainsString(
            'Private lead note',
            $this->getJson('/api/v1/dashboard')->assertOk()->getContent()
        );
    }
}
