<?php

use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authentication — /api/v1/auth
|--------------------------------------------------------------------------
|
| Login is the only endpoint reachable without a token, and it is throttled
| per email + IP by the `login` limiter. There is deliberately no registration
| endpoint: self-registration is disabled product-wide, and accounts are
| created only through the user-management endpoints.
|
*/

Route::post('login', [AuthController::class, 'login'])
    ->middleware('throttle:login')
    ->name('login');

Route::middleware(['auth:sanctum', 'active-api'])->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('logout-all', [AuthController::class, 'logoutAll'])->name('logout-all');

    // Signed-in devices.
    Route::get('tokens', [AuthController::class, 'tokens'])->name('tokens.index');
    Route::delete('tokens/{token}', [AuthController::class, 'revokeToken'])->name('tokens.destroy');
});
