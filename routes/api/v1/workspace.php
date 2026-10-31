<?php

use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\TeamController;
use App\Http\Controllers\Api\V1\TeamMemberController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Workspace — projects, teams, users
|--------------------------------------------------------------------------
|
| Mirrors routes/web.php: reading projects and teams is a planning surface
| (`full-access`, so developers and QA are out), writing them is the
| leadership tier (`lead`), team membership is `manage-releases`, and user
| administration is `manage-users`.
|
*/

/*
 | Projects & teams — reads. Planning surfaces, hidden from limited roles.
 */
Route::middleware('full-access')->group(function () {
    Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('projects/{project}', [ProjectController::class, 'show'])->name('projects.show');

    Route::get('teams', [TeamController::class, 'index'])->name('teams.index');
    Route::get('teams/{team}', [TeamController::class, 'show'])->name('teams.show');
    Route::get('teams/{team}/members', [TeamMemberController::class, 'index'])->name('teams.members.index');
});

/*
 | Projects & teams — writes. Leadership tier.
 */
Route::middleware('lead')->group(function () {
    Route::post('projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::put('projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::delete('projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');
    Route::post('projects/{project}/archive', [ProjectController::class, 'archive'])->name('projects.archive');
    Route::post('projects/{project}/restore', [ProjectController::class, 'restore'])->name('projects.restore');

    Route::post('teams', [TeamController::class, 'store'])->name('teams.store');
    Route::put('teams/{team}', [TeamController::class, 'update'])->name('teams.update');
    Route::delete('teams/{team}', [TeamController::class, 'destroy'])->name('teams.destroy');
    Route::post('teams/{team}/archive', [TeamController::class, 'archive'])->name('teams.archive');
    Route::post('teams/{team}/restore', [TeamController::class, 'restore'])->name('teams.restore');
    Route::put('teams/{team}/lead', [TeamMemberController::class, 'updateLead'])->name('teams.lead.update');
});

/*
 | Team membership — the release-planning tier.
 */
Route::middleware('manage-releases')->group(function () {
    Route::post('teams/{team}/members', [TeamMemberController::class, 'store'])->name('teams.members.store');
    Route::delete('teams/{team}/members/{user}', [TeamMemberController::class, 'destroy'])
        ->name('teams.members.destroy');
});

/*
 | User administration.
 |
 | `users/stats` is registered before `users/{user}` so the literal segment
 | is not swallowed by the model binding.
 */
Route::middleware('manage-users')->group(function () {
    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::get('users/stats', [UserController::class, 'stats'])->name('users.stats');
    Route::post('users', [UserController::class, 'store'])->name('users.store');
    Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
    Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::post('users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle');
    Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
});
