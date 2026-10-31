<?php

namespace App\Http\Controllers;

use App\Http\Requests\PerformanceCompetencyRequest;
use App\Models\PerformanceCompetency;
use App\Services\PerformanceCompetencyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Competency-catalog management. Every action is behind the
 * `can:manage-competencies` gate (org-level leads only).
 */
class PerformanceCompetencyController extends Controller
{
    public function __construct(private readonly PerformanceCompetencyService $competencies) {}

    public function index(): View
    {
        return view('performance.competencies.index', [
            'competencies' => $this->competencies->catalog()->get(),
            'categories' => PerformanceCompetency::CATEGORIES,
        ]);
    }

    public function create(): View
    {
        return view('performance.competencies.create', [
            'competency' => new PerformanceCompetency([
                'category' => PerformanceCompetency::CATEGORY_TECHNICAL,
                'role_scope' => PerformanceCompetency::SCOPE_BOTH,
                'cadence' => PerformanceCompetency::CADENCE_WEEKLY,
                'weight' => 1,
                'active' => true,
            ]),
        ]);
    }

    public function store(PerformanceCompetencyRequest $request): RedirectResponse
    {
        $this->competencies->create($request->validated());

        return redirect()->route('performance.competencies.index')
            ->with('success', 'Competency added.');
    }

    public function edit(PerformanceCompetency $competency): View
    {
        return view('performance.competencies.edit', ['competency' => $competency]);
    }

    public function update(PerformanceCompetencyRequest $request, PerformanceCompetency $competency): RedirectResponse
    {
        $this->competencies->update($competency, $request->validated());

        return redirect()->route('performance.competencies.index')
            ->with('success', 'Competency updated.');
    }

    /** Flip active/inactive — the safe way to retire a scored competency. */
    public function toggle(PerformanceCompetency $competency): RedirectResponse
    {
        $this->competencies->toggle($competency);

        return redirect()->route('performance.competencies.index')
            ->with('success', $competency->active ? 'Competency activated.' : 'Competency deactivated.');
    }

    public function destroy(PerformanceCompetency $competency): RedirectResponse
    {
        // Preserve history: a scored competency can only be deactivated.
        if (! $this->competencies->isDeletable($competency)) {
            return redirect()->route('performance.competencies.index')
                ->with('error', 'This competency has recorded scores — deactivate it instead of deleting.');
        }

        $competency->delete();

        return redirect()->route('performance.competencies.index')
            ->with('success', 'Competency deleted.');
    }
}
