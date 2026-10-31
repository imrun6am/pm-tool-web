<?php

namespace App\Services\Kpi;

use App\Jobs\AnalyseKpiMember;
use App\Jobs\BuildKpiReport;
use App\Models\KpiReport;
use App\Models\KpiReportMember;
use App\Models\TasksheetEntry;
use App\Models\Team;
use App\Models\User;
use Illuminate\Bus\Batch;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

/**
 * KPI review: who is scored, what each member's queued job sends to the
 * analyzer, and the field-guide rules that turn day counts into a report row.
 *
 * Scored members are developers/QA with tasksheet rows in the period; anyone
 * who leads a team is never scored. Full-day leave (casual/sick) is excluded;
 * half-day leave counts as a working day, as on the tasksheet itself.
 */
class KpiReviewService
{
    /**
     * Who a report scores. A team report covers that team's developers/QA and
     * only their rows on that team; an individual report covers one member.
     *
     * @return list<int>
     */
    public function memberIds(string $from, string $to, ?int $teamId = null, ?int $memberId = null): array
    {
        $rows = TasksheetEntry::whereBetween('date', [$from, $to])
            ->when($teamId, fn ($q) => $q->where('team_id', $teamId))
            ->select('user_id');

        return User::withTrashed()
            ->whereIn('role', [User::ROLE_DEVELOPER, User::ROLE_QA])
            ->whereNotIn('id', Team::whereNotNull('team_lead_id')->select('team_lead_id'))
            ->whereIn('id', $rows)
            ->when($memberId, fn ($q) => $q->whereKey($memberId))
            ->orderBy('name')
            ->pluck('id')
            ->all();
    }

    /**
     * Creates the report and queues one job per member, plus the file build.
     * Returns null (and creates nothing) when nobody in the selection has
     * tasksheet rows in the period.
     *
     * @param  array{from: string, to: string, format: string, scope: string, team_id?: int|null, member_id?: int|null}  $data
     */
    public function start(User $admin, array $data): ?KpiReport
    {
        $teamId = $data['scope'] === KpiReport::SCOPE_TEAM ? (int) $data['team_id'] : null;
        $memberId = $data['scope'] === KpiReport::SCOPE_MEMBER ? (int) $data['member_id'] : null;
        $ids = $this->memberIds($data['from'], $data['to'], $teamId, $memberId);
        if ($ids === []) {
            return null;
        }

        $report = DB::transaction(function () use ($admin, $data, $teamId, $memberId, $ids) {
            $report = KpiReport::create([
                'requested_by' => $admin->id,
                'scope' => $data['scope'],
                'team_id' => $teamId,
                'member_id' => $memberId,
                'period_start' => $data['from'],
                'period_end' => $data['to'],
                'format' => $data['format'],
                'members_total' => count($ids),
            ]);

            $now = now();
            KpiReportMember::insert(array_map(fn (int $id) => [
                'kpi_report_id' => $report->id, 'user_id' => $id, 'status' => KpiReportMember::STATUS_PENDING,
                'created_at' => $now, 'updated_at' => $now,
            ], $ids));

            return $report;
        });

        $reportId = $report->id;
        $jobs = $report->members()->pluck('id')->map(fn (int $id) => new AnalyseKpiMember($id))->all();

        // allowFailures: one member's failure must not sink the whole report.
        $batch = Bus::batch($jobs)
            ->name("KPI report #{$reportId}")
            ->allowFailures()
            ->finally(fn (Batch $batch) => BuildKpiReport::dispatch($reportId))
            ->dispatch();

        $report->update(['batch_id' => $batch->id]);

        return $report;
    }

