<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\AiUsageLog;
use App\Models\Event;
use App\Models\MeetingNote;
use App\Models\Project;
use App\Models\Release;
use App\Models\ReleaseOffDay;
use App\Models\ReleasePhase;
use App\Models\Task;
use App\Models\TasksheetEntry;
use App\Models\Team;
use App\Models\User;
use App\Services\ActivityInsights;
use App\Support\LogRetention;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** "Today" is 2026-11-11, so with 3 months kept the cutoff is 2026-08-11 00:00. */
class LogRetentionTest extends TestCase
{
    use RefreshDatabase;

    private const BACKFILL = 'database/migrations/2026_11_04_120002_backfill_release_id_on_activities.php';

    protected function setUp(): void
    {
        parent::setUp();

        config(['logging.retention_months' => 3]);
        $this->travelTo(Carbon::parse('2026-11-11 10:00:00'));
    }

    private function activity(string $at, array $overrides = []): Activity
    {
        return Activity::forceCreate($overrides + [
            'description' => 'Changed something',
            'event' => 'updated',
            'subject_type' => TasksheetEntry::class,
            'subject_id' => 1,
            'created_at' => $at,
        ]);
    }

    private function usage(string $at): AiUsageLog
    {
        return AiUsageLog::forceCreate(['feature' => 'enhance', 'model' => 'claude-sonnet-5-5', 'status' => 'ok', 'created_at' => $at]);
    }

    private function failedJob(string $at): string
    {
        $uuid = (string) Str::uuid();
        DB::table('failed_jobs')->insert([
            'uuid' => $uuid, 'connection' => 'database', 'queue' => 'default',
            'payload' => '{}', 'exception' => 'Boom', 'failed_at' => $at,
        ]);

        return $uuid;
    }

    private function prune(): void
    {
        Artisan::call('model:prune', ['--model' => [Activity::class, AiUsageLog::class]]);
    }

    public function test_activity_past_the_cutoff_goes_and_the_rest_stays(): void
    {
        $old = $this->activity('2026-05-01 12:00:00');
        $justOver = $this->activity('2026-08-10 23:59:59');
        $justInside = $this->activity('2026-08-11 00:00:00');
        $recent = $this->activity('2026-11-10 12:00:00');

        $this->prune();

        $this->assertModelMissing($old);
        $this->assertModelMissing($justOver);
        $this->assertModelExists($justInside);
        $this->assertModelExists($recent);
    }

    public function test_a_release_keeps_its_history_however_old(): void
    {
        $linked = $this->activity('2026-01-01 12:00:00', ['subject_type' => Task::class, 'release_id' => 7]);
        // Rows from before release_id was filled in still describe a release.
        $release = $this->activity('2026-01-01 12:00:00', ['subject_type' => Release::class]);
        $phase = $this->activity('2026-01-01 12:00:00', ['subject_type' => ReleasePhase::class]);
        $offDay = $this->activity('2026-01-01 12:00:00', ['subject_type' => ReleaseOffDay::class]);
        $unrelated = $this->activity('2026-01-01 12:00:00', ['subject_type' => Task::class]);

        $this->prune();

        $this->assertModelExists($linked);
        $this->assertModelExists($release);
        $this->assertModelExists($phase);
        $this->assertModelExists($offDay);
        $this->assertModelMissing($unrelated);
    }

    public function test_the_backfill_links_old_rows_the_way_new_rows_are_linked(): void
    {
        $author = User::factory()->create();
        $project = Project::create(['name' => 'Shop', 'color' => '#0891b2']);
        $team = Team::create(['name' => 'Alpha', 'color' => '#4f46e5']);
        $release = Release::create([
            'project_id' => $project->id, 'team_id' => $team->id, 'name' => 'v1',
            'year' => 2026, 'quarter' => 4, 'start_date' => '2026-10-01', 'end_date' => '2026-12-31',
        ]);
        $task = Task::create(['release_id' => $release->id, 'title' => 'In the release']);
        $loose = Task::create(['title' => 'In no release']);
        $phase = $release->phases()->create(['phase' => array_key_first(Release::PHASES), 'position' => 0, 'start_date' => '2026-10-01', 'end_date' => '2026-10-02']);
        $offDay = ReleaseOffDay::create(['release_id' => $release->id, 'date' => '2026-11-11', 'reason' => 'Holiday']);
        $note = MeetingNote::create(['release_id' => $release->id, 'created_by' => $author->id, 'title' => 'Sync', 'meeting_date' => '2026-10-02', 'body' => '<div>Notes</div>']);
        $event = Event::create(['release_id' => $release->id, 'title' => 'Retro', 'type' => 'meeting', 'starts_at' => '2026-10-02 10:00:00', 'created_by' => $author->id]);
        $onTask = $task->comments()->create(['user_id' => $author->id, 'body' => 'On a task']);
        $onLoose = $loose->comments()->create(['user_id' => $author->id, 'body' => 'On a loose task']);
        $onRelease = $release->comments()->create(['user_id' => $author->id, 'body' => 'On the release']);

        $subjects = [$release, $task, $phase, $offDay, $note, $event, $onTask, $onRelease];
        $unlinked = [$loose, $onLoose, $author, $project, $team];

        Activity::query()->update(['release_id' => null]);
        $gone = $this->activity('2026-01-01 12:00:00', ['subject_type' => Task::class, 'subject_id' => 99999]);
        $other = $this->activity('2026-01-01 12:00:00', ['subject_type' => Task::class, 'subject_id' => $task->id, 'release_id' => 7]);

        (require base_path(self::BACKFILL))->up();

        $linkOf = fn ($subject) => Activity::where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())->whereKeyNot($other->id)->pluck('release_id')->unique()->all();

