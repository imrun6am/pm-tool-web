<?php

namespace App\Support;

use Illuminate\Support\Arr;

/**
 * Every figure in the people report, in one place: the page, the PDF and the
 * CSV all read this list, so they can't disagree.
 *
 * Types: int, decimal, pct, days, minutes, score and text. pct, days and score
 * read a Metric::measure() and show nothing fit to quote below its minimum.
 * `csv` columns are the counts behind a rate, kept for spreadsheets only.
 */
class PeopleReportColumns
{
    /** @var array<string, string> group key => tab label */
    public const GROUPS = [
        'delivery' => 'Delivery',
        'workload' => 'Workload',
        'time' => 'Time',
        'tasksheet' => 'Tasksheet',
        'collaboration' => 'Requests and meetings',
        'ratings' => 'Ratings and KPI',
    ];

    /**
     * @return array<string, list<array{key: string, label: string, path: string, type: string, unit?: string, hint?: string, csv?: bool}>>
     */
    public static function groups(): array
    {
        return [
            'delivery' => [
                ['key' => 'completed', 'label' => 'Finished', 'path' => 'delivery.completed', 'type' => 'int', 'hint' => 'Tasks moved to Done in this period and still Done.'],
                ['key' => 'perDay', 'label' => 'Per working day', 'path' => 'delivery.perDay', 'type' => 'decimal', 'hint' => 'Finished tasks over working days, leave excluded. For a team, per person per day.'],
                ['key' => 'onTime', 'label' => 'On time', 'path' => 'delivery.onTime', 'type' => 'pct', 'unit' => 'task', 'hint' => 'Finished by their due date, of the finished tasks that had one.'],
                ['key' => 'cycle', 'label' => 'Median cycle', 'path' => 'delivery.cycle', 'type' => 'days', 'unit' => 'task', 'hint' => 'Calendar days from first In Progress to Done.'],
                ['key' => 'lead', 'label' => 'Median lead', 'path' => 'delivery.lead', 'type' => 'days', 'unit' => 'task', 'hint' => 'Calendar days from created to Done.'],
                ['key' => 'rework', 'label' => 'Rework', 'path' => 'quality.reworkRate', 'type' => 'pct', 'unit' => 'task', 'hint' => 'Finished tasks that went back to Recheck on the way.'],
                ['key' => 'dated', 'label' => 'Finished with a due date', 'path' => 'delivery.dated', 'type' => 'int', 'csv' => true],
                ['key' => 'onTimeCount', 'label' => 'Finished on time', 'path' => 'delivery.onTimeCount', 'type' => 'int', 'csv' => true],
                ['key' => 'cycleSample', 'label' => 'Tasks with a cycle time', 'path' => 'delivery.cycle.sample', 'type' => 'int', 'csv' => true],
                ['key' => 'cycleAverage', 'label' => 'Average cycle days', 'path' => 'delivery.cycleAverage', 'type' => 'decimal', 'csv' => true],
                ['key' => 'reworked', 'label' => 'Reworked tasks', 'path' => 'quality.reworked', 'type' => 'int', 'csv' => true],
            ],
            'workload' => [
                ['key' => 'open', 'label' => 'Open now', 'path' => 'workload.open', 'type' => 'int', 'hint' => 'Assigned and not finished, as of today.'],
                ['key' => 'todo', 'label' => 'To do', 'path' => 'workload.todo', 'type' => 'int'],
                ['key' => 'inProgress', 'label' => 'In progress', 'path' => 'workload.inProgress', 'type' => 'int'],
                ['key' => 'inReview', 'label' => 'In review', 'path' => 'workload.inReview', 'type' => 'int'],
                ['key' => 'recheck', 'label' => 'Recheck', 'path' => 'workload.recheck', 'type' => 'int'],
                ['key' => 'overdue', 'label' => 'Overdue now', 'path' => 'workload.overdue', 'type' => 'int', 'hint' => 'Open and past the due date, as of today.'],
                ['key' => 'blockedNow', 'label' => 'Blocked now', 'path' => 'workload.blockedNow', 'type' => 'int', 'hint' => 'Flagged as blocked, or waiting on an unfinished task.'],
                ['key' => 'blockedDays', 'label' => 'Blocked days', 'path' => 'workload.blockedDays', 'type' => 'decimal', 'hint' => 'Calendar days tasks spent flagged as blocked in this period. Waiting on another task has no history, so it isn\'t counted.'],
                ['key' => 'unestimated', 'label' => 'No estimate', 'path' => 'workload.unestimated', 'type' => 'int', 'hint' => 'Open tasks without an estimate.'],
            ],
            'time' => [
                ['key' => 'logged', 'label' => 'Logged', 'path' => 'time.loggedMinutes', 'type' => 'minutes', 'hint' => 'Time logged on tasks in this period.'],
                ['key' => 'available', 'label' => 'Available', 'path' => 'time.availableMinutes', 'type' => 'minutes', 'hint' => 'Working days on a team, less leave, at the planning hours per day.'],
                ['key' => 'compared', 'label' => 'Tasks compared', 'path' => 'time.comparedTasks', 'type' => 'int', 'hint' => 'Tasks finished in this period that have both an estimate and logged time.'],
                ['key' => 'estimated', 'label' => 'Estimated', 'path' => 'time.estimatedMinutes', 'type' => 'minutes', 'hint' => 'Estimates on the tasks compared.'],
                ['key' => 'comparedLogged', 'label' => 'Logged on those', 'path' => 'time.comparedLogged', 'type' => 'minutes', 'hint' => 'All time logged on the tasks compared.'],
                ['key' => 'ratio', 'label' => 'Logged vs estimate', 'path' => 'time.ratio', 'type' => 'pct', 'unit' => 'task', 'hint' => 'Over 100% took longer than estimated.'],
            ],
            'tasksheet' => [
                ['key' => 'filled', 'label' => 'Days filled', 'path' => 'tasksheet.filled', 'type' => 'int', 'hint' => 'Working days with every field filled.'],
                ['key' => 'owed', 'label' => 'Days owed', 'path' => 'tasksheet.owed', 'type' => 'int', 'hint' => 'Working days on the team up to yesterday, without full-day leave. Developers and QA only.'],
                ['key' => 'rate', 'label' => 'Filled', 'path' => 'tasksheet.rate', 'type' => 'pct', 'unit' => 'day'],
                ['key' => 'planRate', 'label' => 'Plan with result', 'path' => 'tasksheet.planRate', 'type' => 'pct', 'unit' => 'day', 'hint' => 'Of the days with a plan, how many also have a result.'],
                ['key' => 'late', 'label' => 'Filled late', 'path' => 'tasksheet.late', 'type' => 'int', 'hint' => 'First saved after the day had passed.'],
                ['key' => 'partial', 'label' => 'Part filled', 'path' => 'tasksheet.partial', 'type' => 'int'],
                ['key' => 'leave', 'label' => 'Leave days', 'path' => 'tasksheet.leaveDays', 'type' => 'int', 'hint' => 'Full-day leave on working days.'],
                ['key' => 'half', 'label' => 'Half days', 'path' => 'tasksheet.halfDays', 'type' => 'int'],
                ['key' => 'points', 'label' => 'Work points', 'path' => 'tasksheet.workPoints', 'type' => 'int'],
                ['key' => 'tickets', 'label' => 'Tickets', 'path' => 'tasksheet.tickets', 'type' => 'int'],
                ['key' => 'ticketPoints', 'label' => 'Ticket points', 'path' => 'tasksheet.ticketPoints', 'type' => 'int'],
                ['key' => 'standup', 'label' => 'Standups', 'path' => 'tasksheet.standupRate', 'type' => 'pct', 'unit' => 'marked day', 'hint' => 'Attended, of the days a lead marked.'],
                ['key' => 'planned', 'label' => 'Days with a plan', 'path' => 'tasksheet.planned', 'type' => 'int', 'csv' => true],
                ['key' => 'planWithResult', 'label' => 'Days with plan and result', 'path' => 'tasksheet.planWithResult', 'type' => 'int', 'csv' => true],
                ['key' => 'standupMarked', 'label' => 'Standups marked', 'path' => 'tasksheet.standupMarked', 'type' => 'int', 'csv' => true],
                ['key' => 'standupAttended', 'label' => 'Standups attended', 'path' => 'tasksheet.standupAttended', 'type' => 'int', 'csv' => true],
            ],
            'collaboration' => [
                ['key' => 'raised', 'label' => 'Requests raised', 'path' => 'requests.raised', 'type' => 'int'],
                ['key' => 'accepted', 'label' => 'Accepted', 'path' => 'requests.accepted', 'type' => 'int', 'hint' => 'Of the requests raised.'],
                ['key' => 'declined', 'label' => 'Declined', 'path' => 'requests.declined', 'type' => 'int', 'hint' => 'Of the requests raised.'],
                ['key' => 'pending', 'label' => 'Waiting', 'path' => 'requests.pending', 'type' => 'int', 'hint' => 'Of the requests raised, not decided yet.'],
                ['key' => 'decided', 'label' => 'Decided', 'path' => 'requests.decided', 'type' => 'int', 'hint' => 'Requests this person accepted or declined.'],
                ['key' => 'meetings', 'label' => 'Meetings', 'path' => 'collaboration.meetings', 'type' => 'int', 'hint' => 'Shared calendar meetings they were invited to. Private events aren\'t counted.'],
                ['key' => 'meetingTime', 'label' => 'Meeting time', 'path' => 'collaboration.meetingMinutes', 'type' => 'minutes'],
                ['key' => 'notesAttended', 'label' => 'Notes attended', 'path' => 'collaboration.notesAttended', 'type' => 'int', 'hint' => 'Meeting notes that list them as attending.'],
                ['key' => 'notesWritten', 'label' => 'Notes written', 'path' => 'collaboration.notesWritten', 'type' => 'int'],
                ['key' => 'comments', 'label' => 'Comments', 'path' => 'collaboration.comments', 'type' => 'int'],
                ['key' => 'aiUses', 'label' => 'AI uses', 'path' => 'collaboration.aiUses', 'type' => 'int', 'hint' => 'Successful AI requests they made.'],
            ],
            'ratings' => [
                ['key' => 'rating', 'label' => 'Rating', 'path' => 'ratings.overall', 'type' => 'score', 'unit' => 'competency', 'hint' => 'Weighted average of the competencies rated in this period, from 1 to 5.'],
                ['key' => 'rated', 'label' => 'Competencies rated', 'path' => 'ratings.rated', 'type' => 'int'],
                ['key' => 'kpiPercent', 'label' => 'KPI done', 'path' => 'kpi.percent', 'type' => 'pct', 'unit' => 'analysed day', 'hint' => 'AI-counted tasks completed, of those planned and added, in the latest KPI review overlapping this period.'],
                ['key' => 'kpiStatus', 'label' => 'KPI status', 'path' => 'kpi.status', 'type' => 'text'],
                ['key' => 'kpiRating', 'label' => 'AI-suggested rating', 'path' => 'kpi.suggestedRating', 'type' => 'int'],
                ['key' => 'applicable', 'label' => 'Competencies that apply', 'path' => 'ratings.applicable', 'type' => 'int', 'csv' => true],
                ['key' => 'kpiPlanned', 'label' => 'KPI tasks planned and added', 'path' => 'kpi.planned', 'type' => 'int', 'csv' => true],
                ['key' => 'kpiCompleted', 'label' => 'KPI tasks completed', 'path' => 'kpi.completed', 'type' => 'int', 'csv' => true],
            ],
        ];
    }

