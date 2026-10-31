<?php

use App\Http\Controllers\Api\V1\PerformanceCompetencyController;
use App\Http\Controllers\Api\V1\PerformanceController;
use App\Http\Controllers\Api\V1\PerformanceScoreController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Performance
|--------------------------------------------------------------------------
|
| Lead-only, in full. Scores are sensitive HR data: developers, QA, and
| viewers are blocked at the middleware, and team leads are further scoped
| inside the controllers to the teams they are the assigned lead of.
|
| The competency catalog is org-level configuration, gated one step tighter
| by `can:manage-competencies` (admin, CTO, tech lead) — team leads evaluate,
| they do not reconfigure the framework they are evaluated against.
|
*/

Route::middleware('lead')->prefix('performance')->as('performance.')->group(function () {
    Route::get('teams', [PerformanceController::class, 'teams'])->name('teams');
    Route::get('overview', [PerformanceController::class, 'overview'])->name('overview');

    // Evaluation grid + ratings upsert.
    Route::get('evaluate', [PerformanceScoreController::class, 'grid'])->name('evaluate');
    Route::put('scores', [PerformanceScoreController::class, 'upsert'])->name('scores.upsert');

    /*
     | Competency catalog — registered before `members/{user}` is irrelevant
     | here (different segment), but the literal `competencies` prefix is kept
     | above the member route for readability.
     */
    Route::middleware('can:manage-competencies')->group(function () {
        Route::get('competencies', [PerformanceCompetencyController::class, 'index'])->name('competencies.index');
        Route::post('competencies', [PerformanceCompetencyController::class, 'store'])->name('competencies.store');
        Route::get('competencies/{competency}', [PerformanceCompetencyController::class, 'show'])->name('competencies.show');
        Route::put('competencies/{competency}', [PerformanceCompetencyController::class, 'update'])->name('competencies.update');
        Route::post('competencies/{competency}/toggle', [PerformanceCompetencyController::class, 'toggle'])->name('competencies.toggle');
        Route::delete('competencies/{competency}', [PerformanceCompetencyController::class, 'destroy'])->name('competencies.destroy');
    });

    // A departed member's scorecard stays reachable.
    Route::get('members/{user}', [PerformanceController::class, 'member'])
        ->name('members.show')
        ->withTrashed();
});
