<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReleaseDocumentRequest;
use App\Models\Release;
use App\Models\ReleaseDocument;
use App\Services\ReleaseDocumentService;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReleaseDocumentController extends Controller
{
    public function __construct(private readonly ReleaseDocumentService $documents) {}

    public function store(ReleaseDocumentRequest $request, Release $release): RedirectResponse
    {
        // Upload authorization (non-viewers only) lives in ReleaseDocumentRequest.
        $this->documents->store($release, $request->file('document'), $request->user());

        return back()->with('success', 'Document uploaded.');
    }

    public function download(Release $release, ReleaseDocument $document): StreamedResponse
    {
        return $this->documents->download($release, $document);
    }

    public function destroy(Release $release, ReleaseDocument $document): RedirectResponse
    {
        $this->documents->delete($release, $document);

        return back()->with('success', 'Document deleted.');
    }
}
