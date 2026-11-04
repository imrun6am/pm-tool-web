<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReleaseStatusRequest;
use App\Models\Release;
use App\Services\ReleaseStatusService;
use Illuminate\Http\RedirectResponse;

class ReleaseStatusController extends Controller
{
    public function __construct(private readonly ReleaseStatusService $statuses) {}

    public function store(ReleaseStatusRequest $request, Release $release): RedirectResponse
    {
        $this->statuses->post($release, $request->user(), $request->validated('status'), $request->validated('note'));

        return back()->with('success', 'Status update posted.');
    }
}
