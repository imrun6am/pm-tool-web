<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecurringTaskRequest;
use App\Models\RecurringTask;
use App\Models\Team;
use App\Models\User;
use App\Services\RecurringTaskService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RecurringTaskController extends Controller
{
    public function __construct(private readonly RecurringTaskService $recurring) {}

    public function index(): View
    {
        return view('recurring-tasks.index', [
            'rules' => $this->recurring->all(),
            'teams' => Team::orderBy('name')->get(),
            'users' => User::active()->orderBy('name')->get(),
        ]);
    }

    public function store(RecurringTaskRequest $request): RedirectResponse
    {
        $this->recurring->create($request->ruleAttributes(), $request->user());

        return back()->with('success', 'Recurring task added.');
    }

    public function update(Request $request, RecurringTask $recurringTask): RedirectResponse
    {
        $this->recurring->setActive($recurringTask, $request->boolean('is_active'));

        return back()->with('success', $recurringTask->is_active ? 'Recurring task resumed.' : 'Recurring task paused.');
    }

    public function destroy(RecurringTask $recurringTask): RedirectResponse
    {
        $this->recurring->delete($recurringTask);

        return back()->with('success', 'Recurring task removed. Tasks it already made stay.');
    }
}
