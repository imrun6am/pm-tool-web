<?php

namespace Tests\Feature;

use App\Models\EventImport;
use App\Models\KpiReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Job status on the KPI reports and calendar import pages updates in place
 * (<x-poll-region>), never by reloading the page: each page renders a region
 * that says whether a job is still running, and no reload code remains.
 */
class PollRegionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function kpi(User $admin, string $status): KpiReport
    {
        return KpiReport::create(['requested_by' => $admin->id, 'period_start' => '2026-08-01', 'period_end' => '2026-08-31', 'format' => 'xlsx', 'status' => $status]);
    }

    private function import(User $user, string $status): EventImport
    {
        Storage::fake('local');
        Storage::disk('local')->put("event-imports/{$user->id}/x.txt", 'Standup Monday 10:00');

        return EventImport::create([
            'user_id' => $user->id, 'original_name' => 'plan.txt', 'file_path' => "event-imports/{$user->id}/x.txt",
            'extension' => 'txt', 'mime_type' => 'text/plain', 'size' => 20, 'timezone' => 'UTC', 'status' => $status,
        ]);
    }

    public function test_kpi_history_polls_in_place_while_a_report_runs(): void
    {
        $admin = $this->admin();
        $report = $this->kpi($admin, KpiReport::STATUS_PROCESSING);

        $this->actingAs($admin)->get(route('kpi-reports.index'))->assertOk()
            ->assertSee('id="kpi-history"', false)
            ->assertSee('data-running="true"', false)
            ->assertSee('id="kpi-generate"', false)
            ->assertDontSee('location.reload', false);

        $report->update(['status' => KpiReport::STATUS_COMPLETED]);

        $this->actingAs($admin)->get(route('kpi-reports.index'))
            ->assertSee('data-running="false"', false);
    }

    public function test_calendar_imports_list_polls_in_place(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_TEAM_LEAD]);
        $this->import($user, EventImport::STATUS_PROCESSING);

        $this->actingAs($user)->get(route('calendar.imports.index'))->assertOk()
            ->assertSee('id="calendar-imports"', false)
            ->assertSee('data-running="true"', false)
            ->assertDontSee('location.reload', false);
    }

    public function test_an_import_page_swaps_in_its_result_without_reloading(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_TEAM_LEAD]);
        $import = $this->import($user, EventImport::STATUS_PROCESSING);

        $this->actingAs($user)->get(route('calendar.imports.show', $import))->assertOk()
            ->assertSee('id="calendar-import-'.$import->id.'"', false)
            ->assertSee('data-running="true"', false)
            ->assertSee("\$dispatch('poll-region-now')", false)
            ->assertDontSee('Turbo.visit', false);
    }
}
