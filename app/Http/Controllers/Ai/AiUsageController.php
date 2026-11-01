<?php

namespace App\Http\Controllers\Ai;

use App\Http\Controllers\Controller;
use App\Services\Ai\AiFeatures;
use App\Services\Ai\AiUsageReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Admin: what the Claude features cost and do, and switching them on or off. */
class AiUsageController extends Controller
{
    public const PERIODS = [7 => 'Last 7 days', 30 => 'Last 30 days', 90 => 'Last 90 days'];

    public function __construct(
        private readonly AiFeatures $features,
        private readonly AiUsageReport $report,
    ) {}

    public function index(Request $request): View
    {
        $days = (int) $request->query('days', 30);
        $days = array_key_exists($days, self::PERIODS) ? $days : 30;
        $since = now()->subDays($days - 1)->startOfDay();

        $byFeature = $this->report->byFeature($since);

        return view('ai.usage', [
            'days' => $days,
            'periods' => self::PERIODS,
            'byFeature' => $byFeature,
            'totals' => [
                'calls' => $byFeature->sum('calls'),
                'failures' => $byFeature->sum('failures'),
                'tokens' => $byFeature->sum('input_tokens') + $byFeature->sum('output_tokens'),
                'cost' => $byFeature->sum('cost'),
            ],
            'daily' => $this->report->daily($since),
            'recent' => $this->report->recent(),
            'configured' => $this->features->configured(),
            'features' => $this->features,
        ]);
    }

    public function toggle(Request $request, string $feature): RedirectResponse
    {
        $data = $request->validate(['enabled' => ['required', 'boolean']]);
        abort_unless(array_key_exists($feature, $this->features->all()), 404);

        $this->features->set($feature, (bool) $data['enabled'], $request->user());
        $label = $this->features->get($feature)['label'];

        return back()->with('success', $data['enabled'] ? "{$label} switched on." : "{$label} switched off.");
    }

    /** Set one feature's model and effort ("" = back to the default). */
    public function configure(Request $request, string $feature): RedirectResponse
    {
        abort_unless(array_key_exists($feature, $this->features->all()), 404);
        $data = $request->validate([
            'model' => ['nullable', Rule::in(array_keys($this->features->modelsFor($feature)))],
            'effort' => ['nullable', Rule::in(array_keys(config('ai.efforts')))],
        ]);

        $this->features->configure($feature, $data['model'] ?? null, $data['effort'] ?? null, $request->user());
        $f = $this->features->get($feature);

        return back()->with('success', "{$f['label']} now uses ".(config("ai.models.{$f['model']}") ?? $f['model'])." at {$f['effort']} effort.");
    }

    /** Use one model for every feature (efforts are kept); "" resets every model to its default. */
    public function configureAll(Request $request): RedirectResponse
    {
        $data = $request->validate(['model' => ['nullable', Rule::in(array_keys(config('ai.models')))]]);
        $model = $data['model'] ?? null;

        $skipped = [];
        foreach ($this->features->all() as $key => $f) {
            if ($model !== null && ! array_key_exists($model, $this->features->modelsFor($key))) {
                $skipped[] = $f['label'];

                continue;
            }
            $override = $f['customized'] ? $f['effort'] : null;
            $this->features->configure($key, $model, $override, $request->user());
        }

        $message = $model ? 'Every feature now uses '.config("ai.models.{$model}").'.' : 'Every feature is back on its default model.';
        if ($skipped) {
            $message .= ' Kept for '.implode(', ', $skipped).' (that model isn’t supported there).';
        }

        return back()->with('success', $message);
    }
}