    /**
     * Everything one member's job needs: the analyzer prompt input plus the
     * facts the report keeps (so the export never re-reads the tasksheet).
     *
     * @return array{input: array<string, mixed>, facts: array<string, mixed>}
     */
    public function memberInput(User $member, string $from, string $to, ?int $teamId = null): array
    {
        $rows = TasksheetEntry::query()
            ->with('team:id,name')
            ->where('user_id', $member->id)
            ->whereBetween('date', [$from, $to])
            ->when($teamId, fn ($q) => $q->where('team_id', $teamId))
            ->orderBy('date')
            ->get(['id', 'team_id', 'user_id', 'date', 'plan', 'result', 'comment', 'tickets',
                'work_points', 'ticket_count', 'ticket_points', 'leave_type', 'standup_attended', 'created_at']);

        $teams = [];
        $leaveDates = [];
        $days = [];

        foreach ($rows as $e) {
            $teams[$e->team->name] = true;

            if ($e->isFullDayLeave()) {
                $leaveDates[] = $e->date->toDateString();

                continue;
            }

            $tickets = $this->plain($e->tickets);
            $days[] = [
                'date' => $e->date->toDateString(),
                'team' => $e->team->name,
                'half_day' => $e->isHalfDay(),
                'plan' => $this->plain($e->plan),
                'result' => $this->plain($e->result),
                'comment' => $this->plain($e->comment),
                'tickets' => array_values(array_unique(preg_match_all('/\d{4,}/', $tickets, $m) ? $m[0] : [])),
                'work_points' => $e->work_points,
                'standup' => $e->standup_attended,
                'filled' => $e->isFullyFilled() ? 'full' : ($e->isPartiallyFilled() ? 'partial' : 'empty'),
                'late' => $e->wasFilledLate(),
            ];
        }

        $months = array_values(array_unique(array_map(fn ($d) => substr($d['date'], 0, 7), $days)));

        return [
            'input' => [
                'role' => $member->role,
                'teams' => array_keys($teams),
                'months' => $months,
                'signals' => collect($months)->mapWithKeys(fn ($ym) => [
                    $ym => $this->signals(array_filter($days, fn ($d) => str_starts_with($d['date'], $ym))),
                ])->all(),
                'entries' => array_map(fn ($d) => array_filter([
                    'date' => $d['date'], 'team' => $d['team'], 'half_day' => $d['half_day'] ?: null,
                    'plan' => $d['plan'], 'result' => $d['result'], 'comment' => $d['comment'] ?: null,
                    'tickets' => $d['tickets'] ?: null, 'work_points' => $d['work_points'],
                    'standup' => $d['standup'], 'filled' => $d['filled'], 'late' => $d['late'] ?: null,
                ], fn ($v) => $v !== null && $v !== ''), $days),
            ],
            'facts' => [
                'teams' => array_keys($teams),
                'leave_dates' => $leaveDates,
                'days' => array_map(fn ($d) => [
                    'date' => $d['date'], 'team' => $d['team'], 'tickets' => $d['tickets'],
                    'plan' => $d['plan'], 'result' => $d['result'],
                ], $days),
            ],
        ];
    }

    /**
     * Report rows for one member: the whole period plus each month.
     *
     * @param  list<string>  $months  YYYY-MM
     * @return array{period: array<string, mixed>, months: array<string, array<string, mixed>>, days: list<array<string, mixed>>}
     */
    public function memberRows(KpiReportMember $row, KpiReport $report, array $months): array
    {
        $member = $row->member;
        $analysis = $row->analysis ?? [];
        $facts = $analysis['facts'] ?? ['teams' => [], 'leave_dates' => [], 'days' => []];

        $raw = collect($facts['days'])->keyBy(fn ($d) => $d['date'].'|'.$d['team']);
        $counts = collect($analysis['days'] ?? [])
            ->map(fn ($d) => $d + ['plan' => $raw[$d['date'].'|'.$d['team']]['plan'] ?? '', 'result' => $raw[$d['date'].'|'.$d['team']]['result'] ?? ''])
            ->sortBy('date')
            ->values()
            ->all();

        $teamParam = $report->teamFilter() ? ['team' => $report->teamFilter()] : [];
        $build = function (string $from, string $to, ?array $notes) use ($row, $member, $facts, $counts, $teamParam) {
            $inRange = fn ($d) => $d['date'] >= $from && $d['date'] <= $to;
            $days = array_values(array_filter($counts, $inRange));
            $rawDays = array_filter($facts['days'], $inRange);
            $t = self::totals($days);

            $evidence = array_filter([
                'Tasksheet: '.route('tasksheet.user', ['member' => $member->id, 'from' => $from, 'to' => $to] + $teamParam),
                'Report: '.route('tasksheet.report', ['member' => $member->id, 'from' => $from, 'to' => $to] + $teamParam),
                ($tickets = array_unique(array_merge([], ...array_column($rawDays, 'tickets'))))
                    ? 'Tickets: '.implode(', ', $tickets) : null,
            ]);

            return [
                'name' => $member->name,
                'role' => User::ROLES[$member->role] ?? $member->role,
                'teams' => implode(', ', $facts['teams']),
                // Distinct dates: a member on two teams has one row per team per day.
                'working_days' => count(array_unique(array_column($rawDays, 'date'))),
                'leave_days' => count(array_unique(array_filter($facts['leave_dates'], fn ($d) => $d >= $from && $d <= $to))),
                'kpi' => $t['kpi'],
                'target' => '',
                'actual' => $t['done'],
                'percent' => $t['percent'],
                'feedback' => $notes['feedback'] ?? '',
                'evidence' => implode("\n", $evidence),
                'strengths' => $notes['strengths'] ?? '',
                'improvement_areas' => $notes['improvement_areas'] ?? '',
                'status' => self::status($t['percent']),
                'improvement_action' => $notes['improvement_action'] ?? '',
                'success' => self::success($t['percent']),
                'progress' => self::progress($days),
                'note' => match (true) {
                    $row->status === KpiReportMember::STATUS_FAILED => 'Analysis failed: '.$row->error,
                    $days === [] => 'No tasksheet data in this period',
                    default => '',
                },
            ];
        };

        $monthNotes = collect($analysis['months'] ?? [])->keyBy('month');
        $out = [
            'period' => $build($report->period_start->toDateString(), $report->period_end->toDateString(), $analysis['overall'] ?? null),
            'months' => [],
            'days' => $counts,
        ];
        foreach ($months as $ym) {
            [$from, $to] = $this->monthRange($ym, $report);
            $out['months'][$ym] = $build($from, $to, $monthNotes[$ym] ?? null);
        }

        return $out;
    }

