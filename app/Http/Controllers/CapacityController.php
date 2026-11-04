<?php

namespace App\Http\Controllers;

use App\Services\CapacityService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class CapacityController extends Controller
{
    public function __construct(private readonly CapacityService $capacity) {}

    public function index(Request $request): View
    {
        $teams = $this->capacity->teamsFor($request->user());
        $team = $request->filled('team_id') ? $teams->firstWhere('id', $request->integer('team_id')) : $teams->first();
        abort_if($request->filled('team_id') && ! $team, 403);

        $from = $this->capacity->firstWeek($request->filled('from') ? Carbon::parse($request->date('from')) : null);

        return view('capacity.index', [
            'teams' => $teams,
            'team' => $team,
            'from' => $from,
            'capacity' => $team ? $this->capacity->forTeam($team, $from) : null,
        ]);
    }
}
