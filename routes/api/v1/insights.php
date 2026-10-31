<?php

use App\Http\Controllers\Api\V1\ActivityController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Insights — the activity feed
|--------------------------------------------------------------------------
|
| A planning surface, so `full-access` keeps developers and QA out, matching
| routes/web.php. Note that performance scores never appear here: the model
| deliberately does not record activity, because this feed is readable by
| every full-access user and ratings are not.
|
| `activities/stats` is registered before any `{activity}` binding would be,
| so the literal segment cannot be swallowed.
|
*/

Route::middleware('full-access')->group(function () {
    Route::get('activities', [ActivityController::class, 'index'])->name('activities.index');
    Route::get('activities/stats', [ActivityController::class, 'stats'])->name('activities.stats');
});
