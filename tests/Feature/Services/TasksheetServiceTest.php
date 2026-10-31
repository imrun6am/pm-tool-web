<?php

namespace Tests\Feature\Services;

use App\Models\TasksheetEntry;
use App\Models\Team;
use App\Models\User;
use App\Services\TasksheetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Direct tests of the shared tasksheet logic — reachable without an HTTP
 * request now that it lives in a service.
 */
class TasksheetServiceTest extends TestCase
{
    use RefreshDatabase;

    private TasksheetService $service;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(TasksheetService::class);
        $this->team = Team::create(['name' => 'Core', 'color' => '#0891b2']);
    }

    private function member(string $role = User::ROLE_DEVELOPER): User
    {
        $user = User::factory()->create(['role' => $role]);
        $this->team->members()->attach($user);

        return $user;
    }

    private function entry(User $member, string $date, array $attributes = []): TasksheetEntry
    {
        return TasksheetEntry::create($attributes + [
            'team_id' => $this->team->id,
            'user_id' => $member->id,
            'date' => $date,
        ]);
    }

    /**
     * The regression this refactor exists to make impossible.
     *
     * `date` is cast to a date but stored as a midnight timestamp, so bounding
     * the query with `whereBetween` and plain 'Y-m-d' strings sorted
     * '2026-07-26 00:00:00' *after* '2026-07-26' and dropped the viewed day —
     * the one the chart is centred on.
     */
    public function test_trend_includes_the_viewed_day(): void
    {
        $member = $this->member();
        $today = today();

        $this->entry($member, $today->toDateString(), ['work_points' => 7]);

        $trend = $this->service->trend($this->team, $today);

        $this->assertCount(TasksheetService::TREND_DAYS, $trend);

        $last = end($trend);
        $this->assertSame($today->toDateString(), $last['date']);
        $this->assertTrue($last['current']);
        $this->assertSame(7, $last['wp'], 'The viewed day must not be dropped by the date-cast comparison.');
    }

    public function test_trend_fills_quiet_days_with_zero(): void
    {
        $member = $this->member();
        $today = today();

        $this->entry($member, $today->copy()->subDays(3)->toDateString(), ['work_points' => 5]);

        $trend = $this->service->trend($this->team, $today);
        $byDate = collect($trend)->keyBy('date');

        $this->assertSame(5, $byDate[$today->copy()->subDays(3)->toDateString()]['wp']);
        $this->assertSame(0, $byDate[$today->copy()->subDays(2)->toDateString()]['wp']);
        $this->assertSame(0, $byDate[$today->toDateString()]['wp']);
    }

    public function test_trend_sums_the_whole_team_per_day(): void
    {
        $a = $this->member();
        $b = $this->member(User::ROLE_QA);
        $today = today();

        $this->entry($a, $today->toDateString(), ['work_points' => 4]);
        $this->entry($b, $today->toDateString(), ['work_points' => 6]);

        $trend = $this->service->trend($this->team, $today);

        $this->assertSame(10, end($trend)['wp']);
    }

    public function test_rows_include_a_member_who_left_after_the_viewed_date(): void
    {
        $member = $this->member();
        $past = today()->subDays(10);

        // They leave today; a sheet for a day they *were* on the team must still
        // list them, or the record silently rewrites itself.
        $this->team->memberRecords()->updateExistingPivot($member->id, ['left_at' => now()]);

        $rows = $this->service->rowUsersFor($this->team, $past, collect());

        $this->assertTrue($rows->contains('id', $member->id));
    }

    public function test_rows_exclude_a_member_who_left_before_the_viewed_date(): void
    {
        $member = $this->member();

        $this->team->memberRecords()->updateExistingPivot($member->id, [
            'left_at' => today()->subDays(10),
        ]);

        $rows = $this->service->rowUsersFor($this->team, today(), collect());

        $this->assertFalse($rows->contains('id', $member->id));
    }

    public function test_rows_include_anyone_with_an_entry_that_day_even_if_never_a_member(): void
    {
        $outsider = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $entry = $this->entry($outsider, today()->toDateString(), ['plan' => '<p>Helped out</p>']);

        $rows = $this->service->rowUsersFor(
            $this->team,
            today(),
            collect([$outsider->id => $entry->load('member')]),
        );

        $this->assertTrue($rows->contains('id', $outsider->id));
    }

    public function test_rows_exclude_leads_and_viewers(): void
    {
        $this->member(User::ROLE_DEVELOPER);
        $lead = User::factory()->create(['role' => User::ROLE_TEAM_LEAD]);
        $this->team->members()->attach($lead);

        $rows = $this->service->rowUsersFor($this->team, today(), collect());

        $this->assertFalse($rows->contains('id', $lead->id), 'Only developers and QA get sheet rows.');
    }

    public function test_a_full_day_leave_clears_the_task_fields(): void
    {
        $member = $this->member();
        $entry = $this->entry($member, today()->toDateString(), [
            'plan' => '<p>Ship it</p>',
            'work_points' => 8,
        ]);

        $this->service->save($entry, [
            'leave_type' => 'sick',
            'plan' => '<p>Ship it</p>',
            'work_points' => 8,
        ], $member);

        $this->assertNull($entry->fresh()->plan);
        $this->assertNull($entry->fresh()->work_points);
    }

    public function test_a_half_day_leave_keeps_the_task_fields(): void
    {
        $member = $this->member();
        $entry = $this->service->resolveEntry([
            'team_id' => $this->team->id,
            'user_id' => $member->id,
            'date' => today()->toDateString(),
        ]);

        $this->service->save($entry, [
            'leave_type' => 'half_day',
            'plan' => '<p>Half a day</p>',
            'work_points' => 4,
        ], $member);

        $this->assertSame(4, $entry->fresh()->work_points);
        $this->assertTrue($entry->fresh()->isHalfDay());
    }

    public function test_a_member_save_never_writes_feedback(): void
    {
        $member = $this->member();
        $entry = $this->entry($member, today()->toDateString(), [
            'feedback' => '<p>Lead note</p>',
        ]);

        // Submitted *and* flagged as present — a member still may not touch it.
        $this->service->save($entry, [
            'plan' => '<p>mine</p>',
            'feedback' => '<p>I am great</p>',
        ], $member, feedbackSubmitted: true);

        $this->assertSame('<p>Lead note</p>', $entry->fresh()->feedback);
    }

    public function test_a_lead_save_writes_feedback_only_when_submitted(): void
    {
        $member = $this->member();
        $lead = User::factory()->create(['role' => User::ROLE_TEAM_LEAD]);
        $entry = $this->entry($member, today()->toDateString(), ['feedback' => '<p>Original</p>']);

        // Key absent → left alone, not nulled.
        $this->service->save($entry, ['plan' => '<p>x</p>'], $lead, feedbackSubmitted: false);
        $this->assertSame('<p>Original</p>', $entry->fresh()->feedback);

        // Key present → applied.
        $this->service->save($entry, ['feedback' => '<p>Updated</p>'], $lead, feedbackSubmitted: true);
        $this->assertSame('<p>Updated</p>', $entry->fresh()->feedback);
    }

    public function test_resolve_entry_finds_the_existing_row_rather_than_duplicating(): void
    {
        $member = $this->member();
        $existing = $this->entry($member, today()->toDateString(), ['work_points' => 3]);

        $resolved = $this->service->resolveEntry([
            'team_id' => $this->team->id,
            'user_id' => $member->id,
            'date' => today()->toDateString(),
        ]);

        $this->assertTrue($resolved->exists);
        $this->assertSame($existing->id, $resolved->id);
    }

    public function test_teams_for_puts_the_viewers_own_teams_first(): void
    {
        Team::create(['name' => 'Aardvark', 'color' => '#000000']); // alphabetically first
        $member = $this->member();

        $teams = $this->service->teamsFor($member);

        $this->assertSame($this->team->id, $teams->first()->id);
    }

    public function test_history_filters_by_team_and_range(): void
    {
        $member = $this->member();
        $other = Team::create(['name' => 'Other', 'color' => '#111111']);

        $this->entry($member, today()->subDays(20)->toDateString());
        $this->entry($member, today()->toDateString());
        TasksheetEntry::create([
            'team_id' => $other->id, 'user_id' => $member->id, 'date' => today()->toDateString(),
        ]);

        $this->assertSame(3, $this->service->history($member)->count());
        $this->assertSame(2, $this->service->history($member, $this->team->id)->count());
        $this->assertSame(
            1,
            $this->service->history($member, $this->team->id, today()->subDays(5)->toDateString())->count()
        );
    }
}
