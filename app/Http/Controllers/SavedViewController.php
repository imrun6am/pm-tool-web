<?php

namespace App\Http\Controllers;

use App\Http\Requests\SavedViewRequest;
use App\Models\SavedView;
use App\Services\SavedViewService;
use Illuminate\Http\RedirectResponse;

class SavedViewController extends Controller
{
    public function __construct(private readonly SavedViewService $views) {}

    public function store(SavedViewRequest $request): RedirectResponse
    {
        $this->views->save($request->user(), $request->validated('page'), $request->validated('name'), $request->validated('filters') ?? [], $request->boolean('shared'));

        return back()->with('success', 'View saved.');
    }

    public function destroy(SavedView $view): RedirectResponse
    {
        $this->authorize('delete', $view);

        $this->views->delete($view);

        return back()->with('success', 'View removed.');
    }
}
