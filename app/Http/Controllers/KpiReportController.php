<?php

namespace App\Http\Controllers;

use App\Http\Requests\KpiReportRequest;
use App\Models\KpiReport;
use App\Models\Team;
use App\Models\User;
use App\Services\Kpi\KpiReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin-only KPI review exports. Generation is queued (one job per member)
 * and only one report runs at a time, so a request never ties up a web
 * worker and the queue never runs several Claude-heavy reports at once.
 */
class KpiReportController extends Controller
{
    public function index(): View
    {
        $lastQuarter = today()->subQuarterNoOverflow();

        return view('kpi-reports.index', [
            'reports' => KpiReport::with(['requester', 'team', 'subject'])->latest('id')->limit(20)->get(),
            'teams' => Team::active()->orderBy('name')->get(['id', 'name']),
            'scoredMembers' => User::whereIn('role', [User::ROLE_DEVELOPER, User::ROLE_QA])
                ->whereNotIn('id', Team::whereNotNull('team_lead_id')->select('team_lead_id'))
                ->orderBy('name')->get(['id', 'name', 'role']),
            'running' => KpiReport::whereIn('status', [KpiReport::STATUS_QUEUED, KpiReport::STATUS_PROCESSING])->exists(),
            'apiKeyMissing' => blank(config('services.anthropic.key')),
            'defaultFrom' => $lastQuarter->copy()->firstOfQuarter()->toDateString(),
            'defaultTo' => $lastQuarter->copy()->lastOfQuarter()->toDateString(),
        ]);
    }

    public function store(KpiReportRequest $request, KpiReviewService $kpi): RedirectResponse
    {
        if (blank(config('services.anthropic.key'))) {
            return back()->withInput()->with('error', 'Set ANTHROPIC_API_KEY in .env before generating a report.');
        }

        if (KpiReport::whereIn('status', [KpiReport::STATUS_QUEUED, KpiReport::STATUS_PROCESSING])->exists()) {
            return back()->withInput()->with('error', 'A report is already being generated. Wait for it to finish.');
        }

        if ($kpi->start($request->user(), $request->validated()) === null) {
            return back()->withInput()->with('error', 'No developer or QA tasksheet rows for that selection and period.');
        }

        return redirect()->route('kpi-reports.index')
            ->with('success', 'Report queued. It is generated in the background; this page updates as members are analysed.');
    }

    public function download(KpiReport $report): StreamedResponse
    {
        abort_unless($report->isCompleted() && $report->file_path && Storage::disk('local')->exists($report->file_path), 404);

        return Storage::disk('local')->download($report->file_path, $report->filename());
    }

    public function destroy(KpiReport $report): RedirectResponse
    {
        if ($report->isRunning() && $report->batch_id) {
            Bus::findBatch($report->batch_id)?->cancel();
        }

        Storage::disk('local')->deleteDirectory("kpi-reports/{$report->id}");
        $report->delete();

        return back()->with('success', 'Report deleted.');
    }
}
