<?php

namespace App\Services\Kpi;

use App\Models\KpiReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\Common\Entity\Sheet;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Writes a finished KPI report to the local disk.
 *
 * Excel is streamed with OpenSpout: rows go straight to temp files, and all
 * sheets are open at once so members are read from the database in one lazy
 * pass. The PDF holds the summary only (no per-day table) to keep dompdf's
 * memory use small.
 */
class KpiReportExporter
{
    /** Column key => [header, width] in field-guide order. */
    private const COLUMNS = [
        'name' => ['Team Member', 20],
        'role' => ['Role', 11],
        'teams' => ['Team(s)', 20],
        'working_days' => ['Working days', 9],
        'leave_days' => ['Leave days', 8],
        'kpi' => ['KPI / Task', 9],
        'target' => ['Expected Target / Deadline', 16],
        'actual' => ['Actual Result', 9],
        'percent' => ['Completion %', 10],
        'feedback' => ['Team Lead Feedback (draft)', 45],
        'evidence' => ['Evidence / Record', 45],
        'strengths' => ['Strengths (draft)', 40],
        'improvement_areas' => ['Improvement Areas (draft)', 40],
        'status' => ['Overall Status', 16],
        'improvement_action' => ['Improvement Action / Support (draft)', 45],
        'success' => ['Success Measure', 16],
        'progress' => ['Progress / Outcome', 12],
        'work_mix' => ['Work mix', 32],
        'focus' => ['Main focus / key contributions', 50],
        'note' => ['Note', 30],
    ];

    private const DAILY_COLUMNS = [
        ['Team Member', 20], ['Date', 11], ['Team', 14], ['Planned', 8], ['Added', 8],
        ['KPI / Task', 9], ['Completed', 10], ['Work type', 28], ['Plan', 60], ['Result', 60],
    ];

    private const INSIGHT_COLUMNS = [
        ['Team Member', 20], ['Suggested rating (draft)', 16], ['Rating rationale (draft)', 50],
        ['Key contributions', 55], ['Recurring tasks', 55], ['Carried-over / stuck tasks', 45],
        ['Positive observations', 55], ['Concerns', 55],
    ];

    /** Habit key => [header, width]; values come from KpiReviewService::signals(). */
    private const HABIT_COLUMNS = [
        'working_days' => ['Working days', 9],
        'standup_attended' => ['Standup attended', 10],
        'standup_missed' => ['Standup missed', 10],
        'standup_not_marked' => ['Standup not marked', 10],
        'filled_late' => ['Tasksheet filled late', 10],
        'partially_filled' => ['Tasksheet partially filled', 11],
        'result_missing' => ['Day-end result missing', 10],
        'avg_work_points' => ['Avg work points', 9],
    ];

    /** Overall Status → cell fill. */
    public const STATUS_COLORS = [
        'Excellent' => 'C6EFCE',
        'Good' => 'DDEBF7',
        'Satisfactory' => 'FFEB9C',
        'Needs Improvement' => 'FFC7CE',
    ];

    public function __construct(private readonly KpiReviewService $kpi) {}

    /** @return string the file's path on the local disk */
    public function write(KpiReport $report): string
    {
        $relative = "kpi-reports/{$report->id}/{$report->filename()}";
        $disk = Storage::disk('local');
        $disk->makeDirectory(dirname($relative));

        $report->format === 'pdf'
            ? $this->writePdf($report, $disk->path($relative))
            : $this->writeXlsx($report, $disk->path($relative));

        return $relative;
    }

    /** @return list<string> the explanatory lines shared by both formats */
    public function notes(KpiReport $report): array
    {
        return [
            sprintf('%s · review period %s – %s. Full-day leave (casual/sick) is excluded; half-day leave counts as a working day. Team leads are not scored.',
                $report->coverageLabel(), $report->period_start->format('j M Y'), $report->period_end->format('j M Y')),
            'KPI / Task = planned + added tasks; Actual Result = completed tasks; Completion % = Actual ÷ KPI. Claude counted the tasks from each day’s Plan and Result.',
            'Overall Status: Excellent ≥ 90% · Good 85–89% · Satisfactory 80–84% · Needs Improvement < 80%.',
            'Success Measure uses the same bands: Fully ≥ 90% · Largely 85–89% · Mostly 80–84% · Partially < 80%.',
            'Progress / Outcome compares completion % in the second half of the period with the first half (within ±2 points = Stable).',
            ...($report->teamFilter() ? ['Team report: only tasksheet rows logged on this team are counted.'] : []),
            'Work mix and Work breakdown: Claude sorted each day’s tasks into fixed categories; the totals are summed from those daily counts.',
            'Work habits come straight from the tasksheet (standup marks, when rows were saved, filled fields, work points) – not from Claude.',
            'Columns marked (draft), the Insights sheet and the suggested rating were written by Claude and must be reviewed by the Team Lead. Expected Target / Deadline is left for the Team Lead.',
        ];
    }

