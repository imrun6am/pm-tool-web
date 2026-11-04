<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\ReleaseTemplateTaskRequest;
use App\Http\Resources\V1\ReleaseTemplateResource;
use App\Models\Release;
use App\Models\ReleaseTemplate;
use App\Models\ReleaseTemplateTask;
use App\Services\ReleaseTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/** Standard task sets for releases. Leads only. */
class ReleaseTemplateController extends ApiController
{
    public function __construct(private readonly ReleaseTemplateService $templates) {}

    public function index(): AnonymousResourceCollection
    {
        return ReleaseTemplateResource::collection($this->templates->all());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100', Rule::unique('release_templates', 'name')]]);

        return $this->created(new ReleaseTemplateResource($this->templates->create($data['name'], $request->user())->load('tasks')), 'Template added.');
    }

    public function destroy(ReleaseTemplate $template): JsonResponse
    {
        $this->templates->delete($template);

        return $this->message('Template removed.');
    }

    public function storeTask(ReleaseTemplateTaskRequest $request, ReleaseTemplate $template): JsonResponse
    {
        $this->templates->addTask($template, $request->validated());

        return $this->created(new ReleaseTemplateResource($template->load('tasks')), 'Task added to the template.');
    }

    public function destroyTask(ReleaseTemplate $template, ReleaseTemplateTask $task): JsonResponse
    {
        abort_unless($task->release_template_id === $template->id, 404);

        $this->templates->removeTask($task);

        return $this->message('Task removed from the template.');
    }

    public function apply(Request $request, Release $release): JsonResponse
    {
        $data = $request->validate(['template_id' => ['required', 'integer', Rule::exists('release_templates', 'id')]]);

        $count = $this->templates->apply(ReleaseTemplate::with('tasks')->findOrFail($data['template_id']), $release, $request->user());

        return $this->ok(['created' => $count], $count.' tasks added from the template.');
    }
}
