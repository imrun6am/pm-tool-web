<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\TasksheetExportRequest;
use App\Http\Resources\V1\TasksheetExportResource;
use App\Models\TasksheetExport;
use App\Services\TasksheetExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Tasksheet PDFs, generated in the background — the API side of the web
 * exports page, through the same service and policy.
 *
 * Create and retry answer 202: the export is recorded and queued, but the
 * file does not exist yet. The client polls `show` (or `status` for several)
 * until the export is ready or failed, then follows `download_url`.
 */
class TasksheetExportController extends ApiController
{
    /** Relations the resource labels an export with. */
    private const WITH = ['team', 'subject'];

    public function __construct(private readonly TasksheetExportService $exports) {}

    /** The caller's recent exports, newest first, and whether they may start another. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $viewer = $request->user();

        return TasksheetExportResource::collection($this->exports->recentFor($viewer))
            ->additional(['meta' => [
                'has_capacity' => $this->exports->hasCapacity($viewer),
                'max_running' => max(1, (int) config('tasksheet.export.max_running', 3)),
            ]]);
    }

    public function store(TasksheetExportRequest $request): JsonResponse
    {
        $input = $request->validated();
        $this->authorize('create', [TasksheetExport::class, $this->exports->selection($input)['member_id']]);

        abort_unless(
            $this->exports->hasCapacity($request->user()),
            422,
            'You already have exports in progress. Start another when one finishes.'
        );

        return $this->accepted($this->exports->start($request->user(), $input), 'Export queued.');
    }

    /** Live status for the given exports — only the caller's own come back. */
    public function status(Request $request): AnonymousResourceCollection
    {
        $ids = collect((array) $request->query('ids', []))
            ->flatMap(fn ($value) => explode(',', (string) $value))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->take(50)
            ->values()
            ->all();

        return TasksheetExportResource::collection(
            $this->exports->statusesFor($request->user(), $ids)->load(self::WITH)
        );
    }

    public function show(TasksheetExport $export): TasksheetExportResource
    {
        $this->authorize('view', $export);

        return new TasksheetExportResource($export->load(self::WITH));
    }

    public function download(TasksheetExport $export): StreamedResponse
    {
        $this->authorize('download', $export);
        abort_unless($export->isReady() && $export->file_path && Storage::disk('local')->exists($export->file_path), 404);

        return Storage::disk('local')->download($export->file_path, $this->exports->filename($export));
    }

    public function retry(Request $request, TasksheetExport $export): JsonResponse
    {
        $this->authorize('retry', $export);

        abort_unless($export->isFailed(), 422, 'Only a failed export can be retried.');
        abort_unless(
            $this->exports->hasCapacity($request->user()),
            422,
            'You already have exports in progress. Retry when one finishes.'
        );

        return $this->accepted($this->exports->retry($export), 'Export queued again.');
    }

    public function destroy(TasksheetExport $export): JsonResponse
    {
        $this->authorize('delete', $export);

        $running = $export->isRunning();
        $this->exports->delete($export);

        return $this->message($running ? 'Export cancelled and deleted.' : 'Export deleted.');
    }

    /** A 202 with the queued export, and where to poll it. */
    private function accepted(TasksheetExport $export, string $message): JsonResponse
    {
        return (new TasksheetExportResource($export->load(self::WITH)))
            ->additional(['message' => $message])
            ->response()
            ->setStatusCode(202)
            ->header('Location', route('api-v1.tasksheet.exports.show', $export));
    }
}