    public function monthLabel(string $ym): string
    {
        return Carbon::parse($ym.'-01')->format('M Y');
    }

    private function writeXlsx(KpiReport $report, string $path): void
    {
        $months = $this->kpi->months($report);

        $header = (new Style)->withFontBold(true)->withShouldWrapText(true)->withBackgroundColor('F1F5F9')
            ->withCellVerticalAlignment(CellVerticalAlignment::CENTER);
        $body = (new Style)->withShouldWrapText(true)->withCellVerticalAlignment(CellVerticalAlignment::TOP);
        $percent = $body->withFormat('0.0%');
        $statusStyles = array_map(fn (string $color) => $body->withBackgroundColor($color), self::STATUS_COLORS);

        $writer = new Writer;
        $writer->openToFile($path);

        $prepare = function (Sheet $sheet, string $name, array $columns) use ($writer, $header) {
            $sheet->setName($name);
            $sheet->setSheetView((new SheetView)->withFreezeRow(2)->withFreezeColumn('B'));
            foreach (array_values($columns) as $i => [, $width]) {
                $sheet->setColumnWidth($width, $i + 1);
            }
            $writer->setCurrentSheet($sheet);
            $writer->addRow(Row::fromValuesWithStyle(array_column(array_values($columns), 0), $header));

            return $sheet;
        };

        $periodSheet = $prepare($writer->getCurrentSheet(), 'Period', self::COLUMNS);
        $monthSheets = [];
        foreach ($months as $ym) {
            $monthSheets[$ym] = $prepare($writer->addNewSheetAndMakeItCurrent(), $this->monthLabel($ym), self::COLUMNS);
        }
        $insightSheet = $prepare($writer->addNewSheetAndMakeItCurrent(), 'Insights', self::INSIGHT_COLUMNS);
        $breakdownSheet = $prepare($writer->addNewSheetAndMakeItCurrent(), 'Work breakdown', [
            ['Team Member', 20], ['Period', 14], ['Total tasks', 9],
            ...array_map(fn (string $label) => [$label, 11], array_values(KpiReviewService::CATEGORIES)),
        ]);
        $habitSheet = $prepare($writer->addNewSheetAndMakeItCurrent(), 'Work habits', [
            ['Team Member', 20], ['Period', 14], ...array_values(self::HABIT_COLUMNS),
        ]);
        $dailySheet = $prepare($writer->addNewSheetAndMakeItCurrent(), 'Daily counts', self::DAILY_COLUMNS);

        $addSummary = function (Sheet $sheet, array $row) use ($writer, $body, $percent, $statusStyles) {
            $cells = [];
            foreach (array_keys(self::COLUMNS) as $key) {
                $value = $row[$key];
                $cells[] = match ($key) {
                    'percent' => Cell::fromValue($value === null ? '' : $value / 100, $percent),
                    'status' => Cell::fromValue($value, $statusStyles[$value] ?? $body),
                    default => Cell::fromValue($value, $body),
                };
            }
            $writer->setCurrentSheet($sheet);
            $writer->addRow(new Row($cells));
        };

        foreach ($report->members()->with('member')->lazyById(20) as $memberRow) {
            $rows = $this->kpi->memberRows($memberRow, $report, $months);
            $name = $rows['period']['name'];
            $sections = ['Whole period' => $rows['period']]
                + collect($rows['months'])->mapWithKeys(fn ($row, $ym) => [$this->monthLabel($ym) => $row])->all();

            $addSummary($periodSheet, $rows['period']);
            foreach ($months as $ym) {
                $addSummary($monthSheets[$ym], $rows['months'][$ym]);
            }

            $text = $this->insightText($rows['insights']);
            $writer->setCurrentSheet($insightSheet);
            $writer->addRow(Row::fromValuesWithStyle([
                $name, $text['rating'], $text['rationale'], $text['contributions'],
                $text['recurring'], $text['carried'], $text['positive'], $text['concerns'],
            ], $body));

            foreach ($sections as $label => $section) {
                $writer->setCurrentSheet($breakdownSheet);
                $writer->addRow(Row::fromValuesWithStyle([$name, $label, array_sum($section['categories']), ...array_values($section['categories'])], $body));

                if ($section['habits'] !== null) {
                    $writer->setCurrentSheet($habitSheet);
                    $writer->addRow(Row::fromValuesWithStyle([$name, $label, ...array_map(fn ($key) => $section['habits'][$key], array_keys(self::HABIT_COLUMNS))], $body));
                }
            }

            $writer->setCurrentSheet($dailySheet);
            foreach ($rows['days'] as $d) {
                $writer->addRow(Row::fromValuesWithStyle([
                    $name, $d['date'], $d['team'], $d['planned'], $d['added'],
                    $d['planned'] + $d['added'], $d['completed'], $this->dayCategories($d), $d['plan'], $d['result'],
                ], $body));
            }
        }

        $notes = $writer->addNewSheetAndMakeItCurrent();
        $notes->setName('Notes');
        $notes->setColumnWidth(120, 1);
        foreach ($this->notes($report) as $line) {
            $writer->addRow(Row::fromValuesWithStyle([$line], $body));
        }

        $writer->setCurrentSheet($periodSheet);
        $writer->close();
    }

