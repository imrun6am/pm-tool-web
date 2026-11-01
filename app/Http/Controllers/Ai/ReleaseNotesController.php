<?php

namespace App\Http\Controllers\Ai;

use App\Http\Controllers\Ai\Concerns\RespondsWithAi;
use App\Http\Controllers\Controller;
use App\Models\Release;
use App\Services\Ai\ReleaseNotesWriter;
use App\Support\HtmlSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Release notes: Claude drafts them, a release planner edits and saves them. */
class ReleaseNotesController extends Controller
{
    use RespondsWithAi;

    public function draft(Request $request, Release $release, ReleaseNotesWriter $writer): JsonResponse
    {
        abort_unless($request->user()->canManageReleases(), 403);

        return $this->aiJson(fn () => $writer->draft($release, $request->user()));
    }

    public function update(Request $request, Release $release): RedirectResponse
    {
        abort_unless($request->user()->canManageReleases(), 403);

        $data = $request->validate(['release_notes' => ['nullable', 'string', 'max:50000']]);
        $notes = HtmlSanitizer::clean($data['release_notes'] ?? '');

        $release->forceFill(['release_notes' => HtmlSanitizer::isEmpty($notes) ? null : $notes])->save();

        return back()->with('success', 'Release notes saved.');
    }
}
