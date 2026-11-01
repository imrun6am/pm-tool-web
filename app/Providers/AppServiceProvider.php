<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Ai\AiFeatures;
use App\Services\Ai\ClaudeTextEnhancer;
use App\Services\Ai\TextEnhancer;
use App\Services\Board\ClaudePlanTaskExtractor;
use App\Services\Board\PlanTaskExtractor;
use App\Services\Calendar\ClaudeEventExtractor;
use App\Services\Calendar\EventExtractor;
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
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // KPI reports and calendar import call the SDK directly; their model and
        // effort still come from config/ai.php and the admin's AI usage page.
        $this->app->bind(KpiAnalyzer::class, fn ($app) => new ClaudeKpiAnalyzer(
            config('services.anthropic.key'),
            $app->make(AiFeatures::class)->get('kpi_review')['model'],
            $app->make(AiFeatures::class)->get('kpi_review')['effort'],
        ));

        // These two call Claude through AiGateway (model/effort in config/ai.php).
        $this->app->bind(PlanTaskExtractor::class, ClaudePlanTaskExtractor::class);
        $this->app->bind(TextEnhancer::class, ClaudeTextEnhancer::class);

        $this->app->bind(EventExtractor::class, fn ($app) => new ClaudeEventExtractor(
            config('services.anthropic.key'),
            $app->make(AiFeatures::class)->get('calendar_import')['model'],
            $app->make(AiFeatures::class)->get('calendar_import')['effort'],
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

        // Soft navigation keeps one JS runtime across pages; when a deploy
        // changes the built assets, Turbo sees the tracked tags differ and
        // does a full reload instead of mixing old and new bundles.
        Vite::useScriptTagAttributes(['data-turbo-track' => 'reload']);
        Vite::useStyleTagAttributes(['data-turbo-track' => 'reload']);

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
                'drawerReleases' => $quickLinks->attachableReleases(Auth::user()),
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

        // "Enhance with Claude": each click is a paid model call.
        RateLimiter::for('ai-enhance', fn (Request $request) => Limit::perMinute(10)->by('user:'.$request->user()?->id));

        // Calendar "Import from file": each upload can mean several paid model calls.
        RateLimiter::for('calendar-import', fn (Request $request) => Limit::perHour(20)->by('user:'.$request->user()?->id));

        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(20)->by($request->ip()),
        ]);
    }
}
