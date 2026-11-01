<?php

use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\OnboardingController;
use App\Http\Controllers\Api\V1\PreferenceController;
use App\Http\Controllers\Api\V1\TaskSearchController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Engagement — /api/v1
|--------------------------------------------------------------------------
|
| The caller's own inbox, preferences and getting-started checklist, plus
| task search (scoped by Task::visibleTo). Open to every role: nothing here
| reaches another user's data.
|
*/

Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
Route::post('notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');

Route::get('search/tasks', TaskSearchController::class)->name('search.tasks');

Route::put('me/preferences', [PreferenceController::class, 'update'])->name('me.preferences');

Route::get('onboarding', [OnboardingController::class, 'show'])->name('onboarding.show');
Route::post('onboarding/dismiss', [OnboardingController::class, 'dismiss'])->name('onboarding.dismiss');
