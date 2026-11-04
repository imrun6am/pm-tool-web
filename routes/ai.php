<?php

use App\Http\Controllers\Ai\AiAssistController;
use App\Http\Controllers\Ai\AiInsightController;
use App\Http\Controllers\Ai\AiUsageController;
use App\Http\Controllers\Ai\AssistantController;
use App\Http\Controllers\Ai\ReleaseNotesController;
use App\Http\Controllers\Ai\ReleaseStatusDraftController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| AI features
|--------------------------------------------------------------------------
|
| Loaded by AiServiceProvider under the `web` middleware group. Every
| endpoint that calls the AI is throttled by the `ai` limiter; each
| controller authorizes against the record it reads, and release-scoped
| routes also pass `release-visible` (developers/QA: own teams only).
|
*/

Route::middleware('auth')->group(function () {
    // #9 Ask your workspace (page).
    Route::get('ask', [AiAssistController::class, 'askPage'])->name('ai.ask.page');

    // The floating assistant. Only a person's confirm runs a change.
    Route::prefix('assistant')->name('assistant.')->group(function () {
        Route::get('panel', [AssistantController::class, 'panel'])->name('panel');
        Route::get('conversation', [AssistantController::class, 'conversation'])->name('conversation');
        Route::delete('conversation', [AssistantController::class, 'destroy'])->name('reset');
        Route::middleware('throttle:ai')->group(function () {
            Route::post('messages', [AssistantController::class, 'message'])->name('message');
            Route::post('continue', [AssistantController::class, 'resume'])->name('continue');
        });
        Route::post('actions/{action}/confirm', [AssistantController::class, 'confirm'])->name('actions.confirm');
        Route::post('actions/{action}/cancel', [AssistantController::class, 'cancel'])->name('actions.cancel');
    });

    Route::prefix('ai')->name('ai.')->group(function () {
        // Calls that spend tokens.
        Route::middleware('throttle:ai')->group(function () {
            // Background results (#3 risk brief, #4 standup digest, #7 document insights).
            Route::post('insights', [AiInsightController::class, 'store'])->name('insights.store');

            Route::middleware('release-visible')->group(function () {
                Route::post('releases/{release}/notes/draft', [ReleaseNotesController::class, 'draft'])->name('release-notes.draft'); // #1
                Route::post('releases/{release}/status-update/draft', ReleaseStatusDraftController::class)->name('release-status.draft');
                Route::post('releases/{release}/catch-up', [AiAssistController::class, 'catchUpRelease'])->name('catch-up.release'); // #6
                Route::post('releases/{release}/retrospective', [AiAssistController::class, 'retrospective'])->name('release-retro');
            });

            Route::post('meeting-notes/{meetingNote}/actions', [AiAssistController::class, 'meetingActions'])->name('meeting-actions'); // #2
            Route::post('tasks/draft', [AiAssistController::class, 'draftTask'])->name('tasks.draft'); // #5
            Route::post('tasks/{task}/catch-up', [AiAssistController::class, 'catchUpTask'])->name('catch-up.task'); // #6
            Route::post('schedule', [AiAssistController::class, 'schedule'])->name('schedule'); // #8
            Route::post('ask', [AiAssistController::class, 'ask'])->name('ask'); // #9
            Route::post('performance/evidence', [AiAssistController::class, 'scoreEvidence'])->middleware('lead')->name('performance.evidence'); // #10
            Route::post('tasksheet/feedback', [AiAssistController::class, 'feedbackDraft'])->middleware('lead')->name('tasksheet.feedback'); // #11
            Route::post('tasksheet/draft', [AiAssistController::class, 'tasksheetDraft'])->name('tasksheet.draft');
            Route::post('tasks/{task}/suggest-assignee', [AiAssistController::class, 'suggestAssignee'])->name('tasks.suggest-assignee');
        });

        // Writes of what the AI proposed (no model call).
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
