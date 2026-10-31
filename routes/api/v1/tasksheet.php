<?php

use App\Http\Controllers\Api\V1\TasksheetController;
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

Route::get('tasksheet/users/{member}', [TasksheetController::class, 'user'])
    ->name('tasksheet.user')
    ->withTrashed();
