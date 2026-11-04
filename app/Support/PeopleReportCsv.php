<?php

namespace App\Support;

use App\Models\Task;
use App\Models\User;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** The people report as spreadsheets. A rate with too little data is left blank; its counts stay. */
class PeopleReportCsv
{
    /** @param  list<array<string, mixed>>  $rows */
    public static function people(array $rows, string $filename): StreamedResponse
    {
        return self::stream($filename, ['Person', 'Email', 'Role', 'Teams', 'Note'], $rows, fn (array $row) => [
            TimeReportCsv::cell($row['user']->name),
            TimeReportCsv::cell($row['user']->email),
            User::ROLES[$row['user']->role] ?? $row['user']->role,
            TimeReportCsv::cell(implode(', ', $row['teams'])),
            TimeReportCsv::cell($row['tag'] ?? $row['user']->statusTag()),
        ]);
    }

    /** @param  list<array<string, mixed>>  $rows */
    public static function teams(array $rows, string $filename): StreamedResponse
    {
        return self::stream($filename, ['Team', 'Lead', 'People'], $rows, fn (array $row) => [
            TimeReportCsv::cell($row['name']),
            TimeReportCsv::cell($row['lead']?->name),
            $row['people'],
        ]);
    }

    /**
     * The tasks behind a person's figures.
     *
     * @param  list<array<string, mixed>>  $finished
     * @param  list<Task>  $open
     */
    public static function tasks(array $finished, array $open, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($finished, $open) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['State', 'Task', 'Title', 'Status', 'Team', 'Release', 'Due', 'Finished', 'On time', 'Cycle days', 'Reworked', 'Estimate (hours)', 'Logged (hours)', 'Blocked'], escape: '');

            foreach ($finished as $row) {
                $task = $row['task'];
                fputcsv($out, [
                    'Finished', $task->reference(), TimeReportCsv::cell($task->title), $task->statusLabel(),
                    TimeReportCsv::cell($task->team?->name), TimeReportCsv::cell($task->release?->name),
                    $task->due_date?->toDateString(), $row['completedAt']->toDateString(),
                    $row['onTime'] === null ? '' : ($row['onTime'] ? 'Yes' : 'No'),
                    $row['cycleDays'], $row['reworked'] ? 'Yes' : 'No',
                    self::hours($task->estimate_minutes), self::hours((int) $task->logged_minutes),
                    '',
                ], escape: '');
            }

            foreach ($open as $task) {
                fputcsv($out, [
                    'Open', $task->reference(), TimeReportCsv::cell($task->title), $task->statusLabel(),
                    TimeReportCsv::cell($task->team?->name), TimeReportCsv::cell($task->release?->name),
                    $task->due_date?->toDateString(), '', '', '', '',
                    self::hours($task->estimate_minutes), self::hours((int) $task->logged_minutes),
                    $task->isBlocked() ? 'Yes' : 'No',
                ], escape: '');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @param  list<string>  $lead  the columns naming the row
     * @param  list<array<string, mixed>>  $rows
     */
    private static function stream(string $filename, array $lead, array $rows, \Closure $name): StreamedResponse
    {
        $columns = PeopleReportColumns::all();

        return response()->streamDownload(function () use ($lead, $rows, $name, $columns) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [...$lead, ...array_map(PeopleReportColumns::csvHeading(...), $columns)], escape: '');

            foreach ($rows as $row) {
                fputcsv($out, [
                    ...$name($row),
                    ...array_map(fn (array $column) => PeopleReportColumns::csv($column, $row['record']), $columns),
                ], escape: '');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private static function hours(?int $minutes): ?float
    {
        return $minutes ? round($minutes / 60, 2) : null;
    }
}
