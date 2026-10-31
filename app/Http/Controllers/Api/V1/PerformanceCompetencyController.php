<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\PerformanceCompetencyRequest;
use App\Http\Resources\V1\PerformanceCompetencyResource;
use App\Models\PerformanceCompetency;
use App\Services\PerformanceCompetencyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The competency catalog, behind the `manage-competencies` gate (org-level
 * leads only — team leads evaluate, they do not reconfigure the framework they
 * are evaluated against).
 */
class PerformanceCompetencyController extends ApiController
{
    public function __construct(private readonly PerformanceCompetencyService $competencies) {}

    /** The catalog is small and order matters — returned whole, unpaginated. */
    public function index(Request $request): JsonResponse
    {
        $catalog = $this->competencies->catalog([
            'cadence' => $request->input('cadence'),
            'active_only' => $request->boolean('active_only'),
        ])->get();

        return $this->ok(PerformanceCompetencyResource::collection($catalog));
    }

    public function show(PerformanceCompetency $competency): JsonResponse
    {
        return $this->ok(new PerformanceCompetencyResource($competency->loadCount('scores')));
    }

    public function store(PerformanceCompetencyRequest $request): JsonResponse
    {
        $competency = $this->competencies->create($request->validated());

        return $this->created(new PerformanceCompetencyResource($competency), 'Competency added.');
    }

    public function update(PerformanceCompetencyRequest $request, PerformanceCompetency $competency): JsonResponse
    {
        $this->competencies->update($competency, $request->validated());

        return $this->ok(new PerformanceCompetencyResource($competency), 'Competency updated.');
    }

    /** Flip active/inactive — the safe way to retire a scored competency. */
    public function toggle(PerformanceCompetency $competency): JsonResponse
    {
        $this->competencies->toggle($competency);

        return $this->ok(
            new PerformanceCompetencyResource($competency),
            $competency->active ? 'Competency activated.' : 'Competency deactivated.'
        );
    }

    public function destroy(PerformanceCompetency $competency): JsonResponse
    {
        abort_if(
            ! $this->competencies->isDeletable($competency),
            422,
            'This competency has recorded scores — deactivate it instead of deleting.'
        );

        $competency->delete();

        return $this->message('Competency deleted.');
    }
}
