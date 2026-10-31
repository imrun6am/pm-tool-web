<?php

namespace Tests\Feature;

use App\Jobs\AnalyseKpiMember;
use App\Models\KpiReport;
use App\Models\KpiReportMember;
use App\Models\TasksheetEntry;
use App\Models\Team;
use App\Models\User;
use App\Services\Kpi\KpiAnalyzer;
use App\Services\Kpi\KpiReviewService;
use App\Services\Kpi\MissingApiKeyException;
use Illuminate\Bus\PendingBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

class KpiReportTest extends TestCase
{
    use RefreshDatabase;

    private const FROM = '2026-08-01';

    private const TO = '2026-08-31';

    /** @var list<array<string, mixed>> inputs the fake analyzer received */
    private array $analysed = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config(['services.anthropic.key' => 'test-key']);

        // Every planned task is completed, plus one added task per day that is
        // only completed when the result mentions "done".
        $this->app->instance(KpiAnalyzer::class, new class($this->analysed) implements KpiAnalyzer
        {
            public function __construct(private array &$seen) {}

            public function analyse(array $input): array
            {
                if (str_contains($input['entries'][0]['plan'] ?? '', 'explode')) {
                    throw new MissingApiKeyException('boom');
                }
                $this->seen[] = $input;

                return [
                    'days' => array_map(fn ($e) => [
                        'date' => $e['date'], 'team' => $e['team'], 'planned' => 2, 'added' => 1,
                        'completed' => str_contains($e['result'] ?? '', 'done') ? 3 : 2,
                    ], $input['entries']),
                    'months' => array_map(fn ($ym) => [
                        'month' => $ym, 'feedback' => "Feedback $ym", 'strengths' => 'Strong', 'improvement_areas' => 'Tests', 'improvement_action' => 'Pairing',
                    ], $input['months']),
                    'overall' => ['feedback' => 'Overall feedback', 'strengths' => 'S', 'improvement_areas' => 'I', 'improvement_action' => 'A'],
                    'usage' => ['input_tokens' => 100, 'output_tokens' => 50],
                ];
            }
        });
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    /** @return array{0: Team, 1: User, 2: User, 3: User} team, lead, developer, qa */
    private function team(): array
    {
        $lead = User::factory()->create(['role' => User::ROLE_DEVELOPER, 'name' => 'Lead Dev']);
        $team = Team::create(['name' => 'Core', 'color' => '#0891b2', 'team_lead_id' => $lead->id]);
        $dev = User::factory()->create(['role' => User::ROLE_DEVELOPER, 'name' => 'Dana Dev']);
        $qa = User::factory()->create(['role' => User::ROLE_QA, 'name' => 'Quinn QA']);
        $team->members()->attach([$lead->id, $dev->id, $qa->id]);

        return [$team, $lead, $dev, $qa];
    }

    private function entry(Team $team, User $user, string $date, array $attrs = []): TasksheetEntry
    {
        return TasksheetEntry::create(array_merge([
            'team_id' => $team->id, 'user_id' => $user->id, 'date' => $date,
            'plan' => '<div>- Build login<br>- Fix bug</div>', 'result' => '<div>- Built login, done</div>',
        ], $attrs));
    }

    /** @return list<list<mixed>> rows of the named sheet */
    private function sheet(string $path, string $name): array
    {
        $reader = new Reader;
        $reader->open($path);
        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            if ($sheet->getName() === $name) {
                foreach ($sheet->getRowIterator() as $row) {
                    $rows[] = $row->toArray();
                }
            }
        }
        $reader->close();

