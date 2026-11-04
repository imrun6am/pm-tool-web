<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenerateStandupRequest;
use App\Models\DailyStandup;
use App\Services\DailyStandupService;
use Illuminate\Http\RedirectResponse;

class DailyStandupController extends Controller
{
    public function __construct(private readonly DailyStandupService $standups) {}

    /** Generate or refresh on request, which also brings back a deleted standup. */
    public function store(GenerateStandupRequest $request): RedirectResponse
    {
        $team = $request->team();
        $this->authorize('generate', [DailyStandup::class, $team]);

        $run = $this->standups->generate($team, $request->day(), restore: true);

        return $run->note
            ? redirect()->route('meeting-notes.show', $run->note)->with('success', $run->message())
            : back()->with('error', $run->message());
    }
}
