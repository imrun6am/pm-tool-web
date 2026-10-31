<?php

use App\Http\Controllers\Api\V1\CommentController;
use App\Http\Controllers\Api\V1\ReleaseController;
use App\Http\Controllers\Api\V1\ReleaseDocumentController;
use App\Http\Controllers\Api\V1\ReleaseOffDayController;
use App\Http\Controllers\Api\V1\ReleasePhaseController;
use App\Http\Controllers\Api\V1\TaskController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Release planning
|--------------------------------------------------------------------------
|
| Mirrors routes/web.php exactly:
|
|   - The releases *list* is a planning overview → `full-access`.
|   - A single release *detail* is open to every authenticated user, so
|     developers and QA can see the release they are working on (read-only in
|     effect, since every write below is gated).
|   - Planning writes (create, edit, complete, off-days, document deletion)
|     are the leadership tier → `manage-releases`.
|   - Document *upload* is open to contributors; the viewer exclusion lives in
|     ReleaseDocumentRequest::authorize().
|
*/

/*
 | Planning writes. Registered first so they are unmistakably scoped.
 */
Route::middleware('manage-releases')->group(function () {
    Route::post('releases', [ReleaseController::class, 'store'])->name('releases.store');
    Route::put('releases/{release}', [ReleaseController::class, 'update'])->name('releases.update');
    Route::delete('releases/{release}', [ReleaseController::class, 'destroy'])->name('releases.destroy');
    Route::post('releases/{release}/complete', [ReleaseController::class, 'complete'])->name('releases.complete');
    Route::post('releases/{release}/reopen', [ReleaseController::class, 'reopen'])->name('releases.reopen');

    // Off-days are part of the plan.
    Route::post('releases/{release}/off-days', [ReleaseOffDayController::class, 'store'])
        ->name('releases.off-days.store');
    Route::post('releases/{release}/off-days/weekends', [ReleaseOffDayController::class, 'markWeekends'])
        ->name('releases.off-days.weekends');
    Route::delete('releases/{release}/off-days/{offDay}', [ReleaseOffDayController::class, 'destroy'])
        ->name('releases.off-days.destroy')
        ->scopeBindings();

    // Deleting a document stays lead-only; uploading is opened below.
    Route::delete('releases/{release}/documents/{document}', [ReleaseDocumentController::class, 'destroy'])
        ->name('releases.documents.destroy')
        ->scopeBindings();
});

/*
 | The releases list — a planning overview, hidden from limited roles.
 */
Route::middleware('full-access')->group(function () {
    Route::get('releases', [ReleaseController::class, 'index'])->name('releases.index');
});

/*
 | Release detail and collaboration — every authenticated user.
 */
Route::get('releases/{release}', [ReleaseController::class, 'show'])->name('releases.show');
Route::get('releases/{release}/conflicts', [ReleaseController::class, 'conflicts'])->name('releases.conflicts');
Route::get('releases/{release}/phases', [ReleasePhaseController::class, 'index'])->name('releases.phases.index');
Route::get('releases/{release}/off-days', [ReleaseOffDayController::class, 'index'])->name('releases.off-days.index');

Route::get('releases/{release}/documents', [ReleaseDocumentController::class, 'index'])
    ->name('releases.documents.index');
Route::get('releases/{release}/documents/{document}', [ReleaseDocumentController::class, 'download'])
    ->name('releases.documents.download')
    ->scopeBindings();
// Upload is open to contributors; a non-viewer guard lives in the request.
Route::post('releases/{release}/documents', [ReleaseDocumentController::class, 'store'])
    ->name('releases.documents.store');

Route::get('releases/{release}/tasks', [TaskController::class, 'indexForRelease'])->name('releases.tasks.index');
Route::post('releases/{release}/tasks', [TaskController::class, 'store'])->name('releases.tasks.store');

Route::get('releases/{release}/comments', [CommentController::class, 'indexForRelease'])
    ->name('releases.comments.index');
Route::post('releases/{release}/comments', [CommentController::class, 'storeForRelease'])
    ->name('releases.comments.store');