    private function writePdf(KpiReport $report, string $path): void
    {
        $months = $this->kpi->months($report);
        $members = [];

        foreach ($report->members()->with('member')->lazyById(20) as $memberRow) {
            $rows = $this->kpi->memberRows($memberRow, $report, $months);
            unset($rows['days']);
            $members[] = $rows + ['insightText' => $this->insightText($rows['insights'])];
        }

        Pdf::loadView('kpi-reports.pdf', [
            'report' => $report,
            'members' => $members,
            'categories' => KpiReviewService::CATEGORIES,
            'habitColumns' => array_map(fn ($c) => $c[0], self::HABIT_COLUMNS),
            'monthLabels' => collect($months)->mapWithKeys(fn ($ym) => [$ym => $this->monthLabel($ym)])->all(),
            'notes' => $this->notes($report),
            'statusColors' => self::STATUS_COLORS,
        ])->setPaper('a4', 'landscape')->save($path);
    }

    /**
     * The Claude insights as plain text blocks, shared by the Excel cells and the PDF.
     *
     * @return array{rating: string, rationale: string, contributions: string, recurring: string, carried: string, positive: string, concerns: string}
     */
    public function insightText(array $insights): array
    {
        $bullets = fn (iterable $lines) => collect($lines)->map(fn ($line) => '• '.$line)->implode("\n");
        $observations = collect($insights['observations']);

        return [
            'rating' => $insights['rating'] ? $insights['rating']['score'].' – '.$insights['rating']['label'] : '',
            'rationale' => $insights['rating']['rationale'] ?? '',
            'contributions' => $bullets($insights['key_contributions']),
            'recurring' => $bullets(array_map(fn ($r) => "{$r['task']} — {$r['days']} days. {$r['comment']}", $insights['recurring_tasks'])),
            'carried' => $bullets(array_map(fn ($c) => "{$c['task']} ({$c['first_date']} → {$c['last_date']}, seen on {$c['days']} days)", $insights['carried_over'])),
            'positive' => $bullets($observations->where('type', 'positive')->pluck('text')),
            'concerns' => $bullets($observations->where('type', 'concern')->pluck('text')),
        ];
    }

    /** "Feature work ×2, Bug fixing ×1" for one day. */
    private function dayCategories(array $day): string
    {
        return collect($day['categories'] ?? [])
            ->filter(fn ($c) => $c['tasks'] > 0)
            ->map(fn ($c) => (KpiReviewService::CATEGORIES[$c['category']] ?? $c['category']).' ×'.$c['tasks'])
            ->implode(', ');
    }
}
