<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ActivityInsights;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityController extends Controller
{
    public function __construct(private readonly ActivityInsights $insights) {}

    public function index(Request $request): View
    {
        $causerId = $request->filled('causer_id') ? (int) $request->integer('causer_id') : null;
        $event = $request->input('event');

        return view('activity.index', [
            // The feed respects both filters; the cards and charts follow only
            // the causer focus, so the breakdowns stay meaningful.
            'activities' => $this->insights
                ->feed(['causer_id' => $causerId, 'event' => $event])
                ->paginate(20)
                ->withQueryString(),
            'users' => User::orderBy('name')->get(),
            'filters' => ['causer_id' => $causerId, 'event' => $event],
            'stats' => $this->insights->totals($causerId),
            'trend' => $this->insights->trend($causerId),
            'topContributors' => $this->insights->topContributors($causerId),
            'byType' => $this->insights->bySubjectType($causerId),
        ]);
    }
}
