<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfirmEventImportRequest;
use App\Http\Requests\EventImportRequest;
use App\Models\EventImport;
use App\Models\Release;
use App\Models\User;
use App\Services\Calendar\EventImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Calendar "Import from file". Upload queues the extraction and returns at
 * once; the import page polls until the proposals are ready, then the
 * uploader reviews and confirms them into events.
 */
class EventImportController extends Controller
{
    public function __construct(private readonly EventImportService $imports) {}

    public function index(Request $request): View
    {
        return view('calendar.imports.index', [
            'imports' => EventImport::where('user_id', $request->user()->id)->latest('id')->limit(25)->get(),
            'apiKeyMissing' => blank(config('services.anthropic.key')),
        ]);
    }

    public function store(EventImportRequest $request): RedirectResponse
    {
        $import = $this->imports->start($request->user(), $request->file('document'), $request->validated('timezone'));

        return redirect()->route('calendar.imports.show', $import)
            ->with('success', 'File uploaded. It is being read in the background.');
    }

    public function show(EventImport $import): View
    {
        $this->authorize('view', $import);

        return view('calendar.imports.show', [
            'import' => $import,
            'rows' => $import->isReviewable() ? $this->imports->reviewRows($import) : [],
            'users' => User::active()->visibleTo(request()->user())->orderBy('name')->get(['id', 'name', 'email']),
            'releases' => Release::visibleTo(request()->user())->orderByDesc('year')->orderBy('name')->get(['id', 'name', 'year']),
            'canConfirm' => request()->user()->can('confirm', $import),
        ]);
    }

    /** Polled by the import page while the job runs. */
    public function status(EventImport $import): JsonResponse
    {
        $this->authorize('view', $import);

        return response()->json([
            'status' => $import->status,
            'label' => $import->statusLabel(),
            'running' => $import->isRunning(),
            'chunks_done' => $import->chunks_done,
            'chunks_total' => $import->chunks_total,
            'progress' => $import->progressPercent(),
            'error' => $import->error,
        ]);
    }

    public function confirm(ConfirmEventImportRequest $request, EventImport $import): RedirectResponse
    {
        $this->authorize('confirm', $import);
        abort_unless($import->isReviewable(), 409, 'This import is not ready for review.');

        $created = $this->imports->confirm($import, $request->validated('events'), $request->user());
        if ($created->isEmpty()) {
            return back()->with('error', 'Select at least one event that hasn’t been added yet.');
        }

        $first = $created->first()->starts_at;

        return redirect()->route('calendar.index', ['year' => $first->year, 'month' => $first->month])
            ->with('success', $created->count().' '.Str::plural('event', $created->count()).' added to the calendar.');
    }

    public function retry(EventImport $import): RedirectResponse
    {
        $this->authorize('retry', $import);
        abort_unless($import->isFailed(), 409, 'Only a failed import can be retried.');

        $this->imports->retry($import);

        return redirect()->route('calendar.imports.show', $import)->with('success', 'Import queued again.');
    }

    public function destroy(EventImport $import): RedirectResponse
    {
        $this->authorize('delete', $import);

        $this->imports->delete($import);

        return redirect()->route('calendar.imports.index')->with('success', 'Import deleted. Events already added stay on the calendar.');
    }
}
