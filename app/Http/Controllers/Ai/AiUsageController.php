<?php

namespace App\Http\Controllers\Ai;

use App\Http\Controllers\Controller;
use App\Services\Ai\AiFeatures;
use App\Services\Ai\AiUsageReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
}
