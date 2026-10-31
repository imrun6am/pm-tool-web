<?php

use App\Http\Controllers\Api\V1\MetaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1
|--------------------------------------------------------------------------
|
| Mounted at /api/v1 with the `api-v1.` route-name prefix (see routes/api.php).
| Everything below is split by domain into its own file, so a change to, say,
| release planning never means scrolling past the calendar.
|
| Two middleware run on every authenticated route:
|   auth:sanctum  — bearer-token authentication, no session, no CSRF
|   active-api    — revokes the token and refuses the request if the account
|                   has since been deactivated or deleted
|
| Role gating uses the SAME middleware as routes/web.php (`lead`,
| `manage-users`, `manage-releases`, `full-access`), which is what keeps the
| desktop client and the web app honest about who may do what.
|
*/

// ---------------------------------------------------------------------------
// Public — no token required.
// ---------------------------------------------------------------------------
Route::prefix('auth')->as('auth.')->group(base_path('routes/api/v1/auth.php'));

// ---------------------------------------------------------------------------
// Authenticated.
// ---------------------------------------------------------------------------
Route::middleware(['auth:sanctum', 'active-api'])->group(function () {
    // Domain enumerations for the client's pickers and menus.
    Route::get('meta', [MetaController::class, 'index'])->name('meta');

    Route::group([], base_path('routes/api/v1/account.php'));
    Route::group([], base_path('routes/api/v1/workspace.php'));
    Route::group([], base_path('routes/api/v1/releases.php'));
    Route::group([], base_path('routes/api/v1/collaboration.php'));
    Route::group([], base_path('routes/api/v1/tasksheet.php'));
    Route::group([], base_path('routes/api/v1/performance.php'));
    Route::group([], base_path('routes/api/v1/insights.php'));
});
