<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Background PDF exports
    |--------------------------------------------------------------------------
    |
    | chunk_rows bounds how many tasksheet rows one queued job loads and
    | renders, which is what keeps a worker's peak memory flat however wide
    | the export is. Smaller chunks mean more parts (each embeds its own font
    | subset); larger ones mean more memory per job. Measured on real rows:
    | ~40 MB + ~0.75 MB per row, so 75 keeps a job near 95 MB, under
    | queue:work's default 128 MB limit. (The old single render of 1,383
    | rows peaked at 2.6 GB.)
    |
    */

    'export' => [
        'chunk_rows' => (int) env('TASKSHEET_EXPORT_CHUNK_ROWS', 75),
        'max_running' => (int) env('TASKSHEET_EXPORT_MAX_RUNNING', 3),
        'retention_days' => (int) env('TASKSHEET_EXPORT_RETENTION_DAYS', 14),
    ],

];
