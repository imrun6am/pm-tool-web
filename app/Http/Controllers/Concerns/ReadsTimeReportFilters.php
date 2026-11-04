<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

trait ReadsTimeReportFilters
{
    /**
     * The period defaults to the last 30 days. A team lead's reach is limited by
     * the report itself, and naming another team is refused here.
     *
     * @return array{from: Carbon, to: Carbon, team_id: ?int, release_id: ?int}
     */
    protected function timeReportFilters(Request $request): array
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'release_id' => ['nullable', 'integer', 'exists:releases,id'],
        ]);

        $user = $request->user();
        abort_if(
            ! empty($data['team_id']) && ! $user->canOverseeAllTeams() && ! Team::whereKey($data['team_id'])->where('team_lead_id', $user->id)->exists(),
            403,
        );

        return [
            'from' => filled($data['from'] ?? null) ? Carbon::parse($data['from'])->startOfDay() : today()->subDays(29),
            'to' => filled($data['to'] ?? null) ? Carbon::parse($data['to'])->startOfDay() : today(),
            'team_id' => isset($data['team_id']) ? (int) $data['team_id'] : null,
            'release_id' => isset($data['release_id']) ? (int) $data['release_id'] : null,
        ];
    }
}
