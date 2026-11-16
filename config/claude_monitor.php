<?php

/*
|--------------------------------------------------------------------------
| Claude Code activity monitor
|--------------------------------------------------------------------------
|
| Devices running Claude Code post an event here (session start, each prompt,
| session end) through a hook installed by tools/claude-monitor. The log is
| append-only: there is no route or UI that edits or deletes a row, so an
| entry survives even when the conversation itself is deleted later.
|
*/

return [
    // Shared secret the hook sends as a bearer token. Ingest is off while blank.
    'token' => env('CLAUDE_MONITOR_TOKEN'),

    // Comma-separated emails allowed to read the log. Blank = admins only.
    'viewers' => array_values(array_filter(array_map(
        fn ($email) => strtolower(trim($email)),
        explode(',', (string) env('CLAUDE_MONITOR_VIEWERS', '')),
    ))),

    // How much of each prompt is kept. The full prompt is never stored.
    'prompt_excerpt_length' => (int) env('CLAUDE_MONITOR_PROMPT_CHARS', 300),
];
