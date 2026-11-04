<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ReadsTaskFilters;
use App\Http\Requests\BulkUpdateTasksRequest;
use App\Http\Requests\TaskRequest;
use App\Models\Activity;
use App\Models\Label;
use App\Models\Release;
use App\Models\Task;
use App\Models\TaskTimeLog;
use App\Models\Team;
use App\Models\User;
use App\Services\SavedViewService;
use App\Services\TaskDependencyService;
use App\Services\TaskService;
use App\Services\TaskTimeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TaskController extends Controller
{
    use ReadsTaskFilters;

    private const PER_PAGE = 50;

    public function __construct(
        private readonly TaskService $tasks,
        private readonly TaskTimeService $time,
        private readonly TaskDependencyService $dependencies,
        private readonly SavedViewService $savedViews,
    ) {}

    public function index(Request $request): View
    {
        $viewer = $request->user();
        $limited = $viewer->hasLimitedAccess();
        $teamIds = $limited ? $viewer->currentTeamIds() : null;
        $filters = $this->defaultToOwnTasks($this->taskListFilters($request), $request);

        return view('tasks.index', [
            'tasks' => $this->tasks->filtered($filters, $viewer)->paginate(self::PER_PAGE)->withQueryString(),
            'filters' => $filters,
            'releases' => Release::visibleTo($viewer)->orderBy('year', 'desc')->orderBy('name')->get(),
            'teams' => Team::when($limited, fn ($q) => $q->whereIn('id', $teamIds))->orderBy('name')->get(),
            'users' => User::visibleTo($viewer)->active()->orderBy('name')->get(),
            'labels' => Label::orderBy('name')->get(),
            'savedViews' => $this->savedViews->forPage($viewer, 'tasks'),
        ]);
    }

    public function bulkUpdate(BulkUpdateTasksRequest $request): RedirectResponse
    {
        $count = $this->tasks->bulkUpdate($request->tasks(), $request->changes());

        return back()->with('success', $count.' '.Str::plural('task', $count).' updated.');
    }

    public function store(TaskRequest $request, Release $release): RedirectResponse
    {
        $this->tasks->createForRelease($release, $request->validated(), $request->user());

        return back()->with('success', 'Task added.');
    }

    public function storeSubtask(TaskRequest $request, Task $task): RedirectResponse
    {
        // One level of nesting only. The web flow prefers a flash message over
        // the API's 422, so the guard is checked here before delegating.
        if ($task->isSubtask()) {
            return back()->with('error', 'A subtask cannot have its own subtasks.');
        }

        $this->tasks->createSubtask($task, $request->validated(), $request->user());

        return back()->with('success', 'Subtask added.');
    }

    public function show(Request $request, Task $task): View
    {
        $this->authorize('view', $task);

        $viewer = $request->user();
        $limited = $viewer->hasLimitedAccess();
        $teamIds = $limited ? $viewer->currentTeamIds() : null;

        $task->load(['release.project', 'release.team', 'team', 'assignee', 'creator',
            'subtasks.assignee', 'comments.user', 'parent', 'labels']);
        $this->dependencies->loadLinks($task, $viewer);

        return view('tasks.show', [
            'task' => $task,
            'users' => $this->tasks->assignableUsers($task, $viewer),
            // Developers and QA pick only among their own teams' releases and
            // teams, plus the task's current ones so a save never drops them.
            'releases' => Release::visibleTo($viewer, $task->release_id)
                ->orderBy('year', 'desc')->orderBy('name')->get(),
            'teams' => Team::when($limited, fn ($q) => $q->where(fn ($w) => $w
                ->whereIn('id', $teamIds)->orWhereKey($task->team_id)))
                ->orderBy('name')->get(),
            'teamPicker' => ! $limited || $teamIds->count() > 1,
            'labels' => Label::orderBy('name')->get(),
            'timeSummary' => $this->time->summary($task),
            'timeEntries' => $viewer->can('viewEntries', [TaskTimeLog::class, $task]) ? $this->time->entries($task) : collect(),
            // The task's own history. Activity is a planning surface, so it
            // stays hidden from developers and QA, as on release pages.
            'activities' => $limited ? collect() : Activity::query()
                ->where('subject_type', $task->getMorphClass())
                ->where('subject_id', $task->getKey())
                ->with('causer')
                ->latest()
                ->limit(10)
                ->get(),
        ]);
    }

    /** The side panel's content; it only makes sense inside its Turbo Frame. */
    public function panel(Request $request, Task $task): View|RedirectResponse
    {
        $this->authorize('view', $task);

        if (! $request->hasHeader('Turbo-Frame')) {
            return redirect()->route('tasks.show', $task);
        }

        $viewer = $request->user();

        $task->load(['assignee', 'subtasks', 'comments.user', 'labels', 'team', 'release']);
        $this->dependencies->loadLinks($task, $viewer);

        return view('tasks.panel', [
            'task' => $task,
            'users' => $this->tasks->assignableUsers($task, $viewer),
            'labels' => Label::orderBy('name')->get(),
            'timeSummary' => $this->time->summary($task),
            'timeEntries' => $viewer->can('viewEntries', [TaskTimeLog::class, $task]) ? $this->time->entries($task) : collect(),
        ]);
    }

    public function update(TaskRequest $request, Task $task): RedirectResponse
    {
        $this->tasks->update($task, $request->validated());

        return back()->with('success', 'Task updated.');
    }

    public function updateStatus(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Task::STATUSES))],
        ]);

        $this->tasks->changeStatus($task, $data['status']);

        return back()->with('success', 'Status updated.');
    }

    public function destroy(Task $task): RedirectResponse
    {
        $this->authorize('delete', $task);

        $wasSubtask = $task->isSubtask();
        $task->delete();

        return back()->with('success', $wasSubtask ? 'Subtask deleted.' : 'Task deleted.');
    }
}