        return $rows;
    }

    public function test_only_admins_can_reach_kpi_reports(): void
    {
        foreach ([User::ROLE_CTO, User::ROLE_TECH_LEAD, User::ROLE_TEAM_LEAD, User::ROLE_DEVELOPER, User::ROLE_QA, User::ROLE_VIEWER] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get(route('kpi-reports.index'))->assertForbidden();
            $this->actingAs($user)->post(route('kpi-reports.store'), ['from' => self::FROM, 'to' => self::TO, 'format' => 'xlsx', 'scope' => 'all'])->assertForbidden();
        }

        $this->actingAs($this->admin())->get(route('kpi-reports.index'))->assertOk()->assertSee('KPI reports');
        $this->assertSame(0, KpiReport::count());
    }

    public function test_menu_item_is_shown_to_admins_only(): void
    {
        $this->actingAs($this->admin())->get(route('dashboard'))->assertSee(route('kpi-reports.index'));
        $lead = User::factory()->create(['role' => User::ROLE_CTO]);
        $this->actingAs($lead)->get(route('dashboard'))->assertDontSee(route('kpi-reports.index'));
    }

    public function test_store_queues_one_job_per_scored_member(): void
    {
        Bus::fake();
        [$team, $lead, $dev, $qa] = $this->team();
        foreach ([$lead, $dev, $qa] as $user) {
            $this->entry($team, $user, '2026-08-03');
        }

        $this->actingAs($this->admin())
            ->post(route('kpi-reports.store'), ['from' => self::FROM, 'to' => self::TO, 'format' => 'xlsx', 'scope' => 'all'])
            ->assertRedirect(route('kpi-reports.index'));

        $report = KpiReport::sole();
        $this->assertSame(2, $report->members_total);
        $this->assertEqualsCanonicalizing([$dev->id, $qa->id], $report->members()->pluck('user_id')->all());
        Bus::assertBatched(fn (PendingBatch $batch) => $batch->jobs->count() === 2
            && $batch->jobs->every(fn ($job) => $job instanceof AnalyseKpiMember));
    }

    public function test_excel_report_is_built_from_the_tasksheet(): void
    {
        [$team, , $dev, $qa] = $this->team();
        $this->entry($team, $dev, '2026-08-03');
        $this->entry($team, $dev, '2026-08-04', ['result' => '<div>- Login in progress</div>']);
        $this->entry($team, $dev, '2026-08-05', ['leave_type' => 'sick', 'plan' => null, 'result' => null]);
        $this->entry($team, $qa, '2026-08-03');

        $this->actingAs($this->admin())
            ->post(route('kpi-reports.store'), ['from' => self::FROM, 'to' => self::TO, 'format' => 'xlsx', 'scope' => 'all'])
            ->assertRedirect();

        $report = KpiReport::sole();
        $this->assertSame(KpiReport::STATUS_COMPLETED, $report->status);
        $this->assertSame(2, $report->members_done);
        Storage::disk('local')->assertExists($report->file_path);

        // Full-day leave never reaches the analyzer.
        $devInput = collect($this->analysed)->firstWhere('role', User::ROLE_DEVELOPER);
        $this->assertCount(2, $devInput['entries']);

        $rows = $this->sheet(Storage::disk('local')->path($report->file_path), 'Period');
        $this->assertSame('Team Member', $rows[0][0]);
        $dana = collect($rows)->first(fn ($r) => $r[0] === 'Dana Dev');
        // working days, leave days, KPI / Task, target, actual, completion
        $this->assertSame([2, 1, 6, '', 5], array_slice($dana, 3, 5));
        $this->assertEqualsWithDelta(0.833, $dana[8], 0.001);
        $this->assertSame('Overall feedback', $dana[9]);
        $this->assertSame('Satisfactory', $dana[13]);
        $this->assertSame('Mostly achieved', $dana[15]);
        $this->assertNotContains('Lead Dev', array_column($rows, 0));

        $august = $this->sheet(Storage::disk('local')->path($report->file_path), 'Aug 2026');
        $this->assertSame('Feedback 2026-08', collect($august)->first(fn ($r) => $r[0] === 'Dana Dev')[9]);
        $this->assertCount(4, $this->sheet(Storage::disk('local')->path($report->file_path), 'Daily counts'));

        $this->actingAs($this->admin())->get(route('kpi-reports.download', $report))
            ->assertOk()->assertDownload('kpi-review_all-teams_2026-08-01_2026-08-31.xlsx');
    }

    public function test_a_member_on_two_teams_counts_each_date_once(): void
    {
        [$team, , $dev] = $this->team();
        $other = Team::create(['name' => 'Mobile', 'color' => '#7c3aed']);
        $other->members()->attach($dev);
        $this->entry($team, $dev, '2026-08-03');
        $this->entry($other, $dev, '2026-08-03');

        $this->actingAs($this->admin())
            ->post(route('kpi-reports.store'), ['from' => self::FROM, 'to' => self::TO, 'format' => 'xlsx', 'scope' => 'all']);

        $report = KpiReport::sole();
        $dana = collect($this->sheet(Storage::disk('local')->path($report->file_path), 'Period'))->first(fn ($r) => $r[0] === 'Dana Dev');
        $this->assertSame('Core, Mobile', $dana[2]);
        $this->assertSame(1, $dana[3]);   // one working day…
        $this->assertSame(6, $dana[5]);   // …but both teams' tasks count
    }

    public function test_team_report_covers_only_that_team(): void
    {
        [$team, , $dev, $qa] = $this->team();
        $other = Team::create(['name' => 'Mobile', 'color' => '#7c3aed']);
        $outsider = User::factory()->create(['role' => User::ROLE_DEVELOPER, 'name' => 'Omar Other']);
        $other->members()->attach([$dev->id, $outsider->id]);
        $this->entry($team, $dev, '2026-08-03');
        $this->entry($other, $dev, '2026-08-04');      // Dana's other-team row: excluded
        $this->entry($team, $qa, '2026-08-03');
        $this->entry($other, $outsider, '2026-08-03'); // not on Core: excluded

        $this->actingAs($this->admin())->post(route('kpi-reports.store'), [
            'from' => self::FROM, 'to' => self::TO, 'format' => 'xlsx', 'scope' => 'team', 'team_id' => $team->id,
        ])->assertRedirect(route('kpi-reports.index'));

        $report = KpiReport::sole();
        $this->assertSame('Team: Core', $report->scopeLabel());
        $this->assertEqualsCanonicalizing([$dev->id, $qa->id], $report->members()->pluck('user_id')->all());

        $dana = collect($this->sheet(Storage::disk('local')->path($report->file_path), 'Period'))->first(fn ($r) => $r[0] === 'Dana Dev');
        $this->assertSame('Core', $dana[2]);
        $this->assertSame([1, 0, 3], array_slice($dana, 3, 3)); // one Core day, 3 tasks
        $this->assertStringContainsString('team='.$team->id, $dana[10]);
        $this->assertStringStartsWith('kpi-review_core_', $report->filename());
    }

    public function test_individual_report_covers_one_member_across_teams(): void
    {
        [$team, , $dev, $qa] = $this->team();
        $other = Team::create(['name' => 'Mobile', 'color' => '#7c3aed']);
        $other->members()->attach($dev);
        $this->entry($team, $dev, '2026-08-03');
        $this->entry($other, $dev, '2026-08-04');
        $this->entry($team, $qa, '2026-08-03');

        $this->actingAs($this->admin())->post(route('kpi-reports.store'), [
            'from' => self::FROM, 'to' => self::TO, 'format' => 'pdf', 'scope' => 'member', 'member_id' => $dev->id,
        ])->assertRedirect(route('kpi-reports.index'));

        $report = KpiReport::sole();
        $this->assertSame('Member: Dana Dev', $report->scopeLabel());
        $this->assertSame([$dev->id], $report->members()->pluck('user_id')->all());
        $this->assertCount(2, $this->analysed[0]['entries']);
        $this->assertStringStartsWith('%PDF', Storage::disk('local')->get($report->file_path));
    }

    public function test_scope_requires_its_target(): void
    {
        [, $lead] = $this->team();
        $admin = $this->admin();
        $base = ['from' => self::FROM, 'to' => self::TO, 'format' => 'xlsx'];

        $this->actingAs($admin)->post(route('kpi-reports.store'), $base + ['scope' => 'team'])->assertSessionHasErrors('team_id');
        $this->actingAs($admin)->post(route('kpi-reports.store'), $base + ['scope' => 'member'])->assertSessionHasErrors('member_id');
        $this->actingAs($admin)->post(route('kpi-reports.store'), $base + ['scope' => 'member', 'member_id' => $admin->id])->assertSessionHasErrors('member_id');
        $this->actingAs($admin)->post(route('kpi-reports.store'), $base + ['scope' => 'everyone'])->assertSessionHasErrors('scope');
        // A developer who leads a team is never scored, so there is nothing to queue.
        $this->actingAs($admin)->post(route('kpi-reports.store'), $base + ['scope' => 'member', 'member_id' => $lead->id])->assertSessionHas('error');
        $this->assertSame(0, KpiReport::count());
    }

    public function test_pdf_report_is_built(): void
    {
        [$team, , $dev] = $this->team();
        $this->entry($team, $dev, '2026-08-03');

        $this->actingAs($this->admin())
            ->post(route('kpi-reports.store'), ['from' => self::FROM, 'to' => self::TO, 'format' => 'pdf', 'scope' => 'all']);

        $report = KpiReport::sole();
        $this->assertSame(KpiReport::STATUS_COMPLETED, $report->status);
        $this->assertStringStartsWith('%PDF', Storage::disk('local')->get($report->file_path));
    }

    public function test_a_failed_member_is_flagged_and_the_report_still_completes(): void
    {
        [$team, , $dev, $qa] = $this->team();
        $this->entry($team, $dev, '2026-08-03', ['plan' => '<div>explode</div>']);
        $this->entry($team, $qa, '2026-08-03');

        $this->actingAs($this->admin())
            ->post(route('kpi-reports.store'), ['from' => self::FROM, 'to' => self::TO, 'format' => 'xlsx', 'scope' => 'all']);

        $report = KpiReport::sole();
        $this->assertSame(KpiReport::STATUS_COMPLETED, $report->status);
        $this->assertSame([1, 1], [$report->members_done, $report->members_failed]);
        $this->assertSame(KpiReportMember::STATUS_FAILED, $report->members()->where('user_id', $dev->id)->value('status'));

        $rows = $this->sheet(Storage::disk('local')->path($report->file_path), 'Period');
        $this->assertStringStartsWith('Analysis failed', collect($rows)->first(fn ($r) => $r[0] === 'Dana Dev')[17]);
    }

    public function test_only_one_report_runs_at_a_time(): void
    {
        [$team, , $dev] = $this->team();
        $this->entry($team, $dev, '2026-08-03');
        $admin = $this->admin();
        KpiReport::create(['requested_by' => $admin->id, 'period_start' => self::FROM, 'period_end' => self::TO, 'format' => 'xlsx', 'status' => KpiReport::STATUS_PROCESSING]);

        $this->actingAs($admin)
            ->post(route('kpi-reports.store'), ['from' => self::FROM, 'to' => self::TO, 'format' => 'xlsx', 'scope' => 'all'])
            ->assertSessionHas('error');

        $this->assertSame(1, KpiReport::count());
    }

    public function test_store_validates_the_period(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('kpi-reports.store'), ['from' => '2026-09-01', 'to' => '2026-08-01', 'format' => 'xlsx', 'scope' => 'all'])
            ->assertSessionHasErrors('to');
        $this->actingAs($admin)->post(route('kpi-reports.store'), ['from' => '2025-01-01', 'to' => '2026-08-01', 'format' => 'xlsx', 'scope' => 'all'])
            ->assertSessionHasErrors('to');
        $this->actingAs($admin)->post(route('kpi-reports.store'), ['from' => self::FROM, 'to' => self::TO, 'format' => 'csv', 'scope' => 'all'])
            ->assertSessionHasErrors('format');
        $this->actingAs($admin)->post(route('kpi-reports.store'), ['from' => self::FROM, 'to' => self::TO, 'format' => 'xlsx', 'scope' => 'all'])
            ->assertSessionHas('error'); // no tasksheet rows in the period
    }

    public function test_status_bands_follow_the_field_guide(): void
    {
        $this->assertSame('Excellent', KpiReviewService::status(90.0));
        $this->assertSame('Good', KpiReviewService::status(89.9));
        $this->assertSame('Good', KpiReviewService::status(85.0));
        $this->assertSame('Satisfactory', KpiReviewService::status(80.0));
        $this->assertSame('Needs Improvement', KpiReviewService::status(79.9));
        $this->assertSame('', KpiReviewService::status(null));

        $this->assertSame('Fully achieved', KpiReviewService::success(95.0));
        $this->assertSame('Partially achieved', KpiReviewService::success(50.0));

        $day = fn (int $done) => ['planned' => 10, 'added' => 0, 'completed' => $done];
        $this->assertSame('Improving', KpiReviewService::progress([$day(7), $day(7), $day(9), $day(9)]));
        $this->assertSame('Declining', KpiReviewService::progress([$day(9), $day(9), $day(7), $day(7)]));
        $this->assertSame('Stable', KpiReviewService::progress([$day(8), $day(8), $day(8), $day(8)]));
    }

    public function test_deleting_a_report_removes_its_file(): void
    {
        [$team, , $dev] = $this->team();
        $this->entry($team, $dev, '2026-08-03');
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('kpi-reports.store'), ['from' => self::FROM, 'to' => self::TO, 'format' => 'xlsx', 'scope' => 'all']);
        $report = KpiReport::sole();

        $this->actingAs($admin)->delete(route('kpi-reports.destroy', $report))->assertRedirect();

        $this->assertSame(0, KpiReport::count());
        Storage::disk('local')->assertMissing($report->file_path);
    }
}
