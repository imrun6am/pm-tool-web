<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReleaseRequest;
use App\Models\Activity;
use App\Models\Project;
use App\Models\Release;
use App\Models\Team;
use App\Models\User;
use App\Services\ReleaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReleaseController extends Controller
{
    public function __construct(private readonly ReleaseService $releases) {}

    public function index(Request $request): View
    {
        $filters = [
            'status' => $request->input('status'), // active | completed | (all)
            'project_id' => $request->filled('project_id') ? (int) $request->integer('project_id') : null,
            'team_id' => $request->filled('team_id') ? (int) $request->integer('team_id') : null,
            'year' => $request->filled('year') ? (int) $request->integer('year') : null,
        ];

        return view('releases.index', [
            'releases' => $this->releases->filtered($filters)->get(),
            'projects' => Project::orderBy('name')->get(),
            'teams' => Team::orderBy('name')->get(),
            'years' => $this->releases->years(),
            'filters' => [
                'status' => $filters['status'],
                'projectId' => $filters['project_id'],
                'teamId' => $filters['team_id'],
                'year' => $filters['year'],
            ],
        ]);
    }

    public function complete(Request $request, Release $release): RedirectResponse
    {
        $data = $request->validate([
            'completion_notes' => ['nullable', 'string', 'max:10000'],
        ]);

        $this->releases->complete($release, $request->user(), $data['completion_notes'] ?? null);

        return back()->with('success', "Release “{$release->name}” marked complete.");
    }

    public function reopen(Release $release): RedirectResponse
    {
        $this->releases->reopen($release);

        return back()->with('success', "Release “{$release->name}” reopened.");
    }

    public function create(): View
    {
        return view('releases.create', [
            'release' => new Release([
                'year' => (int) now()->year,
                'quarter' => (int) ceil(now()->month / 3),
            ]),
            'projects' => Project::active()->orderBy('name')->get(),
            'teams' => Team::active()->orderBy('name')->get(),
            'teamMembers' => $this->teamMembersMap(),
            'memberValues' => [],
            'phaseValues' => [],
            'offDayValues' => [],
        ]);
    }

    public function store(ReleaseRequest $request): RedirectResponse
    {
        $release = $this->releases->create(
            $request->validated(),
            $request->input('phases', []),
            $request->input('off_days', []),
            $request->input('members', []),
        );

        return redirect()->route('releases.show', $release)
            ->with('success', "Release “{$release->name}” created.")
            ->with($this->overlapSession($release));
    }

    public function show(Release $release): View
    {
        $release->load([
            'project', 'team', 'phases', 'documents.uploader',
            'rootTasks.subtasks.assignee', 'rootTasks.assignee', 'rootTasks.comments',
            'offDays', 'comments.user', 'members',
        ]);

        // Meeting notes the viewer may see (attendees-only ones are filtered out).
        $release->setRelation('meetingNotes', $release->meetingNotes()
            ->with('author')->visibleTo(request()->user())->get());

        $history = Activity::where('release_id', $release->id)
            ->with('causer')
            ->latest()
            ->limit(40)
            ->get();

        return view('releases.show', [
            'release' => $release,
            'conflicts' => $this->releases->conflictsFor($release),
            'history' => $history,
            'users' => $this->releases->assignableUsers($release),
            'releaseLinks' => $release->quickLinks()->with('author')
                ->visibleTo(request()->user())->get(),
        ]);
    }

    public function edit(Release $release): View
    {
        $release->load(['phases', 'members']);

        return view('releases.edit', [
            'release' => $release,
            'projects' => Project::active()->orderBy('name')->get(),
            'teams' => Team::active()->orderBy('name')->get(),
            'teamMembers' => $this->teamMembersMap(),
            'memberValues' => $release->members->pluck('id')->all(),
            'phaseValues' => $release->phases->keyBy('phase'),
            'offDayValues' => $release->offDays()->orderBy('date')->get()
                ->map(fn ($o) => ['date' => $o->date->toDateString(), 'reason' => $o->reason])->all(),
        ]);
    }

    public function update(ReleaseRequest $request, Release $release): RedirectResponse
    {
        $this->releases->update(
            $release,
            $request->validated(),
            $request->input('phases', []),
            $request->input('off_days', []),
            $request->input('members', []),
        );

        return redirect()->route('releases.show', $release)
            ->with('success', "Release “{$release->name}” updated.")
            ->with($this->overlapSession($release));
    }

    public function destroy(Release $release): RedirectResponse
    {
        $name = $release->name;
        $release->delete(); // cascades to phases + documents; model event removes files

        return redirect()->route('dashboard')
            ->with('success', "Release “{$name}” deleted.");
    }

    /**
     * Active teams mapped to their active members, for the release form's
     * dependent member picker: [teamId => [['id','name','role'], ...]].
     *
     * @return array<int, array<int, array{id: int, name: string, role: string}>>
     */
    private function teamMembersMap(): array
    {
        return Team::active()
            ->with(['members' => fn ($q) => $q->active()->orderBy('name')])
            ->get()
            ->mapWithKeys(fn (Team $team) => [
                $team->id => $team->members->map(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'role' => $u->roleLabel(),
                ])->values()->all(),
            ])
            ->all();
    }

    /**
     * Flash payload for the overlap warning: the message when the team is
     * double-booked, or an empty array so the key stays absent entirely.
     *
     * @return array<string, string>
     */
    private function overlapSession(Release $release): array
    {
        $warning = $this->releases->overlapMessage($release);

        return $warning ? ['overlap_warning' => $warning] : [];
    }
}
