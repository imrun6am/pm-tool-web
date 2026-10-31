<?php

namespace Tests\Feature\Services;

use App\Models\Note;
use App\Models\PerformanceCompetency;
use App\Models\Project;
use App\Models\QuickLink;
use App\Models\Release;
use App\Models\Task;
use App\Models\TasksheetEntry;
use App\Models\Team;
use App\Models\User;
use App\Services\ActivityInsights;
use App\Services\BoardService;
use App\Services\EventService;
use App\Services\NoteService;
use App\Services\PerformanceCompetencyService;
use App\Services\PerformanceEvaluationService;
use App\Services\QuickLinkService;
use App\Services\TaskService;
use App\Services\UserService;
use App\Support\PerformancePeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Direct tests of the remaining extracted logic. Grouped in one file because
 * each service contributes only a handful of rules worth pinning.
 */
class CollaborationServicesTest extends TestCase
{
    use RefreshDatabase;

    private function release(): Release
    {
        $project = Project::create(['name' => 'P'.uniqid(), 'color' => '#4f46e5']);
        $team = Team::create(['name' => 'T'.uniqid(), 'color' => '#0891b2']);

        return Release::create([
            'project_id' => $project->id, 'team_id' => $team->id, 'name' => 'R',
            'year' => 2026, 'quarter' => 3,
            'start_date' => '2026-07-01', 'end_date' => '2026-07-31',
        ]);
    }

    // -- TaskService ------------------------------------------------------

    public function test_a_new_task_defaults_to_the_initial_status(): void
    {
        $release = $this->release();
        $author = User::factory()->create(['role' => User::ROLE_DEVELOPER]);

        $task = app(TaskService::class)->createForRelease($release, ['title' => 'Fix login'], $author);

        $this->assertSame(TaskService::DEFAULT_STATUS, $task->status);
        $this->assertSame($author->id, $task->created_by);
        $this->assertNull($task->parent_id);
    }

    public function test_nesting_stops_at_one_level(): void
    {
        $release = $this->release();
        $author = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $service = app(TaskService::class);

        $parent = $service->createForRelease($release, ['title' => 'Parent'], $author);
        $child = $service->createSubtask($parent, ['title' => 'Child'], $author);

        $this->expectException(HttpException::class);
        $service->createSubtask($child, ['title' => 'Grandchild'], $author);
    }

    public function test_an_update_does_not_rewrite_the_creator(): void
    {
        $release = $this->release();
        $author = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $service = app(TaskService::class);

        $task = $service->createForRelease($release, ['title' => 'Original'], $author);
        $service->update($task, ['title' => 'Edited', 'status' => 'in_progress']);

        $this->assertSame('Edited', $task->fresh()->title);
        $this->assertSame($author->id, $task->fresh()->created_by);
    }

    // -- BoardService -----------------------------------------------------

    public function test_the_board_returns_every_status_column_even_when_empty(): void
    {
        $columns = app(BoardService::class)->columns();

        $this->assertSame(array_keys(Task::STATUSES), array_keys($columns));
        foreach ($columns as $cards) {
            $this->assertCount(0, $cards);
        }
    }

    public function test_a_move_sets_the_status_and_renumbers_the_column_together(): void
    {
        $release = $this->release();
        $a = Task::create(['release_id' => $release->id, 'title' => 'A', 'status' => 'todo', 'position' => 0]);
        $b = Task::create(['release_id' => $release->id, 'title' => 'B', 'status' => 'todo', 'position' => 1]);

        app(BoardService::class)->move($b, 'in_progress', [$b->id, $a->id]);

        $this->assertSame('in_progress', $b->fresh()->status);
        $this->assertSame(0, $b->fresh()->position);
        $this->assertSame(1, $a->fresh()->position);
    }

    public function test_a_move_never_reorders_subtasks(): void
    {
        $release = $this->release();
        $parent = Task::create(['release_id' => $release->id, 'title' => 'P', 'status' => 'todo', 'position' => 0]);
        $sub = Task::create([
            'release_id' => $release->id, 'parent_id' => $parent->id,
            'title' => 'S', 'status' => 'todo', 'position' => 5,
        ]);

        app(BoardService::class)->move($parent, 'done', [$sub->id]);

        // Only top-level cards are board cards; the subtask keeps its position.
        $this->assertSame(5, $sub->fresh()->position);
    }