    /** @return list<string> YYYY-MM for every month the period touches */
    public function months(KpiReport $report): array
    {
        $months = [];
        for ($d = $report->period_start->copy()->startOfMonth(); $d->lte($report->period_end); $d->addMonthNoOverflow()) {
            $months[] = $d->format('Y-m');
        }

        return $months;
    }

    /** @return array{0: string, 1: string} the month clipped to the report period */
    public function monthRange(string $ym, KpiReport $report): array
    {
        $start = Carbon::parse($ym.'-01');

        return [
            max($start->toDateString(), $report->period_start->toDateString()),
            min($start->copy()->endOfMonth()->toDateString(), $report->period_end->toDateString()),
        ];
    }

    /** @return array{kpi: int, done: int, percent: float|null} */
    public static function totals(array $days): array
    {
        $kpi = array_sum(array_map(fn ($d) => $d['planned'] + $d['added'], $days));
        $done = array_sum(array_column($days, 'completed'));

        return ['kpi' => $kpi, 'done' => $done, 'percent' => $kpi ? round($done / $kpi * 100, 1) : null];
    }

    /** Field guide: Excellent ≥ 90 · Good 85–89 · Satisfactory 80–84 · Needs Improvement < 80. */
    public static function status(?float $percent): string
    {
        return match (true) {
            $percent === null => '',
            $percent >= 90 => 'Excellent',
            $percent >= 85 => 'Good',
            $percent >= 80 => 'Satisfactory',
            default => 'Needs Improvement',
        };
    }

    /** The guide gives no bands for Success Measure; it uses the Overall Status bands. */
    public static function success(?float $percent): string
    {
        return match (true) {
            $percent === null => '',
            $percent >= 90 => 'Fully achieved',
            $percent >= 85 => 'Largely achieved',
            $percent >= 80 => 'Mostly achieved',
            default => 'Partially achieved',
        };
    }

    /** Second half of the period vs the first half, by completion %; ±2 points is Stable. */
    public static function progress(array $days): string
    {
        if (count($days) < 4) {
            return '';
        }

        $mid = intdiv(count($days), 2);
        $before = self::totals(array_slice($days, 0, $mid))['percent'];
        $after = self::totals(array_slice($days, $mid))['percent'];

        return match (true) {
            $before === null || $after === null => '',
            $after - $before >= 2 => 'Improving',
            $before - $after >= 2 => 'Declining',
            default => 'Stable',
        };
    }

    /** @return array<string, int|float> behaviour signals the drafts are grounded in */
    private function signals(array $days): array
    {
        $n = count($days);
        $count = fn (callable $f) => count(array_filter($days, $f));

        return [
            'working_days' => count(array_unique(array_column($days, 'date'))),
            'half_days' => $count(fn ($d) => $d['half_day']),
            'standup_attended' => $count(fn ($d) => $d['standup'] === true),
            'standup_missed' => $count(fn ($d) => $d['standup'] === false),
            'standup_not_marked' => $count(fn ($d) => $d['standup'] === null),
            'filled_late' => $count(fn ($d) => $d['late']),
            'partially_filled' => $count(fn ($d) => $d['filled'] === 'partial'),
            'result_missing' => $count(fn ($d) => $d['result'] === ''),
            'avg_work_points' => $n ? round(array_sum(array_map(fn ($d) => $d['work_points'] ?? 0, $days)) / $n, 1) : 0,
        ];
    }

    /** Sanitized rich text → plain lines (the prompt needs words, not markup). */
    private function plain(?string $html): string
    {
        if ($html === null) {
            return '';
        }

        $html = preg_replace('#<br\s*/?>|</(div|p|li|h\d)>#i', "\n", $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5);

        return trim(preg_replace("/\n{2,}/", "\n", preg_replace('/[ \t]+/', ' ', $text)));
    }
}
