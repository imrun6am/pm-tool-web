<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Release;
use App\Models\Team;
use App\Models\User;
use App\Services\ActivityInsights;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityController extends Controller
{
    public function __construct(private readonly ActivityInsights $insights) {}

    public function index(Request $request): View
    {
        $filters = ActivityInsights::filters($request->query());

        return view('activity.index', [
            // The feed takes every filter; the charts take only the scope
            // (person/team/project/release/period), so a drill-down narrows
            // the feed without collapsing the breakdown it came from.
            'activities' => $this->insights->windowFeed($filters)->paginate(20)->withQueryString(),
            'filters' => $filters,
            'analysis' => $this->insights->analysis($filters),
            'stats' => $this->insights->totals($filters['causer_id']),
            'typeOptions' => $this->insights->bySubjectType(),
            'users' => User::orderBy('name')->get(['id', 'name']),
            'teams' => Team::orderBy('name')->get(['id', 'name']),
            'projects' => Project::orderBy('name')->get(['id', 'name']),
            'release' => $filters['release_id'] ? Release::find($filters['release_id'], ['id', 'name']) : null,
        ]);
    }
}
