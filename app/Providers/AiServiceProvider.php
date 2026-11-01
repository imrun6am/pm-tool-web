<?php

namespace App\Providers;

use App\Services\Ai\AiFeatures;
use App\Services\Ai\AiGateway;
use App\Services\Ai\AiTransport;
use App\Services\Ai\ClaudeTransport;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Wiring for the Claude features that go through AiGateway: the transport,
 * the gateway, their rate limit and their routes (routes/ai.php).
 */
class AiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AiFeatures::class);

        // No key, no transport: the gateway then answers "not configured".
        $this->app->bind(AiTransport::class, fn () => filled(config('services.anthropic.key'))
            ? new ClaudeTransport(config('services.anthropic.key'))
            : null);

        $this->app->bind(AiGateway::class, fn ($app) => new AiGateway(
            $app->make(AiFeatures::class),
            $app->make(AiTransport::class),
        ));
    }

    public function boot(): void
    {
        // Each click on a Claude button is a paid model call.
        RateLimiter::for('ai', fn (Request $request) => Limit::perMinute(20)->by('user:'.$request->user()?->id));

        if (! $this->app->routesAreCached()) {
            Route::middleware('web')->group(base_path('routes/ai.php'));
        }
    }
}
