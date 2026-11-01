<?php

use App\Http\Controllers\Ai\AiAssistController;
use App\Http\Controllers\Ai\AiInsightController;
use App\Http\Controllers\Ai\AiUsageController;
use App\Http\Controllers\Ai\ReleaseNotesController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Claude features
|--------------------------------------------------------------------------
|
| Loaded by AiServiceProvider under the `web` middleware group. Every
| endpoint that calls Claude is throttled by the `ai` limiter; each
| controller authorizes against the record it reads, and release-scoped
| routes also pass `release-visible` (developers/QA: own teams only).
|
*/

Route::middleware('auth')->group(function () {
    // #9 Ask your workspace (page).
    Route::get('ask', [AiAssistController::class, 'askPage'])->name('ai.ask.page');

    Route::prefix('ai')->name('ai.')->group(function () {
        // Calls that spend tokens.
        Route::middleware('throttle:ai')->group(function () {
            // Background results (#3 risk brief, #4 standup digest, #7 document insights).
            Route::post('insights', [AiInsightController::class, 'store'])->name('insights.store');

            Route::middleware('release-visible')->group(function () {
                Route::post('releases/{release}/notes/draft', [ReleaseNotesController::class, 'draft'])->name('release-notes.draft'); // #1
                Route::post('releases/{release}/catch-up', [AiAssistController::class, 'catchUpRelease'])->name('catch-up.release'); // #6
            });

            Route::post('meeting-notes/{meetingNote}/actions', [AiAssistController::class, 'meetingActions'])->name('meeting-actions'); // #2
            Route::post('tasks/draft', [AiAssistController::class, 'draftTask'])->name('tasks.draft'); // #5
            Route::post('tasks/{task}/catch-up', [AiAssistController::class, 'catchUpTask'])->name('catch-up.task'); // #6
            Route::post('schedule', [AiAssistController::class, 'schedule'])->name('schedule'); // #8
            Route::post('ask', [AiAssistController::class, 'ask'])->name('ask'); // #9
            Route::post('performance/evidence', [AiAssistController::class, 'scoreEvidence'])->middleware('lead')->name('performance.evidence'); // #10
            Route::post('tasksheet/feedback', [AiAssistController::class, 'feedbackDraft'])->middleware('lead')->name('tasksheet.feedback'); // #11
        });

        // Writes of what Claude proposed (no model call).
        Route::put('releases/{release}/notes', [ReleaseNotesController::class, 'update'])->middleware('release-visible')->name('release-notes.update');
        Route::post('meeting-notes/{meetingNote}/tasks', [AiAssistController::class, 'storeMeetingTasks'])->name('meeting-actions.store');
        Route::post('tasks/{task}/subtasks', [AiAssistController::class, 'storeSubtasks'])->name('tasks.subtasks');
        Route::post('releases/{release}/documents/{document}/tasks', [AiAssistController::class, 'storeDocumentTasks'])
            ->middleware('release-visible')->scopeBindings()->name('documents.tasks');

        Route::get('insights/{insight}', [AiInsightController::class, 'show'])->name('insights.show');

        // Admin: usage, cost and per-feature switches.
        Route::middleware('admin')->group(function () {
            Route::get('usage', [AiUsageController::class, 'index'])->name('usage');
            Route::post('features/{feature}', [AiUsageController::class, 'toggle'])->name('features.toggle');
            Route::put('features/{feature}/model', [AiUsageController::class, 'configure'])->name('features.configure');
            Route::put('models', [AiUsageController::class, 'configureAll'])->name('features.configure-all');
        });
    });
});
