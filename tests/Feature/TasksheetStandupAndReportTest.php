<?php

namespace Tests\Feature;

use App\Models\TasksheetEntry;
use App\Models\Team;
use App\Models\User;
use App\Services\TasksheetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TasksheetStandupAndReportTest extends TestCase
{
    use RefreshDatabase;

    private function team(string $name = 'Core'): Team
    {
        return Team::create(['name' => $name, 'color' => '#0891b2']);
    }

    private function member(Team $team, string $role = User::ROLE_DEVELOPER): User
    {
        $user = User::factory()->create(['role' => $role]);
        $team->members()->attach($user);

        return $user;
    }

    /**
     * `standup_attended` is intentionally not fillable, so it cannot be mass
     * assigned here either — it is set explicitly after creation, the same way
     * the service's authorized write does it.
     */
    private function entry(Team $team, User $user, array $attrs = []): TasksheetEntry
    {
        $attended = $attrs['standup_attended'] ?? null;
        unset($attrs['standup_attended']);

        $entry = TasksheetEntry::create(array_merge([
            'team_id' => $team->id, 'user_id' => $user->id, 'date' => today()->toDateString(),
        ], $attrs));

        if ($attended !== null) {
            $entry->standup_attended = $attended;
            $entry->save();
        }

        return $entry;
    }

    /** @return array<string, mixed> */
    private function target(Team $team, User $user, ?string $date = null): array
    {
        return ['team_id' => $team->id, 'user_id' => $user->id, 'date' => $date ?? today()->toDateString()];
    }

    /** Names shown on the day's sheet for the given query. */
    private function listedMembers(User $viewer, array $query): array
    {
        $response = $this->actingAs($viewer)->get(route('tasksheet.index', $query))->assertOk();

        return collect($response->viewData('rowUsers'))->pluck('name')->sort()->values()->all();
    }

    // ---------------------------------------------------------- recording

    public function test_lead_marks_attended_and_absent(): void
    {
        $team = $this->team();
        $dev = $this->member($team);
        $lead = User::factory()->create(['role' => User::ROLE_TEAM_LEAD]);

        $this->actingAs($lead)->post(route('tasksheet.standup'), $this->target($team, $dev) + ['attended' => 1])
            ->assertRedirect();
        $this->assertTrue(TasksheetEntry::first()->attendedStandup());

        $this->actingAs($lead)->post(route('tasksheet.standup'), $this->target($team, $dev) + ['attended' => 0])
            ->assertRedirect();
        $this->assertTrue(TasksheetEntry::first()->missedStandup());
    }

    public function test_rows_are_unmarked_until_someone_marks_them(): void
    {
        $team = $this->team();
        $dev = $this->member($team);
        $entry = $this->entry($team, $dev, ['plan' => 'Work']);

        $this->assertTrue($entry->isStandupUnmarked());
        $this->assertFalse($entry->attendedStandup());
        $this->assertFalse($entry->missedStandup());
    }

    public function test_marking_a_member_with_no_row_creates_one_that_is_still_unfilled(): void
    {
        $team = $this->team();
        $dev = $this->member($team);
        $lead = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->assertSame(0, TasksheetEntry::count());

        $this->actingAs($lead)->post(route('tasksheet.standup'), $this->target($team, $dev) + ['attended' => 1])
            ->assertRedirect();

        $entry = TasksheetEntry::first();
        $this->assertNotNull($entry);
        $this->assertTrue($entry->attendedStandup());
        // Attendance is not task content — the row still needs filling.
        $this->assertSame(0, $entry->filledFieldCount());
        $this->assertFalse($entry->isFullyFilled());
    }

    public function test_clearing_returns_the_row_to_unmarked(): void
    {
        $team = $this->team();
        $dev = $this->member($team);
        $lead = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($lead)->post(route('tasksheet.standup'), $this->target($team, $dev) + ['attended' => 1]);
        $this->actingAs($lead)->post(route('tasksheet.standup'), $this->target($team, $dev) + ['attended' => '']);

        $this->assertTrue(TasksheetEntry::first()->isStandupUnmarked());
    }

    // ------------------------------------------------------ authorization

    public function test_a_member_cannot_verify_their_own_attendance(): void
    {
        $team = $this->team();
        $dev = $this->member($team);

        $this->actingAs($dev)->post(route('tasksheet.standup'), $this->target($team, $dev) + ['attended' => 1])
            ->assertForbidden();

        $this->assertSame(0, TasksheetEntry::whereNotNull('standup_attended')->count());
    }

    public function test_a_members_row_save_can_neither_forge_nor_clear_attendance(): void
    {
        $team = $this->team();
        $dev = $this->member($team);
        $lead = User::factory()->create(['role' => User::ROLE_ADMIN]);

        // Forging on a fresh row: the field is not fillable, so it stays null.
        $this->actingAs($dev)->put(route('tasksheet.entries.upsert'), $this->target($team, $dev) + [
            'plan' => 'Work', 'standup_attended' => 1,
        ])->assertRedirect();
        $this->assertTrue(TasksheetEntry::first()->isStandupUnmarked());

        // A lead's mark survives the member's next save.
        $this->actingAs($lead)->post(route('tasksheet.standup'), $this->target($team, $dev) + ['attended' => 1]);
        $this->actingAs($dev)->put(route('tasksheet.entries.upsert'), $this->target($team, $dev) + [
            'plan' => 'More work', 'standup_attended' => 0,
        ])->assertRedirect();

        $this->assertTrue(TasksheetEntry::first()->attendedStandup());
    }

    public function test_toggling_attendance_leaves_task_content_untouched(): void
    {
        $team = $this->team();
        $dev = $this->member($team);
        $lead = User::factory()->create(['role' => User::ROLE_TECH_LEAD]);
        $this->entry($team, $dev, ['plan' => 'Ship the thing', 'work_points' => 7]);

        $this->actingAs($lead)->post(route('tasksheet.standup'), $this->target($team, $dev) + ['attended' => 1])
            ->assertRedirect();

        $entry = TasksheetEntry::first();
        $this->assertSame('Ship the thing', strip_tags((string) $entry->plan));
        $this->assertSame(7, $entry->work_points);
        $this->assertTrue($entry->attendedStandup());
    }

    // -------------------------------------------------------- full-day leave

    public function test_a_full_day_leave_row_refuses_attendance(): void
    {
        $team = $this->team();
        $dev = $this->member($team);
        $lead = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->entry($team, $dev, ['leave_type' => 'sick']);

        $this->actingAs($lead)->post(route('tasksheet.standup'), $this->target($team, $dev) + ['attended' => 1])
            ->assertStatus(422);

        $this->assertTrue(TasksheetEntry::first()->isStandupUnmarked());
    }

    public function test_becoming_a_full_day_leave_clears_a_recorded_attendance(): void
    {
        $team = $this->team();
        $dev = $this->member($team);
        $lead = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($lead)->post(route('tasksheet.standup'), $this->target($team, $dev) + ['attended' => 0]);
        $this->assertTrue(TasksheetEntry::first()->missedStandup());

        $this->actingAs($lead)->put(route('tasksheet.entries.upsert'), $this->target($team, $dev) + [
            'leave_type' => 'casual',
        ])->assertRedirect();

        $this->assertTrue(TasksheetEntry::first()->isStandupUnmarked());
    }

    public function test_half_day_leave_still_accepts_attendance(): void
    {
        $team = $this->team();
        $dev = $this->member($team);
        $lead = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->entry($team, $dev, ['leave_type' => 'half_day', 'plan' => 'Half a day of work']);

        $this->actingAs($lead)->post(route('tasksheet.standup'), $this->target($team, $dev) + ['attended' => 1])
            ->assertRedirect();

        $this->assertTrue(TasksheetEntry::first()->attendedStandup());
    }

    // ------------------------------------------------------------- filters

    public function test_daily_sheet_filters_by_attendance_including_unmarked(): void
    {
        $team = $this->team();
        $lead = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $there = $this->member($team);
        $missing = $this->member($team);
        $unknown = $this->member($team);

        $there->update(['name' => 'Ada']);
        $missing->update(['name' => 'Bea']);
        $unknown->update(['name' => 'Cyd']);

        $this->entry($team, $there, ['standup_attended' => true]);
        $this->entry($team, $missing, ['standup_attended' => false]);
        // Cyd has no row at all — still belongs under "not yet marked".

        $base = ['team' => $team->id, 'date' => today()->toDateString()];

        $this->assertSame(['Ada'], $this->listedMembers($lead, $base + ['attendance' => 'attended']));
        $this->assertSame(['Bea'], $this->listedMembers($lead, $base + ['attendance' => 'missed']));
        $this->assertSame(['Cyd'], $this->listedMembers($lead, $base + ['attendance' => 'unmarked']));
        $this->assertSame(['Ada', 'Bea', 'Cyd'], $this->listedMembers($lead, $base));
    }

    public function test_daily_sheet_filters_by_fill_status_and_member(): void
    {
        $team = $this->team();
        $lead = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $full = $this->member($team);
        $partial = $this->member($team);
        $none = $this->member($team);

        $full->update(['name' => 'Ada']);
        $partial->update(['name' => 'Bea']);
        $none->update(['name' => 'Cyd']);

        $this->entry($team, $full, array_fill_keys(TasksheetEntry::TASK_FIELDS, 1) + ['plan' => 'p', 'result' => 'r', 'comment' => 'c', 'tickets' => 't']);
        $this->entry($team, $partial, ['plan' => 'Only a plan']);

        $base = ['team' => $team->id, 'date' => today()->toDateString()];

        $this->assertSame(['Ada'], $this->listedMembers($lead, $base + ['fill' => 'complete']));
        $this->assertSame(['Bea'], $this->listedMembers($lead, $base + ['fill' => 'partial']));
        $this->assertSame(['Cyd'], $this->listedMembers($lead, $base + ['fill' => 'empty']));
        $this->assertSame(['Bea'], $this->listedMembers($lead, $base + ['member' => $partial->id]));
    }

    public function test_daily_sheet_filters_by_leave_and_combine(): void
    {
        $team = $this->team();
        $lead = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $working = $this->member($team);
        $off = $this->member($team);

        $working->update(['name' => 'Ada']);
        $off->update(['name' => 'Bea']);

        $this->entry($team, $working, ['plan' => 'Work', 'standup_attended' => true]);
        $this->entry($team, $off, ['leave_type' => 'sick']);

        $base = ['team' => $team->id, 'date' => today()->toDateString()];

        $this->assertSame(['Bea'], $this->listedMembers($lead, $base + ['leave' => 'any']));
        $this->assertSame(['Ada'], $this->listedMembers($lead, $base + ['leave' => 'working']));
        // Combined: working AND attended.
        $this->assertSame(['Ada'], $this->listedMembers($lead, $base + ['leave' => 'working', 'attendance' => 'attended']));
        $this->assertSame([], $this->listedMembers($lead, $base + ['leave' => 'any', 'attendance' => 'attended']));
    }

    public function test_filtering_the_sheet_does_not_change_the_totals(): void
    {
        $team = $this->team();
        $lead = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $a = $this->member($team);
        $this->member($team);

        $this->entry($team, $a, ['plan' => 'Work', 'standup_attended' => true]);

        $response = $this->actingAs($lead)->get(route('tasksheet.index', [
            'team' => $team->id, 'date' => today()->toDateString(), 'attendance' => 'attended',
        ]))->assertOk();

        // One row listed, but the roster behind the stat tiles is still both.
        $this->assertCount(1, $response->viewData('rowUsers'));
        $this->assertCount(2, $response->viewData('allRowUsers'));
    }

    public function test_history_filters_match_the_daily_sheet_meanings(): void
    {
        $team = $this->team();
        $lead = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $dev = $this->member($team);

        $this->entry($team, $dev, ['date' => '2026-08-03', 'standup_attended' => true, 'plan' => 'p']);
        $this->entry($team, $dev, ['date' => '2026-08-04', 'standup_attended' => false, 'plan' => 'p']);
        $this->entry($team, $dev, ['date' => '2026-08-05', 'plan' => 'p']);

        $dates = function (array $query) use ($lead, $dev) {
            $response = $this->actingAs($lead)->get(route('tasksheet.user', [$dev, ...$query]))->assertOk();

            return collect($response->viewData('entries'))->map(fn ($e) => $e->date->toDateString())->sort()->values()->all();
        };

        $this->assertSame(['2026-08-03'], $dates(['attendance' => 'attended']));
        $this->assertSame(['2026-08-04'], $dates(['attendance' => 'missed']));
        $this->assertSame(['2026-08-05'], $dates(['attendance' => 'unmarked']));
        // Partial: some task fields filled, not all — true of all three rows.
        $this->assertCount(3, $dates(['fill' => 'partial']));
    }

    public function test_history_fill_complete_does_not_drop_normal_working_rows(): void
    {
        $team = $this->team();
        $lead = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $dev = $this->member($team);

        // Every task field filled and no leave_type at all — a plain working day.
        $this->entry($team, $dev, [
            'date' => '2026-08-03',
            'plan' => 'p', 'result' => 'r', 'comment' => 'c', 'tickets' => 't',
            'work_points' => 1, 'ticket_count' => 1, 'ticket_points' => 1,
        ]);

        $response = $this->actingAs($lead)->get(route('tasksheet.user', [$dev, 'fill' => 'complete']))->assertOk();

        // A naive `whereNotIn('leave_type', …)` would drop this row, because
        // SQL evaluates `NULL NOT IN (…)` as NULL rather than true.
        $this->assertCount(1, $response->viewData('entries'));
    }

    // -------------------------------------------------------------- reports

    public function test_lead_downloads_a_pdf_for_a_day_a_range_and_a_member(): void
    {
        $team = $this->team();
        $lead = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $dev = $this->member($team);
        $this->entry($team, $dev, ['date' => '2026-08-03', 'plan' => 'Work']);

        foreach ([
            ['team' => $team->id, 'date' => '2026-08-03'],
            ['team' => $team->id, 'from' => '2026-08-01', 'to' => '2026-08-31'],
            ['member' => $dev->id, 'from' => '2026-08-01', 'to' => '2026-08-31'],
        ] as $query) {
            $response = $this->actingAs($lead)->get(route('tasksheet.report', $query))->assertOk();

            $this->assertSame('application/pdf', $response->headers->get('content-type'));
            $this->assertStringStartsWith('%PDF', $response->getContent());
        }
    }

    public function test_the_daily_sheet_offers_a_team_wide_date_range_export(): void
    {
        $team = $this->team();
        $lead = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $dev = $this->member($team);
        $this->entry($team, $dev, ['date' => today()->toDateString(), 'plan' => 'Work']);

        // The sheet must expose the range itself — its own view is a single day,
        // so without these inputs a team-wide span is unreachable from the UI.
        $this->actingAs($lead)
            ->get(route('tasksheet.index', ['team' => $team->id, 'date' => today()->toDateString()]))
            ->assertOk()
            ->assertSee('name="from"', false)
            ->assertSee('name="to"', false)
            ->assertSee(route('tasksheet.report'), false);
    }

    public function test_a_team_range_export_spans_more_than_the_viewed_day(): void
    {
        $team = $this->team();
        $lead = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $dev = $this->member($team);

        $this->entry($team, $dev, ['date' => '2026-08-03', 'plan' => 'Monday work']);
        $this->entry($team, $dev, ['date' => '2026-08-05', 'plan' => 'Wednesday work']);

        $tasksheet = app(TasksheetService::class);

        // What the popover submits: team + from/to, no single `date`.
        [$from, $to] = $tasksheet->reportRange(['from' => '2026-08-01', 'to' => '2026-08-31']);
        $this->assertCount(2, $tasksheet->reportRows($team->id, null, $from, $to, $tasksheet->parseFilters([])));

        // A reversed range is swapped rather than returning nothing.
        [$from, $to] = $tasksheet->reportRange(['from' => '2026-08-31', 'to' => '2026-08-01']);
        $this->assertSame(['2026-08-01', '2026-08-31'], [$from, $to]);

        $this->actingAs($lead)->get(route('tasksheet.report', [
            'team' => $team->id, 'from' => '2026-08-01', 'to' => '2026-08-31',
        ]))->assertOk();
    }

    public function test_a_member_cannot_pull_a_team_report_but_can_pull_their_own(): void
    {
        $team = $this->team();
        $dev = $this->member($team);
        $other = $this->member($team);

        $this->actingAs($dev)->get(route('tasksheet.report', ['team' => $team->id]))->assertForbidden();
        $this->actingAs($dev)->get(route('tasksheet.report', ['member' => $other->id]))->assertForbidden();
        $this->actingAs($dev)->get(route('tasksheet.report', ['member' => $dev->id]))->assertOk();
    }

    /**
     * Asserted against the rendered report template rather than the PDF bytes:
     * dompdf subsets the embedded font, so the text is not searchable in the
     * output — a `assertStringNotContainsString` on the binary would pass no
     * matter what the document actually said.
     */
    public function test_feedback_is_withheld_from_a_members_own_report_but_present_for_a_lead(): void
    {
        $team = $this->team();
        $lead = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $dev = $this->member($team);
        $this->entry($team, $dev, [
            'date' => today()->toDateString(), 'plan' => 'Work', 'feedback' => 'Needs to speak up more',
        ]);

        $tasksheet = app(TasksheetService::class);
        $today = today()->toDateString();
        $render = fn (User $viewer) => view(
            'tasksheet.report',
            $tasksheet->reportData($team->id, $dev->id, $today, $today, $tasksheet->parseFilters([]), $viewer)
        )->render();

        // The member's own export must not become the read path the screen denies.
        $ownHtml = $render($dev);
        $this->assertStringNotContainsString('Needs to speak up more', $ownHtml);
        $this->assertStringNotContainsString('Feedback', $ownHtml);
        // …but it is genuinely their report, so the rest of the row is there.
        $this->assertStringContainsString('Work', $ownHtml);

        $leadHtml = $render($lead);
        $this->assertStringContainsString('Needs to speak up more', $leadHtml);
        $this->assertStringContainsString('Feedback', $leadHtml);

        // Both may fetch this URL; only what it contains differs.
        $this->actingAs($dev)->get(route('tasksheet.report', ['member' => $dev->id, 'date' => $today]))->assertOk();
        $this->actingAs($lead)->get(route('tasksheet.report', ['member' => $dev->id, 'date' => $today]))->assertOk();
    }

    public function test_the_report_honours_the_active_filters(): void
    {
        $team = $this->team();
        $lead = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $dev = $this->member($team);

        $this->entry($team, $dev, ['date' => '2026-08-03', 'plan' => 'Kept row', 'standup_attended' => false]);
        $this->entry($team, $dev, ['date' => '2026-08-04', 'plan' => 'Dropped row', 'standup_attended' => true]);

        $tasksheet = app(TasksheetService::class);

        $rows = $tasksheet->reportRows(
            $team->id, null, '2026-08-01', '2026-08-31',
            $tasksheet->parseFilters(['attendance' => 'missed']),
        );

        $this->assertCount(1, $rows);
        $this->assertSame('2026-08-03', $rows->first()->date->toDateString());
    }

    public function test_api_standup_toggle_and_report_mirror_the_web(): void
    {
        $team = $this->team();
        $lead = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $dev = $this->member($team);

        $this->actingAs($lead, 'sanctum')
            ->postJson('/api/v1/tasksheet/standup', $this->target($team, $dev) + ['attended' => true])
            ->assertOk()
            ->assertJsonPath('data.standup_attended', true)
            ->assertJsonPath('data.is_standup_unmarked', false);

        $this->actingAs($dev, 'sanctum')
            ->postJson('/api/v1/tasksheet/standup', $this->target($team, $dev) + ['attended' => false])
            ->assertForbidden();

        $this->actingAs($lead, 'sanctum')
            ->get('/api/v1/tasksheet/report?team='.$team->id.'&date='.today()->toDateString())
            ->assertOk();
    }
}
