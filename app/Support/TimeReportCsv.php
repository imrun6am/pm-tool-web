<?php

namespace App\Support;

use App\Models\TaskTimeLog;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Time entries as a spreadsheet download. */
class TimeReportCsv
{
    /** @param  Builder<TaskTimeLog>  $entries */
    public static function download(Builder $entries, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($entries) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'Person', 'Team', 'Release', 'Task', 'Title', 'Minutes', 'Hours', 'Note'], escape: '');

            $entries->chunk(500, function ($logs) use ($out) {
                foreach ($logs as $log) {
                    fputcsv($out, [
                        $log->logged_on->toDateString(),
                        self::cell($log->user?->name),
                        self::cell($log->task?->team?->name),
                        self::cell($log->task?->release?->name),
                        $log->task?->reference(),
                        self::cell($log->task?->title),
                        $log->minutes,
                        round($log->minutes / 60, 2),
                        self::cell($log->note),
                    ], escape: '');
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Spreadsheets run a cell starting with one of these as a formula, so people's text can't start that way. */
    public static function cell(?string $value): string
    {
        $value = (string) $value;

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