    // -- EventService -----------------------------------------------------

    public function test_an_all_day_event_is_snapped_to_day_bounds(): void
    {
        $attributes = app(EventService::class)->attributes([
            'title' => 'Offsite', 'type' => 'meeting',
            'starts_at' => '2026-07-20 14:30:00',
            'ends_at' => '2026-07-20 16:00:00',
            'all_day' => true,
        ]);

        $this->assertSame('2026-07-20 00:00:00', $attributes['starts_at']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-20 23:59:59', $attributes['ends_at']->format('Y-m-d H:i:s'));
    }

    public function test_a_timed_event_keeps_its_times(): void
    {
        $attributes = app(EventService::class)->attributes([
            'title' => 'Standup', 'type' => 'meeting',
            'starts_at' => '2026-07-20 09:15:00',
            'ends_at' => '2026-07-20 09:30:00',
            'all_day' => false,
        ]);

        $this->assertSame('09:15:00', $attributes['starts_at']->format('H:i:s'));
        $this->assertSame('09:30:00', $attributes['ends_at']->format('H:i:s'));
    }

    public function test_the_month_window_covers_whole_weeks(): void
    {
        // July 2026 starts on a Wednesday, so the grid opens on the preceding Sunday.
        [$from, $to] = app(EventService::class)->monthWindow(2026, 7);

        $this->assertSame(Carbon::SUNDAY, $from->dayOfWeek);
        $this->assertSame(Carbon::SATURDAY, $to->dayOfWeek);
        $this->assertTrue($from->lte(Carbon::create(2026, 7, 1)));
        $this->assertTrue($to->gte(Carbon::create(2026, 7, 31)));
    }

    // -- NoteService ------------------------------------------------------

    public function test_switching_a_note_off_specific_visibility_clears_its_recipients(): void
    {
        $author = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $recipient = User::factory()->create(['role' => User::ROLE_QA]);
        $service = app(NoteService::class);

        $note = $service->create([
            'date' => '2026-07-20', 'body' => '<p>Hi</p>',
            'visibility' => Note::VISIBILITY_SPECIFIC,
        ], [$recipient->id], $author);

        $this->assertCount(1, $note->recipients);

        $service->update($note, [
            'date' => '2026-07-20', 'body' => '<p>Hi</p>',
            'visibility' => Note::VISIBILITY_PRIVATE,
        ], [$recipient->id]);

        $this->assertCount(0, $note->fresh()->recipients, 'A stale share list must not survive the switch.');
    }

    public function test_a_reversed_date_range_is_swapped_not_rejected(): void
    {
        $filters = app(NoteService::class)->normalizeFilters([
            'from' => '2026-07-31', 'to' => '2026-07-01',
        ]);

        $this->assertSame('2026-07-01', $filters['from']);
        $this->assertSame('2026-07-31', $filters['to']);
    }

    // -- QuickLinkService -------------------------------------------------

    public function test_quick_links_are_partitioned_into_own_and_shared(): void
    {
        $viewer = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $other = User::factory()->create(['role' => User::ROLE_ADMIN]);

        QuickLink::create([
            'user_id' => $viewer->id, 'label' => 'Mine',
            'url' => 'https://example.com/a', 'visibility' => QuickLink::VISIBILITY_PRIVATE,
        ]);
        QuickLink::create([
            'user_id' => $other->id, 'label' => 'Theirs',
            'url' => 'https://example.com/b', 'visibility' => QuickLink::VISIBILITY_SHARED,
        ]);

        $partitioned = app(QuickLinkService::class)->partitionedFor($viewer);

        $this->assertCount(1, $partitioned['mine']);
        $this->assertSame('Mine', $partitioned['mine']->first()->label);
        $this->assertCount(1, $partitioned['shared']);
        $this->assertSame('Theirs', $partitioned['shared']->first()->label);
    }

    public function test_a_limited_role_sees_no_shared_links(): void
    {
        $dev = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $other = User::factory()->create(['role' => User::ROLE_ADMIN]);

        QuickLink::create([
            'user_id' => $other->id, 'label' => 'Shared',
            'url' => 'https://example.com', 'visibility' => QuickLink::VISIBILITY_SHARED,
        ]);

        $partitioned = app(QuickLinkService::class)->partitionedFor($dev);

        $this->assertCount(0, $partitioned['shared']);
    }

    // -- PerformanceEvaluationService -------------------------------------

    public function test_leave_flags_include_the_periods_final_day(): void
    {
        $team = Team::create(['name' => 'Core', 'color' => '#0891b2']);
        $member = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $team->members()->attach($member);

        // Leave booked on the last day of the week — the day a plain 'Y-m-d'
        // upper bound would have excluded.
        $week = PerformancePeriod::week(today());
        TasksheetEntry::create([
            'team_id' => $team->id, 'user_id' => $member->id,
            'date' => $week['end']->toDateString(), 'leave_type' => 'casual',
        ]);

        $flags = app(PerformanceEvaluationService::class)
            ->leaveFlags($team, collect([$member]), $week);

        $this->assertTrue($flags[$member->id]);
    }

    public function test_a_member_who_worked_at_all_is_not_flagged_as_on_leave(): void
    {
        $team = Team::create(['name' => 'Core', 'color' => '#0891b2']);
        $member = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $team->members()->attach($member);

        $week = PerformancePeriod::week(today());
        TasksheetEntry::create([
            'team_id' => $team->id, 'user_id' => $member->id,
            'date' => $week['start']->toDateString(), 'leave_type' => 'casual',
        ]);
        TasksheetEntry::create([
            'team_id' => $team->id, 'user_id' => $member->id,
            'date' => $week['end']->toDateString(), 'work_points' => 5,
        ]);

        $flags = app(PerformanceEvaluationService::class)
            ->leaveFlags($team, collect([$member]), $week);

        $this->assertFalse($flags[$member->id]);
    }

    public function test_blank_grid_cells_are_dropped(): void
    {
        $rated = app(PerformanceEvaluationService::class)->ratedCells([
            1 => 4, 2 => null, 3 => '', 4 => '5',
        ]);

        $this->assertSame([1 => 4, 4 => 5], $rated);
    }

    public function test_the_grid_only_offers_competencies_of_the_requested_cadence(): void
    {
        $service = app(PerformanceEvaluationService::class);

        PerformanceCompetency::create([
            'key' => 'daily-one', 'name' => 'Daily', 'category' => 'technical',
            'role_scope' => 'both', 'cadence' => PerformanceCompetency::CADENCE_DAILY,
            'weight' => 1, 'active' => true, 'position' => 0,
        ]);
        PerformanceCompetency::create([
            'key' => 'weekly-one', 'name' => 'Weekly', 'category' => 'technical',
            'role_scope' => 'both', 'cadence' => PerformanceCompetency::CADENCE_WEEKLY,
            'weight' => 1, 'active' => true, 'position' => 1,
        ]);

        $this->assertSame(['Daily'], $service->competenciesFor('daily')->pluck('name')->all());
        $this->assertSame(['Weekly'], $service->competenciesFor('weekly')->pluck('name')->all());
    }

    public function test_an_inactive_competency_drops_off_the_grid(): void
    {
        PerformanceCompetency::create([
            'key' => 'retired', 'name' => 'Retired', 'category' => 'technical',
            'role_scope' => 'both', 'cadence' => PerformanceCompetency::CADENCE_WEEKLY,
            'weight' => 1, 'active' => false, 'position' => 0,
        ]);

        $this->assertCount(0, app(PerformanceEvaluationService::class)->competenciesFor('weekly'));
    }

    // -- PerformanceCompetencyService -------------------------------------

    public function test_competency_keys_are_unique(): void
    {
        $service = app(PerformanceCompetencyService::class);

        $attributes = [
            'name' => 'Attention to Detail', 'category' => 'technical',
            'role_scope' => 'qa', 'cadence' => 'daily', 'weight' => 3, 'active' => true,
        ];

        $this->assertSame('attention-to-detail', $service->create($attributes)->key);
        $this->assertSame('attention-to-detail-2', $service->create($attributes)->key);
        $this->assertSame('attention-to-detail-3', $service->create($attributes)->key);
    }

    public function test_a_competency_key_is_immutable(): void
    {
        $service = app(PerformanceCompetencyService::class);

        $competency = $service->create([
            'name' => 'Code Quality', 'category' => 'technical',
            'role_scope' => 'both', 'cadence' => 'weekly', 'weight' => 5, 'active' => true,
        ]);

        $service->update($competency, ['name' => 'Renamed', 'key' => 'hijacked']);

        $this->assertSame('code-quality', $competency->fresh()->key);
        $this->assertSame('Renamed', $competency->fresh()->name);
    }

    // -- ActivityInsights -------------------------------------------------

    public function test_activity_totals_count_each_event_kind(): void
    {
        $author = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($author);

        $release = $this->release();       // created
        $release->update(['name' => 'X']); // updated

        $totals = app(ActivityInsights::class)->totals();

        $this->assertGreaterThan(0, $totals['created']);
        $this->assertGreaterThan(0, $totals['updated']);
        $this->assertSame(
            $totals['created'] + $totals['updated'] + $totals['deleted'],
            $totals['total']
        );
    }

    public function test_the_activity_trend_is_a_continuous_axis(): void
    {
        $trend = app(ActivityInsights::class)->trend();

        $this->assertCount(ActivityInsights::TREND_DAYS, $trend);
        $this->assertSame(today()->toDateString(), end($trend)['date']->toDateString());
        // Quiet days are filled with zero rather than omitted.
        $this->assertSame(0, $trend[0]['count']);
    }

    // -- UserService ------------------------------------------------------

    public function test_the_last_active_admin_is_protected(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $service = app(UserService::class);

        $this->assertTrue($service->isLastActiveAdmin($admin));
        $this->assertFalse($service->canChangeRoleTo($admin, User::ROLE_DEVELOPER));

        // A second admin removes the constraint.
        User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->assertFalse($service->isLastActiveAdmin($admin));
        $this->assertTrue($service->canChangeRoleTo($admin, User::ROLE_DEVELOPER));
    }

    public function test_a_deactivated_admin_does_not_count_toward_the_last_admin_check(): void
    {
        $active = User::factory()->create(['role' => User::ROLE_ADMIN]);
        User::factory()->create(['role' => User::ROLE_ADMIN, 'deactivated_at' => now()]);

        $this->assertTrue(app(UserService::class)->isLastActiveAdmin($active));
    }

    public function test_a_blank_password_is_dropped_on_update(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $original = $user->password;

        app(UserService::class)->update($user, ['name' => 'Renamed', 'password' => '']);

        $this->assertSame('Renamed', $user->fresh()->name);
        $this->assertSame($original, $user->fresh()->password);
    }

    public function test_soft_deleting_a_user_ends_their_memberships_and_revokes_tokens(): void
    {
        $team = Team::create(['name' => 'Core', 'color' => '#0891b2']);
        $user = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $team->members()->attach($user);
        $user->createToken('Desktop');

        app(UserService::class)->softDelete($user);

        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertSame(0, $team->members()->count());
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_deactivating_a_user_revokes_their_tokens(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $user->createToken('Desktop');

        app(UserService::class)->deactivate($user);

        $this->assertFalse($user->fresh()->isActive());
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_user_stats_describe_the_whole_directory(): void
    {
        User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        User::factory()->create(['role' => User::ROLE_QA]);
        User::factory()->create(['role' => User::ROLE_ADMIN, 'deactivated_at' => now()]);

        $stats = app(UserService::class)->stats();

        $this->assertSame(3, $stats['total']);
        $this->assertSame(2, $stats['active']);
        $this->assertSame(1, $stats['inactive']);
        $this->assertSame(2, $stats['engineers']);
        $this->assertCount(count(User::ROLES), $stats['roleDistribution']);
    }
}
