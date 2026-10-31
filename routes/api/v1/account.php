<?php

use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| The signed-in account — /api/v1
|--------------------------------------------------------------------------
|
| `me` is the client's start-up call: it returns the user, their effective
| permission flags, their teams and the teams they lead, which is what the
| desktop navigation is built from.
|
| The dashboard lives here rather than under a role group because it serves
| both audiences: full-access roles get the planning timeline, developers and
| QA get the personal member view. The controller decides, so neither role
| needs a different URL.
|
*/

Route::get('me', [ProfileController::class, 'me'])->name('me');
Route::put('me', [ProfileController::class, 'update'])->name('me.update');
Route::put('me/password', [ProfileController::class, 'changePassword'])->name('me.password');

Route::get('dashboard', DashboardController::class)->name('dashboard');
