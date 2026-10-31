<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProjectRequest;
use App\Models\Project;
use App\Services\ProjectService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function __construct(private readonly ProjectService $projects) {}

    public function index(): View
    {
        return view('projects.index', [
            'projects' => $this->projects->filtered()->get(),
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

    public function show(Project $project): View
    {
        $releases = $this->projects->releasesWithProgress($project);

        return view('projects.show', [
            'project' => $project,
            'releases' => $releases,
            'stats' => $this->projects->analytics($project, $releases),
            'activities' => $this->projects->recentActivity($project, $releases),
        ]);
    }

    public function edit(Project $project): View
    {
        return view('projects.edit', compact('project'));
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
