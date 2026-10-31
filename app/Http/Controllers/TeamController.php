<?php

namespace App\Http\Controllers;

use App\Http\Requests\TeamRequest;
use App\Models\Team;
use App\Models\User;
use App\Services\OverlapChecker;
use App\Services\TeamService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function __construct(private readonly TeamService $teams) {}

    public function index(): View
    {
        return view('teams.index', [
            'teams' => $this->teams->filtered()->get(),
        ]);
    }

    public function create(): View
    {
        return view('teams.create', [
            'team' => new Team(['color' => '#0891b2']),
            'users' => $this->teams->leadCandidates(),
        ]);
    }

    public function store(TeamRequest $request): RedirectResponse
    {
        $team = Team::create($request->validated());

        return redirect()->route('teams.index')
            ->with('success', "Team “{$team->name}” created.");
    }

    public function show(Team $team, OverlapChecker $overlap): View
    {
        $team->load(['releases.project', 'releases.team', 'members', 'teamLead']);
        $releases = $team->releases->sortBy('start_date')->values();

        return view('teams.show', [
            'team' => $team,
            'releases' => $releases,
            'conflicts' => $overlap->flagConflicts($releases),
            'assignableUsers' => $this->teams->assignableUsers($team),
            'leadCandidates' => $this->teams->leadCandidates(),
        ]);
    }

    public function edit(Team $team): View
    {
        return view('teams.edit', [
            'team' => $team,
            'users' => $this->teams->leadCandidates(),
        ]);
    }

    public function update(TeamRequest $request, Team $team): RedirectResponse
    {
        $team->update($request->validated());

        return redirect()->route('teams.index')
            ->with('success', "Team “{$team->name}” updated.");
    }

    public function destroy(Team $team): RedirectResponse
    {
        if (! $this->teams->isDeletable($team)) {
            return back()->with('error', 'This team owns releases and cannot be deleted. Archive it instead.');
        }

        $name = $team->name;
        $team->delete();

        return redirect()->route('teams.index')
            ->with('success', "Team “{$name}” deleted.");
    }

    public function archive(Team $team): RedirectResponse
    {
        $this->teams->archive($team);

        return back()->with('success', "Team “{$team->name}” archived.");
    }

    public function restore(Team $team): RedirectResponse
    {
        $this->teams->restore($team);

        return back()->with('success', "Team “{$team->name}” restored.");
    }

    public function addMember(Request $request, Team $team): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', Rule::exists('users', 'id')->whereNull('deactivated_at')],
        ]);

        $user = $this->teams->addMember($team, $data['user_id']);

        return back()->with('success', "{$user->name} added to {$team->name}.");
    }

    public function removeMember(Team $team, User $user): RedirectResponse
    {
        $this->teams->removeMember($team, $user);

        return back()->with('success', "{$user->name} removed from {$team->name}.");
    }

    /** Assign (or clear) the team lead directly from the team page. */
    public function updateLead(Request $request, Team $team): RedirectResponse
    {
        $data = $request->validate([
            'team_lead_id' => ['nullable', Rule::exists('users', 'id')->whereNull('deactivated_at')],
        ]);

        $this->teams->updateLead($team, $data['team_lead_id'] ?? null);

        return back()->with('success', $team->team_lead_id
            ? "{$team->teamLead->name} set as lead of {$team->name}."
            : "Team lead cleared for {$team->name}.");
    }
}
