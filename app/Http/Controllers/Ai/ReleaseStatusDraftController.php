<?php

namespace App\Http\Controllers\Ai;

use App\Http\Controllers\Ai\Concerns\RespondsWithAi;
use App\Http\Controllers\Controller;
use App\Models\Release;
use App\Services\Ai\ReleaseStatusWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReleaseStatusDraftController extends Controller
{
    use RespondsWithAi;

    public function __invoke(Request $request, Release $release, ReleaseStatusWriter $writer): JsonResponse
    {
        abort_unless($request->user()->canManageReleases(), 403);

        return $this->aiJson(fn () => $writer->draft($release, $request->user()));
    }
}
