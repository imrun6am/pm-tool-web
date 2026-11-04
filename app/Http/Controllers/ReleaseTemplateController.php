<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReleaseTemplateTaskRequest;
use App\Models\Release;
use App\Models\ReleaseTemplate;
use App\Models\ReleaseTemplateTask;
use App\Services\ReleaseTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReleaseTemplateController extends Controller
{
    public function __construct(private readonly ReleaseTemplateService $templates) {}

    public function index(): View
    {
        return view('release-templates.index', ['templates' => $this->templates->all()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100', Rule::unique('release_templates', 'name')]]);

        $this->templates->create($data['name'], $request->user());

        return back()->with('success', 'Template added.');
    }

    public function destroy(ReleaseTemplate $template): RedirectResponse
    {
        $this->templates->delete($template);

        return back()->with('success', 'Template removed. Releases that used it keep their tasks.');
    }

    public function storeTask(ReleaseTemplateTaskRequest $request, ReleaseTemplate $template): RedirectResponse
    {
        $this->templates->addTask($template, $request->validated());

        return back()->with('success', 'Task added to the template.');
    }

    public function destroyTask(ReleaseTemplate $template, ReleaseTemplateTask $task): RedirectResponse
    {
        abort_unless($task->release_template_id === $template->id, 404);

        $this->templates->removeTask($task);

        return back()->with('success', 'Task removed from the template.');
    }

    public function apply(Request $request, Release $release): RedirectResponse
    {
        $data = $request->validate(['template_id' => ['required', 'integer', Rule::exists('release_templates', 'id')]]);

        $count = $this->templates->apply(ReleaseTemplate::with('tasks')->findOrFail($data['template_id']), $release, $request->user());

        return back()->with('success', $count ? $count.' '.Str::plural('task', $count).' added from the template.' : 'This release already has those tasks.');
    }
}
