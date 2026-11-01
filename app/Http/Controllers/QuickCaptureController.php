<?php

namespace App\Http\Controllers;

use App\Http\Requests\QuickTaskRequest;
use App\Services\QuickCaptureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class QuickCaptureController extends Controller
{
    public function __construct(private readonly QuickCaptureService $capture) {}

    /** The full-page form: direct links, and the no-JavaScript fallback. */
    public function create(Request $request): View
    {
        $options = $this->capture->options($request->user());

        // The capture dialog fetches just the form each time it opens.
        if ($request->hasHeader('X-Quick-Capture')) {
            return view('tasks._quick-form', $options + ['inDialog' => true]);
        }

        return view('tasks.quick', $options);
    }

    /** JSON for the capture dialog; a redirect to the task for the page form. */
    public function store(QuickTaskRequest $request): JsonResponse|RedirectResponse
    {
        $task = $this->capture->capture($request->validated(), $request->user());
        $message = 'Added “'.Str::limit($task->title, 60).'” to the board.';

        if ($request->expectsJson()) {
            return response()->json([
                'task' => ['id' => $task->id, 'title' => $task->title, 'url' => route('tasks.show', $task)],
                'message' => $message,
            ], 201);
        }

        return redirect()->route('tasks.show', $task)->with('success', $message);
    }
}
