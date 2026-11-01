<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProjectRequest;
use App\Models\Project;
use App\Services\OverlapChecker;
use App\Services\ProjectService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function __construct(private readonly ProjectService $projects) {}

    public function index(Request $request): View
    {
        $filters = [
            'search' => trim((string) $request->string('search')) ?: null,
            'status' => in_array($request->input('status'), ['active', 'archived'], true) ? $request->input('status') : null,
            'sort' => array_key_exists((string) $request->input('sort'), ProjectService::SORTS) ? $request->input('sort') : 'name',
        ];

        $projects = $this->projects->filtered($filters)->get();

        return view('projects.index', [
            'projects' => $projects,
            'summaries' => $this->projects->cardSummaries($projects),
            'overview' => $this->projects->overview(),
            'filters' => $filters,
            'sorts' => ProjectService::SORTS,
        ]);
    }

    public function create(): View
    {
        return view('projects.create', ['project' => new Project(['color' => '#4f46e5'])]);
    }

    public function store(ProjectRequest $request): RedirectResponse
    {
        $project = Project::create($request->validated());

        return redirect()->route('projects.index')
            ->with('success', "Project “{$project->name}” created.");
    }

    public function show(Project $project, OverlapChecker $overlap): View
    {
        $releases = $this->projects->releasesWithProgress($project);

        return view('projects.show', [
            'project' => $project,
            'releases' => $releases,
            'conflicts' => $overlap->flagConflicts($releases->whereNull('completed_at')->values()),
            'stats' => $this->projects->analytics($project, $releases),
            'activities' => $this->projects->recentActivity($project, $releases),
            'deletable' => $releases->isEmpty(),
        ]);
    }

    public function edit(Project $project): View
    {
        return view('projects.edit', [
            'project' => $project,
            'deletable' => $this->projects->isDeletable($project),
        ]);
    }

    public function update(ProjectRequest $request, Project $project): RedirectResponse
    {
        $project->update($request->validated());

        return redirect()->route('projects.index')
            ->with('success', "Project “{$project->name}” updated.");
    }

    public function destroy(Project $project): RedirectResponse
    {
        if (! $this->projects->isDeletable($project)) {
            return back()->with('error', 'This project has releases and cannot be deleted. Archive it instead.');
        }

        $name = $project->name;
        $project->delete();

        return redirect()->route('projects.index')
            ->with('success', "Project “{$name}” deleted.");
    }

    public function archive(Project $project): RedirectResponse
    {
        $this->projects->archive($project);

        return back()->with('success', "Project “{$project->name}” archived.");
    }

    public function restore(Project $project): RedirectResponse
    {
        $this->projects->restore($project);

        return back()->with('success', "Project “{$project->name}” restored.");
    }
}
