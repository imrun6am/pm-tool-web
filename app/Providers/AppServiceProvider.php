<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Board\ClaudePlanTaskExtractor;
use App\Services\Board\PlanTaskExtractor;
use App\Services\Kpi\ClaudeKpiAnalyzer;
use App\Services\Kpi\KpiAnalyzer;
use App\Services\QuickLinkService;
use App\Support\Navigation;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(KpiAnalyzer::class, fn () => new ClaudeKpiAnalyzer(
            config('services.anthropic.key'),
            config('services.anthropic.model'),
            config('services.anthropic.effort'),
        ));

        $this->app->bind(PlanTaskExtractor::class, fn () => new ClaudePlanTaskExtractor(
            config('services.anthropic.key'),
            config('services.anthropic.board_tasks_model'),
            config('services.anthropic.board_tasks_effort'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Managing the competency catalog is org-level configuration — admin,
        // CTO, and tech lead only (team leads evaluate, they do not reconfigure).
        Gate::define('manage-competencies', fn (User $user) => $user->canManageCompetencies());

        $this->configureRateLimiting();

        // The quick-links drawer renders on every page; a composer supplies its
        // data so individual controllers never have to. The visible-links query
        // and the own/shared split come from QuickLinkService, shared with the
        // API, so the drawer and the desktop client cannot disagree.
        View::composer('partials.quick-links-drawer', function ($view) {
            $quickLinks = app(QuickLinkService::class);
            $partitioned = $quickLinks->partitionedFor(Auth::user());

            $view->with([
                'myQuickLinks' => $partitioned['mine'],
                'sharedQuickLinks' => $partitioned['shared'],
                'drawerReleases' => $quickLinks->attachableReleases(),
            ]);
        });

        // @invalid('field') — marks a form control invalid for assistive tech and
        // links it to the inline error that x-field renders (id "field-error").
        Blade::directive('invalid', fn (string $field) => "<?php if (\$errors->has({$field})): ?>aria-invalid=\"true\" aria-describedby=\"<?php echo e({$field}); ?>-error\"<?php endif; ?>");

        // App-shell navigation: the sidebar (desktop rail + mobile drawer) and
        // the command palette share one role-filtered menu.
        View::composer(['layouts.sidebar', 'layouts.command-palette'], function ($view) {
            $user = Auth::user();

            $view->with([
                'navGroups' => Navigation::groups($user),
                'createActions' => Navigation::createActions($user),
            ]);
        });
    }

    /**
     * Named limiters for the API. Traffic is metered per access token so one
     * noisy desktop client cannot spend another's budget, falling back to the
     * IP for unauthenticated calls. Logins are metered far more tightly, keyed
     * on the submitted email *and* the caller's address, so neither guessing
     * one account from many addresses nor many accounts from one address is
     * cheap.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)
            ->by($request->user()?->currentAccessToken()?->id
                ? 'token:'.$request->user()->currentAccessToken()->id
                : 'ip:'.$request->ip()));

        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(20)->by($request->ip()),
        ]);
    }
}
