<?php

/*
|--------------------------------------------------------------------------
| Claude Code activity monitor
|--------------------------------------------------------------------------
|
| Devices running Claude Code post an event here (session start, each prompt,
| session end) through a hook installed by tools/claude-monitor. Nothing edits
| a row, so an entry survives the conversation being deleted later; only the
| log's viewers can delete entries, from the Claude activity pages.
|
*/

return [
    // Shared secret the hook sends as a bearer token. Ingest is off while blank.
    'token' => env('CLAUDE_MONITOR_TOKEN'),

    // Comma-separated user ids allowed to read the log. Blank = admins only.
    // Ids, not emails: anyone can change their own email in Profile, so an
    // email nobody holds yet would let the first person to take it in. An
    // entry that isn't an id matches nobody, so a mistake shuts the page.
    'viewers' => array_values(array_filter(array_map(
        fn ($id) => trim($id),
        explode(',', (string) env('CLAUDE_MONITOR_VIEWERS', '')),
    ), fn ($id) => $id !== '')),

    // How much of each prompt is kept, at most 1,000 characters (the column's
    // size). The full prompt is never stored.
    'prompt_excerpt_length' => (int) env('CLAUDE_MONITOR_PROMPT_CHARS', 500),
];
