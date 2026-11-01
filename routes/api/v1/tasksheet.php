<?php

use App\Http\Controllers\Api\V1\TasksheetController;
use App\Http\Controllers\Api\V1\TasksheetExportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Team tasksheet
|--------------------------------------------------------------------------
|
| Open to every authenticated user. The finer rules are enforced below the
| routing layer, where they belong:
|
|   - TasksheetEntryPolicy limits saving to the row's own member (while they
|     are still on the team) or a lead.
|   - The lead-only `feedback` column is ignored on a non-lead's write and
|     omitted from a non-lead's read (TasksheetEntryResource).
|   - A member's history is readable by that member and by leads only,
|     checked in the controller.
|   - PDF exports are queued and private to their requester
|     (TasksheetExportPolicy). `report` is retired: it answers 410 and
|     points at `exports`, so the API never renders a PDF inline.
|
| `users/{member}` is registered with `withTrashed()` so a departed member's
| history stays reachable — the sheet is a record, and deleting someone must
| not erase the days they worked.
|
*/

Route::get('tasksheet', [TasksheetController::class, 'index'])->name('tasksheet.index');
Route::put('tasksheet/entries', [TasksheetController::class, 'upsert'])->name('tasksheet.entries.upsert');
Route::post('tasksheet/standup', [TasksheetController::class, 'verifyStandup'])->name('tasksheet.standup');
Route::get('tasksheet/report', [TasksheetController::class, 'report'])->name('tasksheet.report');

Route::get('tasksheet/exports', [TasksheetExportController::class, 'index'])->name('tasksheet.exports.index');
Route::post('tasksheet/exports', [TasksheetExportController::class, 'store'])->name('tasksheet.exports.store');
Route::get('tasksheet/exports/status', [TasksheetExportController::class, 'status'])->name('tasksheet.exports.status');
Route::get('tasksheet/exports/{export}', [TasksheetExportController::class, 'show'])->name('tasksheet.exports.show')->whereNumber('export');
Route::get('tasksheet/exports/{export}/download', [TasksheetExportController::class, 'download'])->name('tasksheet.exports.download');
Route::post('tasksheet/exports/{export}/retry', [TasksheetExportController::class, 'retry'])->name('tasksheet.exports.retry');
Route::delete('tasksheet/exports/{export}', [TasksheetExportController::class, 'destroy'])->name('tasksheet.exports.destroy');

Route::get('tasksheet/users/{member}', [TasksheetController::class, 'user'])
    ->name('tasksheet.user')
    ->withTrashed();
