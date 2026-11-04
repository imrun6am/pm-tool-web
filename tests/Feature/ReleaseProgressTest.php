<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Release;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Services\ReleaseProgressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** A release's burn-up and forecast. "Today" is Monday, 2 November 2026. */
class ReleaseProgressTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    private Release $release;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-02 09:00:00');
        $this->team = Team::create(['name' => 'Core', 'color' => '#0891b2']);
        $this->release = $this->newRelease('2026-10-20', '2026-11-30');
    }

    private function newRelease(string $start, string $end): Release
    {
        return Release::create([
            'project_id' => Project::create(['name' => 'Shop', 'color' => '#0891b2'])->id,
            'team_id' => $this->team->id,
            'name' => 'Checkout',
            'year' => 2026, 'quarter' => 4,
            'start_date' => $start, 'end_date' => $end,
        ]);
    }

    private function task(string $createdAt, ?string $doneAt = null, array $attrs = []): Task
    {
        $back = now()->copy();
        $this->travelTo($createdAt);
        $task = Task::create($attrs + ['title' => 'Work', 'status' => 'todo', 'position' => 0, 'release_id' => $this->release->id, 'team_id' => $this->team->id]);
        if ($doneAt) {
            $this->travelTo($doneAt);
            $task->update(['status' => 'done']);
        }
        $this->travelTo($back);

        return $task;
    }

    /** One task finished in each week, counting back from last week. */
    private function history(array $perWeek): void
    {
        foreach ($perWeek as $weeksAgo => $count) {
            for ($n = 0; $n < $count; $n++) {
                $day = today()->startOfWeek()->subWeeks($weeksAgo)->addDay();
                $this->task($day->copy()->subWeeks(20)->toDateTimeString(), $day->toDateTimeString(), ['release_id' => null]);
            }
        }
    }

    private function point(array $burnUp, string $date): array
    {
        return collect($burnUp)->first(fn ($p) => $p['date']->toDateString() === $date);
    }

    public function test_the_burn_up_shows_scope_growing_and_work_finishing(): void
    {
        $this->task('2026-10-01 09:00:00', '2026-10-25 09:00:00'); // from before the release started
        $this->task('2026-10-22 09:00:00');
        $this->task('2026-10-28 09:00:00', '2026-10-30 09:00:00'); // added later

        $burnUp = app(ReleaseProgressService::class)->burnUp($this->release);

        $this->assertSame('2026-10-20', $burnUp[0]['date']->toDateString());
        $this->assertSame('2026-11-02', last($burnUp)['date']->toDateString());
        $this->assertSame(['scope' => 1, 'done' => 0], array_intersect_key($burnUp[0], ['scope' => 0, 'done' => 0]));
        $this->assertSame(['scope' => 2, 'done' => 1], array_intersect_key($this->point($burnUp, '2026-10-26'), ['scope' => 0, 'done' => 0]));
        $this->assertSame(['scope' => 3, 'done' => 2], array_intersect_key(last($burnUp), ['scope' => 0, 'done' => 0]));
    }

    public function test_reopened_work_stops_counting_as_done_from_when_it_reopened(): void
    {
        $task = $this->task('2026-10-20 09:00:00', '2026-10-25 09:00:00');
        $this->travelTo('2026-10-28 09:00:00');
        $task->update(['status' => 'in_progress']);
        $this->travelTo('2026-11-02 09:00:00');

        $burnUp = app(ReleaseProgressService::class)->burnUp($this->release);

        $this->assertSame(1, $this->point($burnUp, '2026-10-26')['done']);
        $this->assertSame(0, last($burnUp)['done']);
    }

    public function test_archived_trashed_and_sub_tasks_are_not_counted(): void
    {
        $this->task('2026-10-21 09:00:00');
        $this->task('2026-10-21 09:00:00', null, ['status' => 'archive']);
        $this->task('2026-10-21 09:00:00')->delete();
        $parent = $this->task('2026-10-21 09:00:00');
        $this->task('2026-10-21 09:00:00', null, ['parent_id' => $parent->id]);

        $this->assertSame(2, last(app(ReleaseProgressService::class)->burnUp($this->release))['scope']);
    }

    public function test_a_long_release_is_drawn_with_a_readable_number_of_points(): void
    {
        $release = $this->newRelease('2025-11-02', '2026-11-30');

        $burnUp = app(ReleaseProgressService::class)->burnUp($release);

        $this->assertLessThanOrEqual(25, count($burnUp));
        $this->assertSame('2026-11-02', last($burnUp)['date']->toDateString());
    }

    public function test_a_release_that_has_not_started_still_has_a_point_to_draw(): void
    {
        $release = $this->newRelease('2026-12-01', '2026-12-20');

        $this->assertCount(1, app(ReleaseProgressService::class)->burnUp($release));
    }

    public function test_a_finished_release_stops_at_its_completion_date(): void
    {
        $this->release->update(['completed_at' => '2026-10-29 12:00:00']);

        $this->assertSame('2026-10-29', last(app(ReleaseProgressService::class)->burnUp($this->release))['date']->toDateString());
    }

    public function test_the_forecast_replays_the_teams_own_weeks(): void
    {
        $this->history([1 => 2, 2 => 2, 3 => 2, 4 => 2, 5 => 2, 6 => 2, 7 => 2, 8 => 2, 9 => 2, 10 => 2, 11 => 2, 12 => 2]);
        foreach (range(1, 6) as $n) {
            $this->task('2026-10-21 09:00:00');
        }

        $forecast = app(ReleaseProgressService::class)->forecast($this->release);

        // Exactly two a week means six tasks take exactly three weeks, every run.
        $this->assertSame(6, $forecast['remaining']);
        $this->assertSame(2.0, $forecast['avg_per_week']);
        $this->assertSame('2026-11-23', $forecast['dates']['p50']->toDateString());
        $this->assertSame('2026-11-23', $forecast['dates']['p95']->toDateString());
        $this->assertSame(100, $forecast['on_time_pct']);
    }

    public function test_slow_weeks_spread_the_dates_and_lower_the_chance_of_the_end_date(): void
    {
        $this->history([1 => 1, 3 => 4, 5 => 1, 7 => 4, 9 => 1, 11 => 4]);
        foreach (range(1, 8) as $n) {
            $this->task('2026-10-21 09:00:00');
        }

        $service = app(ReleaseProgressService::class);
        $forecast = $service->forecast($this->release);

        $this->assertTrue($forecast['dates']['p50']->lt($forecast['dates']['p95']));
        $this->assertLessThan(100, $forecast['on_time_pct']);
        $this->assertEquals($forecast['dates'], $service->forecast($this->release)['dates'], 'The page shows the same dates until the facts change.');
    }

    public function test_only_the_releases_own_team_feeds_its_forecast(): void
    {
        $other = Team::create(['name' => 'Other', 'color' => '#db2777']);
        $this->history([1 => 3, 2 => 3, 3 => 3]);
        Task::query()->update(['team_id' => $other->id]);
        $this->task('2026-10-21 09:00:00');

        $forecast = app(ReleaseProgressService::class)->forecast($this->release);

        $this->assertFalse($forecast['enough_history']);
        $this->assertNull($forecast['dates']);
    }

    public function test_no_forecast_without_history_or_with_nothing_left(): void
    {
        $this->task('2026-10-21 09:00:00');
        $service = app(ReleaseProgressService::class);
        $this->assertNull($service->forecast($this->release)['dates']);

        $this->history([1 => 1, 2 => 1, 3 => 1]);
        $this->assertNotNull($service->forecast($this->release)['dates']);

        Task::where('release_id', $this->release->id)->update(['status' => 'done']);
        $this->assertSame(0, $service->forecast($this->release)['remaining']);
        $this->assertNull($service->forecast($this->release)['dates']);
    }

    public function test_weeks_before_a_new_team_finished_anything_dont_slow_its_forecast(): void
    {
        $this->history([1 => 2, 2 => 2, 3 => 2]);
        $this->task('2026-10-21 09:00:00');

        $forecast = app(ReleaseProgressService::class)->forecast($this->release);

        $this->assertSame(3, $forecast['weeks_sampled']);
        $this->assertSame(2.0, $forecast['avg_per_week']);
        $this->assertSame('2026-11-09', $forecast['dates']['p85']->toDateString());
    }

    public function test_quiet_weeks_after_the_team_got_going_do_count(): void
    {
        $this->history([1 => 0, 2 => 0, 3 => 3, 4 => 3, 5 => 3]);
        $this->task('2026-10-21 09:00:00');

        $forecast = app(ReleaseProgressService::class)->forecast($this->release);

        $this->assertSame(5, $forecast['weeks_sampled']);
        $this->assertSame(1.8, $forecast['avg_per_week']);
    }

    public function test_past_the_planned_end_the_chance_of_making_it_is_zero(): void
    {
        $this->history([1 => 2, 2 => 2, 3 => 2]);
        $this->task('2026-10-21 09:00:00');
        $this->release->update(['end_date' => '2026-10-30']);

        $this->assertSame(0, app(ReleaseProgressService::class)->forecast($this->release)['on_time_pct']);
    }

    public function test_the_release_page_shows_the_chart_and_the_forecast(): void
    {
        $lead = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->history([1 => 2, 2 => 2, 3 => 2]);
        $this->task('2026-10-21 09:00:00');

        $this->actingAs($lead)->get(route('releases.show', $this->release))
            ->assertOk()
            ->assertSee('Progress')
            ->assertSee('Likely finish')
            ->assertSee('Chance of making Nov 30')
            ->assertSee('A likelihood, not a promise');
    }

    public function test_the_release_page_copes_with_no_tasks(): void
    {
        $lead = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($lead)->get(route('releases.show', $this->release))
            ->assertOk()
            ->assertSee('Progress shows here once this release has tasks.');
    }

    public function test_the_api_returns_the_same_figures(): void
    {
        $this->history([1 => 2, 2 => 2, 3 => 2]);
        $this->task('2026-10-21 09:00:00');
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_VIEWER]));

        $this->getJson("/api/v1/releases/{$this->release->id}/progress")
            ->assertOk()
            ->assertJsonPath('data.forecast.remaining', 1)
            ->assertJsonPath('data.forecast.dates.p50', '2026-11-09')
            ->assertJsonPath('data.burn_up.0.date', '2026-10-20');
    }

    public function test_a_developer_on_another_team_cannot_read_the_api(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_DEVELOPER]));

        $this->getJson("/api/v1/releases/{$this->release->id}/progress")->assertForbidden();
    }
}
