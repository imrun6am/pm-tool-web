<?php

namespace App\Providers;

use App\Http\Middleware\ShareCelebration;
use App\Models\Comment;
use App\Models\Event;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Observers\CommentEngagementObserver;
use App\Observers\EventEngagementObserver;
use App\Observers\TaskEngagementObserver;
use App\Observers\TaskTasksheetObserver;
use App\Observers\TeamEngagementObserver;
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
        // Scoped: observers and services share one batch buffer.
        $this->app->scoped(NotificationService::class);
    }

    public function boot(Router $router): void
    {
        Task::observe(TaskEngagementObserver::class);
        Task::observe(TaskTasksheetObserver::class);
        Comment::observe(CommentEngagementObserver::class);
        Team::observe(TeamEngagementObserver::class);
        Event::observe(EventEngagementObserver::class);

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

        View::composer('layouts.select-swatches', function ($view) {
            $view->with([
                'ssTeamColors' => Team::pluck('color', 'id'),
                'ssProjectColors' => Project::pluck('color', 'id'),
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