    /** @return list<array<string, mixed>> the columns shown on the page and in the PDF */
    public static function visible(string $group): array
    {
        return array_values(array_filter(self::groups()[$group], fn (array $c) => empty($c['csv'])));
    }

    /** @return list<array<string, mixed>> every column, for a spreadsheet */
    public static function all(): array
    {
        return array_merge(...array_values(self::groups()));
    }

    /** Whether a column is a measure, whose value can be unfit to quote. */
    private static function measured(array $column): bool
    {
        return in_array($column['type'], ['pct', 'days', 'score'], true);
    }

    /** The bare value: null when there's nothing, or too little, to quote. */
    public static function value(array $column, array $record): int|float|string|null
    {
        $value = Arr::get($record, $column['path']);

        if (self::measured($column)) {
            return ($value['enough'] ?? false) ? $value['value'] : null;
        }

        return $value;
    }

    /** "Only 2 tasks, needs 3", when the data is too thin to quote; otherwise null. */
    public static function tooFew(array $column, array $record): ?int
    {
        if (! self::measured($column)) {
            return null;
        }

        $value = Arr::get($record, $column['path']);

        return $value !== null && ($value['value'] ?? null) !== null && ! $value['enough'] ? (int) $value['sample'] : null;
    }

    /** The figure as it reads on the page and in the PDF. */
    public static function display(array $column, array $record): string
    {
        $value = self::value($column, $record);

        if ($value === null || $value === '') {
            return '—';
        }

        return match ($column['type']) {
            'pct' => $value.'%',
            'days' => self::trim((float) $value).' d',
            'score' => number_format((float) $value, 2),
            'minutes' => $value > 0 ? Duration::format((int) $value) : '—',
            'decimal' => self::trim((float) $value),
            'int' => $value === 0 ? '0' : number_format((int) $value),
            default => (string) $value,
        };
    }

    /** The value a spreadsheet gets: plain numbers, with minutes as hours. */
    public static function csv(array $column, array $record): int|float|string|null
    {
        $value = self::value($column, $record);

        return $column['type'] === 'minutes' && $value !== null ? round($value / 60, 2) : $value;
    }

    /** The heading a spreadsheet gets, so a value's unit is never a guess. */
    public static function csvHeading(array $column): string
    {
        return match ($column['type']) {
            'pct' => $column['label'].' %',
            'days' => $column['label'].' (days)',
            'minutes' => $column['label'].' (hours)',
            default => $column['label'],
        };
    }

    private static function trim(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2), '0'), '.');
    }
}