        foreach ($subjects as $subject) {
            $this->assertSame([$release->id], $linkOf($subject), class_basename($subject).' was not linked');
        }
        foreach ($unlinked as $subject) {
            $this->assertSame([null], $linkOf($subject), class_basename($subject).' should stay unlinked');
        }
        $this->assertNull($gone->fresh()->release_id);
        $this->assertSame(7, $other->fresh()->release_id, 'An existing link is never replaced');
    }

    public function test_ai_usage_past_the_cutoff_goes_and_the_rest_stays(): void
    {
        $old = $this->usage('2026-08-10 23:59:59');
        $inside = $this->usage('2026-08-11 00:00:00');

        $this->prune();

        $this->assertModelMissing($old);
        $this->assertModelExists($inside);
    }

    public function test_failed_jobs_past_the_cutoff_go_and_the_rest_stay(): void
    {
        $old = $this->failedJob('2026-08-10 23:59:59');
        $inside = $this->failedJob('2026-08-11 00:00:00');

        Artisan::call('queue:prune-failed', ['--hours' => LogRetention::hours()]);

        $this->assertDatabaseMissing('failed_jobs', ['uuid' => $old]);
        $this->assertDatabaseHas('failed_jobs', ['uuid' => $inside]);
    }

    public function test_rows_are_removed_in_batches(): void
    {
        foreach (range(1, 5) as $i) {
            $this->activity('2026-01-01 12:00:00');
        }

        $deletes = 0;
        DB::listen(function ($query) use (&$deletes) {
            $deletes += str_starts_with($query->sql, 'delete') ? 1 : 0;
        });

        $this->assertSame(5, (new Activity)->pruneAll(2));
        // 2 + 2 + 1, then one more that finds nothing left.
        $this->assertSame(4, $deletes);
    }

    public function test_the_retention_period_can_be_changed(): void
    {
        config(['logging.retention_months' => 6]);
        $this->assertSame('2026-05-11 00:00:00', LogRetention::cutoff()->toDateTimeString());

        $fourMonths = $this->activity('2026-07-11 12:00:00');
        $this->prune();

        $this->assertModelExists($fourMonths);
    }

    public function test_a_bad_setting_cannot_empty_a_table(): void
    {
        config(['logging.retention_months' => 0]);

        $this->assertSame('2026-10-11 00:00:00', LogRetention::cutoff()->toDateTimeString());
    }

    public function test_the_cutoff_never_cuts_into_the_90_day_view(): void
    {
        // February to May is the shortest three months: 89 days.
        foreach (['2026-05-01 10:00:00', '2026-05-31 23:00:00', '2028-05-01 00:00:00', '2026-03-01 10:00:00'] as $today) {
            $this->travelTo(Carbon::parse($today));

            $window = app(ActivityInsights::class)->window(ActivityInsights::filters(['days' => 90]));

            $this->assertTrue(LogRetention::cutoff()->lte($window['start']), "Cutoff passes the window on {$today}");
        }
    }

    public function test_the_hours_for_failed_jobs_reach_back_to_the_cutoff(): void
    {
        $this->assertTrue(now()->subHours(LogRetention::hours())->lte(LogRetention::cutoff()));
        $this->assertTrue(now()->subHours(LogRetention::hours() - 1)->gt(LogRetention::cutoff()));
    }

    public function test_the_cleanup_is_scheduled_daily(): void
    {
        $events = collect(app(Schedule::class)->events());

        $models = $events->first(fn ($e) => str_contains($e->command ?? '', 'model:prune'));
        foreach (['Activity', 'AiUsageLog', 'Task', 'TasksheetExport'] as $model) {
            $this->assertStringContainsString('App\Models\\'.$model, $models->command);
        }
        $this->assertSame('0 0 * * *', $models->expression);

        $failed = $events->first(fn ($e) => str_contains($e->command ?? '', 'queue:prune-failed'));
        $this->assertStringContainsString('--hours=', $failed->command);
        $this->assertSame('0 0 * * *', $failed->expression);
    }

    public function test_log_files_are_kept_for_the_retention_period(): void
    {
        $this->assertSame((int) env('LOG_RETENTION_MONTHS', 3) * 30, config('logging.channels.daily.days'));
    }

    public function test_the_90_day_view_stops_comparing_with_a_period_no_longer_kept(): void
    {
        $this->activity('2026-10-01 12:00:00');

        $kpis = app(ActivityInsights::class)->analysis(ActivityInsights::filters(['days' => 90]))['kpis'];

        $this->assertSame(['current' => 1, 'previous' => null, 'change' => null], $kpis['events']);
        $this->assertNull($kpis['people']['change']);
        $this->assertNull($kpis['deletions']['change']);

        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
        $this->get(route('activity.index', ['days' => 90]))->assertOk()->assertDontSee('previous 90 days');
    }

    public function test_the_30_day_view_still_compares(): void
    {
        $this->activity('2026-10-01 12:00:00');
        $this->activity('2026-11-01 12:00:00');

        $kpis = app(ActivityInsights::class)->analysis(ActivityInsights::filters(['days' => 30]))['kpis'];

        $this->assertSame(['current' => 1, 'previous' => 1, 'change' => 0], $kpis['events']);
    }

    public function test_a_longer_retention_brings_the_90_day_comparison_back(): void
    {
        config(['logging.retention_months' => 6]);
        $this->activity('2026-07-01 12:00:00');
        $this->activity('2026-10-01 12:00:00');

        $kpis = app(ActivityInsights::class)->analysis(ActivityInsights::filters(['days' => 90]))['kpis'];

        $this->assertSame(['current' => 1, 'previous' => 1, 'change' => 0], $kpis['events']);
    }
}
