<?php

namespace App\Http\Controllers;

use App\Http\Requests\TriageAcceptRequest;
use App\Http\Requests\TriageDeclineRequest;
use App\Http\Requests\TriageItemRequest;
use App\Models\Label;
use App\Models\Release;
use App\Models\TriageItem;
use App\Models\User;
use App\Services\TriageImportService;
use App\Services\TriageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TriageController extends Controller
{
    public function __construct(private readonly TriageService $triage) {}

    public function index(Request $request, TriageImportService $imports): View
    {
        $viewer = $request->user();

        return view('triage.index', [
            'queue' => $this->triage->queue($viewer),
            'mine' => $this->triage->mine($viewer),
            'teams' => $this->triage->teamsFor($viewer),
            'labels' => Label::orderBy('name')->get(),
            'users' => $viewer->isLead() ? User::active()->orderBy('name')->get() : collect(),
            'releases' => $viewer->isLead() ? Release::ongoing()->orderBy('name')->get() : collect(),
            'imports' => $imports->open($viewer),
            'importAvailable' => $imports->available(),
        ]);
    }

    public function store(TriageItemRequest $request): RedirectResponse
    {
        $this->triage->submit($request->user(), $request->validated());

        return back()->with('success', 'Request sent.');
    }

    public function accept(TriageAcceptRequest $request, TriageItem $triageItem): RedirectResponse
    {
        abort_unless($triageItem->isPending(), 409, 'This request has already been decided.');

        $this->triage->accept($triageItem, $request->user(), $request->validated());

        return back()->with('success', 'Accepted. It’s now task '.$triageItem->task->reference().'.');
    }

    public function decline(TriageDeclineRequest $request, TriageItem $triageItem): RedirectResponse
    {
        abort_unless($triageItem->isPending(), 409, 'This request has already been decided.');

        $this->triage->decline($triageItem, $request->user(), $request->validated('reason'));

        return back()->with('success', 'Request declined.');
    }
}
