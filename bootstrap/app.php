<?php

use App\Http\Middleware\EnsureApiUserIsActive;
use App\Http\Middleware\EnsureFullAccess;
use App\Http\Middleware\EnsureUserCanManageReleases;
use App\Http\Middleware\EnsureUserCanManageUsers;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsLead;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'lead' => EnsureUserIsLead::class,
            'manage-users' => EnsureUserCanManageUsers::class,
            'manage-releases' => EnsureUserCanManageReleases::class,
            'full-access' => EnsureFullAccess::class,
            // The API counterpart of EnsureUserIsActive: no session to invalidate,
            // so it revokes the presented token instead of redirecting.
            'active-api' => EnsureApiUserIsActive::class,
        ]);

        // Sign out any user deactivated mid-session on their next request.
        $middleware->web(append: [
            EnsureUserIsActive::class,
        ]);

        // Every API request is throttled; the limiter is defined in AppServiceProvider.
        $middleware->api(prepend: [
            'throttle:api',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
