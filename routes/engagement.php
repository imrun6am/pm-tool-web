<?php

use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PreferenceController;
use App\Http\Controllers\QuickCaptureController;
use App\Http\Controllers\TaskSearchController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Engagement — inbox, quick capture, preferences, onboarding
|--------------------------------------------------------------------------
|
| Every signed-in user, any role: each endpoint works only on the caller's
| own data (their notifications, their preferences) or goes through the
| task rules (TaskPolicy, Task::visibleTo). Required near the top of
| routes/web.php so `tasks/new` registers before `tasks/{task}`.
|
*/

Route::middleware('auth')->group(function () {
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::get('notifications/{notification}', [NotificationController::class, 'open'])->name('notifications.open');

    Route::get('tasks/new', [QuickCaptureController::class, 'create'])->name('tasks.quick');
    Route::post('tasks/quick', [QuickCaptureController::class, 'store'])->name('tasks.quick.store');
    Route::get('search/tasks', TaskSearchController::class)->name('search.tasks');

    Route::put('profile/preferences', [PreferenceController::class, 'update'])->name('profile.preferences.update');
    Route::post('onboarding/dismiss', [OnboardingController::class, 'dismiss'])->name('onboarding.dismiss');
});
