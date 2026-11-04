<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Release;
use App\Models\Task;
use App\Models\TaskTimeLog;
use App\Models\Team;
use App\Models\User;
use App\Services\TimeReportService;
use App\Support\TimeReportCsv;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Logged hours by release, team and person, and how they compare with estimates. */
class TimeReportTest extends TestCase
{
    use RefreshDatabase;

    private Team $core;

    private Team $other;

    private User $admin;

    private User $dana;

    private Release $release;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-02 09:00:00');
        $this->core = Team::create(['name' => 'Core', 'color' => '#0891b2']);
        $this->other = Team::create(['name' => 'Other', 'color' => '#db2777']);
        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->dana = User::factory()->create(['role' => User::ROLE_DEVELOPER, 'name' => 'Dana Dev']);
        $this->release = Release::create([
            'project_id' => Project::create(['name' => 'Shop', 'color' => '#0891b2'])->id,
            'team_id' => $this->core->id, 'name' => 'Checkout', 'year' => 2026, 'quarter' => 4,
            'start_date' => '2026-10-01', 'end_date' => '2026-11-30',
        ]);
    }

    private function task(array $attrs = []): Task
    {
        return Task::create($attrs + ['title' => 'Work', 'status' => 'todo', 'position' => 0, 'team_id' => $this->core->id]);
    }

    private function log(Task $task, int $minutes, string $on = '2026-10-20', ?User $user = null, ?string $note = null): void
    {
        TaskTimeLog::create(['task_id' => $task->id, 'user_id' => ($user ?? $this->dana)->id, 'logged_on' => $on, 'minutes' => $minutes, 'note' => $note]);
    }

    private function report(array $filters = [], ?User $viewer = null): array
    {
        return app(TimeReportService::class)->report($filters + ['from' => today()->subDays(29), 'to' => today(), 'team_id' => null, 'release_id' => null], $viewer ?? $this->admin);
    }

    public function test_hours_are_added_up_by_release_team_and_person(): void
    {
        $this->log($this->task(['release_id' => $this->release->id]), 120);
        $this->log($this->task(), 30);
        $this->log($this->task(['team_id' => $this->other->id]), 60, user: $this->admin);

        $report = $this->report();

        $this->assertSame(210, $report['total_minutes']);
        $this->assertSame([['Checkout', 120], ['No release', 90]], array_map(fn ($r) => [$r['name'], $r['minutes']], $report['by_release']));
        $this->assertSame([['Core', 150], ['Other', 60]], array_map(fn ($r) => [$r['name'], $r['minutes']], $report['by_team']));
        $this->assertSame(['Dana Dev', 150], [$report['by_person'][0]['name'], $report['by_person'][0]['minutes']]);
    }

    public function test_the_period_and_filters_narrow_it(): void
    {
        $this->log($this->task(), 10, '2026-09-01');
        $this->log($this->task(['release_id' => $this->release->id]), 20);
        $this->log($this->task(['team_id' => $this->other->id]), 40);

        $this->assertSame(60, $this->report()['total_minutes']);
        $this->assertSame(40, $this->report(['team_id' => $this->other->id])['total_minutes']);
        $this->assertSame(20, $this->report(['release_id' => $this->release->id])['total_minutes']);
    }

    public function test_hours_on_deleted_tasks_are_left_out(): void
    {
        $task = $this->task();
        $this->log($task, 30);
        $task->delete();

        $this->assertSame(0, $this->report()['total_minutes']);
    }

    public function test_someone_who_left_still_has_their_hours_named(): void
    {
        $this->log($this->task(), 30);
        $this->dana->delete();

        $this->assertSame('Dana Dev', $this->report()['by_person'][0]['name']);
    }

    public function test_accuracy_compares_finished_tasks_that_have_both_figures(): void
    {
        $over = $this->task(['estimate_minutes' => 60, 'title' => 'Over']);
        $this->log($over, 180);
        $over->update(['status' => 'done']);

        $under = $this->task(['estimate_minutes' => 120, 'title' => 'Under']);
        $this->log($under, 60);
        $under->update(['status' => 'done']);

        $open = $this->task(['estimate_minutes' => 60]);
        $this->log($open, 600);

        $noLogs = $this->task(['estimate_minutes' => 60]);
        $noLogs->update(['status' => 'done']);

        $noEstimate = $this->task();
        $this->log($noEstimate, 600);
        $noEstimate->update(['status' => 'done']);

        $accuracy = $this->report()['accuracy'];

        $this->assertSame(2, $accuracy['tasks']);
        $this->assertSame(180, $accuracy['estimated_minutes']);
        $this->assertSame(240, $accuracy['logged_minutes']);
        $this->assertSame(1.33, $accuracy['ratio']);
        $this->assertSame(['Over', 'Under'], array_column($accuracy['misses'], 'title'));
    }

    public function test_accuracy_counts_tasks_finished_in_the_period_only(): void
    {
        $this->travelTo('2026-08-01 09:00:00');
        $task = $this->task(['estimate_minutes' => 60]);
        $task->update(['status' => 'done']);
        $this->travelTo('2026-11-02 09:00:00');
        $this->log($task, 60);

        $this->assertSame(0, $this->report()['accuracy']['tasks']);
    }

    public function test_only_leads_can_see_it(): void
    {
        foreach ([User::ROLE_ADMIN, User::ROLE_CTO, User::ROLE_TECH_LEAD, User::ROLE_TEAM_LEAD] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get(route('reports.time'))->assertOk();
        }

        foreach ([User::ROLE_DEVELOPER, User::ROLE_QA, User::ROLE_VIEWER] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get(route('reports.time'))->assertForbidden();
            $this->actingAs($user)->get(route('reports.time.export'))->assertForbidden();
        }
    }

    public function test_a_team_lead_sees_only_the_teams_they_lead(): void
    {
        $lead = User::factory()->create(['role' => User::ROLE_TEAM_LEAD]);
        $this->core->update(['team_lead_id' => $lead->id]);
        $this->log($this->task(), 20);
        $this->log($this->task(['team_id' => $this->other->id]), 40);
        $mine = $this->task(['estimate_minutes' => 10]);
        $this->log($mine, 10);
        $mine->update(['status' => 'done']);
        $theirs = $this->task(['estimate_minutes' => 10, 'team_id' => $this->other->id]);
        $this->log($theirs, 10);
        $theirs->update(['status' => 'done']);

        $report = $this->report(viewer: $lead);

        $this->assertSame(30, $report['total_minutes']);
        $this->assertSame(1, $report['accuracy']['tasks']);

        $this->actingAs($lead)->get(route('reports.time', ['team_id' => $this->other->id]))->assertForbidden();
        $this->actingAs($lead)->get(route('reports.time.export', ['team_id' => $this->other->id]))->assertForbidden();
        $this->actingAs($lead)->get(route('reports.time'))->assertDontSee('Other');
    }

    public function test_a_date_range_can_be_open_ended_but_not_backwards(): void
    {
        $this->actingAs($this->admin)->get(route('reports.time', ['to' => '2026-10-01']))->assertOk();
        $this->actingAs($this->admin)->get(route('reports.time', ['from' => '2026-10-01']))->assertOk();
        $this->actingAs($this->admin)->from(route('reports.time'))
            ->get(route('reports.time', ['from' => '2026-10-10', 'to' => '2026-10-01']))
            ->assertRedirect()->assertSessionHasErrors('to');
    }

    public function test_the_page_shows_the_breakdown_and_says_what_its_for(): void
    {
        $task = $this->task(['estimate_minutes' => 60, 'title' => 'Payment fix']);
        $this->log($task, 90);
        $task->update(['status' => 'done']);

        $this->actingAs($this->admin)->get(route('reports.time'))->assertOk()
            ->assertSee('Dana Dev')
            ->assertSee('1h 30m')
            ->assertSee('150%')
            ->assertSee('Payment fix')
            ->assertSee('don’t feed performance scores', false);
    }

    public function test_the_page_has_a_friendly_empty_state(): void
    {
        $this->actingAs($this->admin)->get(route('reports.time'))->assertOk()->assertSee('No hours logged in this period.');
    }

    public function test_the_export_is_a_spreadsheet_of_every_entry(): void
    {
        $this->log($this->task(['title' => 'Plain', 'release_id' => $this->release->id]), 90, note: 'Fixed it');

        $response = $this->actingAs($this->admin)->get(route('reports.time.export'))->assertOk();

        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('time_2026-10-04_2026-11-02.csv', $response->headers->get('Content-Disposition'));
        $csv = $response->streamedContent();
        $this->assertStringContainsString('Date,Person,Team,Release,Task,Title,Minutes,Hours,Note', $csv);
        $this->assertStringContainsString('2026-10-20,"Dana Dev",Core,Checkout', $csv);
        $this->assertStringContainsString('Plain,90,1.5,"Fixed it"', $csv);
    }

    public function test_text_in_the_export_cannot_run_as_a_spreadsheet_formula(): void
    {
        foreach (['=HYPERLINK("x")', '+1+1', '-2', '@SUM(A1)', "\tTab"] as $danger) {
            $this->assertSame("'".$danger, TimeReportCsv::cell($danger));
        }
        $this->assertSame('Normal', TimeReportCsv::cell('Normal'));
        $this->assertSame('', TimeReportCsv::cell(null));

        $this->log($this->task(['title' => '=cmd()']), 30, note: '@evil');
        $csv = $this->actingAs($this->admin)->get(route('reports.time.export'))->streamedContent();

        $this->assertStringContainsString("'=cmd()", $csv);
        $this->assertStringContainsString("'@evil", $csv);
    }

    public function test_the_api_mirrors_the_page(): void
    {
        $this->log($this->task(), 45);
        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/reports/time')->assertOk()
            ->assertJsonPath('data.total_minutes', 45)
            ->assertJsonPath('data.by_person.0.name', 'Dana Dev')
            ->assertJsonPath('data.from', '2026-10-04');

        $this->assertStringContainsString('Dana Dev', $this->get('/api/v1/reports/time/export')->assertOk()->streamedContent());

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_VIEWER]));
        $this->getJson('/api/v1/reports/time')->assertForbidden();
    }
}
