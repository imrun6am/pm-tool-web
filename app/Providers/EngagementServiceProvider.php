<?php

namespace App\Providers;

use App\Http\Middleware\ShareCelebration;
use App\Models\Comment;
use App\Models\Task;
use App\Observers\CommentEngagementObserver;
use App\Observers\TaskEngagementObserver;
use App\Services\NotificationService;
use App\Services\OnboardingService;
use App\Support\PendingCelebration;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Wiring for the engagement features — the notification inbox, the
 * completion moment, quick capture and onboarding — kept out of
 * AppServiceProvider so it reads as one unit.
 */
class EngagementServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(PendingCelebration::class);
    }

    public function boot(Router $router): void
    {
        Task::observe(TaskEngagementObserver::class);
        Comment::observe(CommentEngagementObserver::class);

        // Runs inside StartSession, so a flashed celebration is saved with the session.
        $router->pushMiddlewareToGroup('web', ShareCelebration::class);
        $router->pushMiddlewareToGroup('api', ShareCelebration::class);

        View::composer('layouts.notification-bell', function ($view) {
            $user = auth()->user();
            $notifications = app(NotificationService::class);

            $view->with([
                'bellUnread' => $notifications->unreadCount($user),
                'bellLatest' => $notifications->latest($user),
            ]);
        });

        View::composer('partials.onboarding-checklist', function ($view) {
            $user = auth()->user();
            $onboarding = app(OnboardingService::class);
            $steps = $onboarding->checklist($user);

            $view->with([
                'onboardingSteps' => $steps,
                'onboardingVisible' => $onboarding->isVisible($user, $steps),
            ]);
        });
    }
}
