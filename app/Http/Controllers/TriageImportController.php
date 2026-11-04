<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ServesTriageImportFile;
use App\Http\Requests\ConfirmTriageImportRequest;
use App\Http\Requests\TriageImportRequest;
use App\Http\Requests\TriageImportTextRequest;
use App\Models\Label;
use App\Models\TriageImport;
use App\Models\TriageItem;
use App\Services\TriageImportService;
use App\Services\TriageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Creating requests from a file, screenshot or pasted text. Each queues the
 * reading and returns at once; the import's page updates in place until the
 * proposals are ready, then its owner reviews and sends them.
 */
class TriageImportController extends Controller
{
    use ServesTriageImportFile;

    public function __construct(private readonly TriageImportService $imports) {}

    public function store(TriageImportRequest $request): RedirectResponse
    {
        $import = $this->imports->start($request->user(), $request->file('document'));

        return redirect()->route('triage.imports.show', $import);
    }

    public function storeText(TriageImportTextRequest $request): RedirectResponse
    {
        $import = $this->imports->startFromText($request->user(), $request->validated('text'));

        return redirect()->route('triage.imports.show', $import);
    }

    public function show(Request $request, TriageImport $triageImport, TriageService $triage): View
    {
        $this->authorize('view', $triageImport);

        return view('triage.imports.show', [
            'import' => $triageImport,
            'rows' => $triageImport->isReviewable() ? $this->imports->reviewRows($triageImport) : [],
            'teams' => $triage->teamsFor($request->user()),
            'labels' => Label::orderBy('name')->get(),
            'pastedText' => $triageImport->isPasted() ? Storage::disk('local')->get($triageImport->file_path) : null,
            'canRetry' => $this->imports->available(),
        ]);
    }

    public function confirm(ConfirmTriageImportRequest $request, TriageImport $triageImport): RedirectResponse
    {
        $this->authorize('confirm', $triageImport);
        abort_unless($triageImport->isReviewable(), 409, 'This import is not ready for review.');

        $created = $this->imports->confirm($triageImport, $request->validated('requests'), $request->user());
        if ($created->isEmpty()) {
            return back()->with('error', 'Select at least one request that hasn’t been sent yet.');
        }

        return redirect()->route('triage.index')
            ->with('success', $created->count().' '.Str::plural('request', $created->count()).' sent.');
    }

    public function retry(TriageImport $triageImport): RedirectResponse
    {
        $this->authorize('retry', $triageImport);
        abort_unless($triageImport->isFailed(), 409, 'Only a failed import can be tried again.');

        if (! $this->imports->available()) {
            return back()->with('error', 'Claude isn’t available right now, so this can’t be read until an admin turns it back on.');
        }

        $this->imports->retry($triageImport);

        return redirect()->route('triage.imports.show', $triageImport);
    }

    public function destroy(TriageImport $triageImport): RedirectResponse
    {
        $this->authorize('delete', $triageImport);

        $this->imports->delete($triageImport);

        return redirect()->route('triage.index')->with('success', 'Import deleted.');
    }

    /** The owner's copy of the original, shown on the review page. */
    public function file(TriageImport $triageImport): StreamedResponse
    {
        $this->authorize('view', $triageImport);

        return $this->importFile($triageImport);
    }

    /** The original behind a sent request, for its reporter and whoever may decide it. */
    public function source(TriageItem $triageItem): StreamedResponse
    {
        $this->authorize('viewSource', $triageItem);
        abort_unless($triageItem->sourceImport, 404, 'This request has no original file.');

        return $this->importFile($triageItem->sourceImport);
    }
}
