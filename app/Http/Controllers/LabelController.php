<?php

namespace App\Http\Controllers;

use App\Http\Requests\LabelRequest;
use App\Models\Label;
use App\Services\LabelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LabelController extends Controller
{
    public function __construct(private readonly LabelService $labels) {}

    public function index(): View
    {
        return view('labels.index', ['labels' => $this->labels->withUsage()]);
    }

    public function store(LabelRequest $request): RedirectResponse
    {
        $this->labels->create($request->validated());

        return back()->with('success', 'Label added.');
    }

    public function update(LabelRequest $request, Label $label): RedirectResponse
    {
        $this->labels->update($label, $request->validated());

        return back()->with('success', 'Label updated.');
    }

    public function destroy(Label $label): RedirectResponse
    {
        $this->labels->delete($label);

        return back()->with('success', 'Label deleted.');
    }
}
