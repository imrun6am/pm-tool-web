<?php

namespace App\Http\Controllers;

use App\Http\Requests\TasksheetExportRequest;
use App\Models\TasksheetExport;
use App\Services\TasksheetExportService;
use App\Services\TasksheetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Tasksheet PDFs, generated in the background. A request only records the
 * export and queues it; the queue renders it a chunk at a time, and this
 * page polls `status` until the file is ready to download.
 */
class TasksheetExportController extends Controller
{
    /** The inputs the export form, and the old report URL, accept. */
    public const INPUT = ['team', 'member', 'date', 'from', 'to', 'attendance', 'fill', 'leave'];

    public function __construct(private readonly TasksheetExportService $exports) {}

    public function index(Request $request, TasksheetService $tasksheet): View
    {
        $viewer = $request->user();
        $prefill = $this->exports->selection($request->only(self::INPUT));

        $exports = $this->exports->recentFor($viewer);

        return view('tasksheet.exports', [
            'exports' => $exports,
            'statuses' => $exports->mapWithKeys(fn (TasksheetExport $e) => [$e->id => $this->statusPayload($e)]),
            'teams' => $tasksheet->teamsFor($viewer),
            'members' => $this->exports->subjectsFor($viewer, $prefill['member_id']),
            'prefill' => $prefill,
            'hasCapacity' => $this->exports->hasCapacity($viewer),
            'isLeadViewer' => $viewer->isLead(),
        ]);
    }

    public function store(TasksheetExportRequest $request): RedirectResponse
    {
        $input = $request->validated();
        $this->authorize('create', [TasksheetExport::class, $this->exports->selection($input)['member_id']]);

        if (! $this->exports->hasCapacity($request->user())) {
            return back()->withInput()->with('error', 'You already have exports in progress. Start another when one finishes.');
        }

        $this->exports->start($request->user(), $input);

        return redirect()->route('tasksheet.exports.index')
            ->with('success', 'Export queued. It is built in the background; this page updates as it runs.');
    }

    /** Live status for the given exports — only the viewer's own come back. */
    public function status(Request $request): JsonResponse
    {
        $ids = collect((array) $request->query('ids', []))
            ->flatMap(fn ($value) => explode(',', (string) $value))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->take(50)
            ->values()
            ->all();

        return response()->json([
            'data' => $this->exports->statusesFor($request->user(), $ids)
                ->map(fn (TasksheetExport $export) => $this->statusPayload($export))
                ->values(),
        ]);
    }

    public function download(TasksheetExport $export): StreamedResponse
    {
        $this->authorize('download', $export);
        abort_unless($export->isReady() && $export->file_path && Storage::disk('local')->exists($export->file_path), 404);

        return Storage::disk('local')->download($export->file_path, $this->exports->filename($export));
    }

    public function retry(Request $request, TasksheetExport $export): RedirectResponse
    {
        $this->authorize('retry', $export);

        if (! $export->isFailed()) {
            return back()->with('error', 'Only a failed export can be retried.');
        }

        if (! $this->exports->hasCapacity($request->user())) {
            return back()->with('error', 'You already have exports in progress. Retry when one finishes.');
        }

        $this->exports->retry($export);

        return back()->with('success', 'Export queued again.');
    }

    public function destroy(TasksheetExport $export): RedirectResponse
    {
        $this->authorize('delete', $export);

        $running = $export->isRunning();
        $this->exports->delete($export);

        return back()->with('success', $running ? 'Export cancelled and deleted.' : 'Export deleted.');
    }

    /** @return array<string, mixed> */
    private function statusPayload(TasksheetExport $export): array
    {
        return [
            'id' => $export->id,
            'status' => $export->status,
            'status_label' => $export->statusLabel(),
            'progress' => $export->progressPercent(),
            'chunks_done' => $export->chunks_done,
            'chunks_total' => $export->chunks_total,
            'rows_total' => $export->rows_total,
            'merging' => $export->isMerging(),
            'error' => $export->error,
            'download_url' => $export->isReady() ? route('tasksheet.exports.download', $export) : null,
            'completed_at' => $export->completed_at?->toIso8601String(),
        ];
    }
}
