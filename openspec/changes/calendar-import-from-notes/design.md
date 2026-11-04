## Context

`EventImportService::startFromText` already stores text on the private disk and queues the job, so the job, chunking, retry and delete treat it like an upload. A note import can take the same path. Three things differ from a paste: the text comes from a record with its own read rules, relative dates should not be resolved against today, and the history needs a useful label.

## Decisions

### A third `input` value, with a polymorphic origin
`input` becomes `file | paste | note`, which fits the existing 8-character column. `origin_type` and `origin_id` point at the `MeetingNote` or `Note`. `origin_*` is used because `source` already means `ics | claude`. `original_name` holds the label ("Meeting note: Sprint sync"), so `displayName()` needs no new branch and the label survives if the note is later deleted. Notes are hard-deleted, so `origin` simply becomes null.

### The reference date is an optional context key
`reference_date` is stored on the import, so a retry uses the same value. The service passes it as `written_on`, and the prompt prefers it over today for relative dates and for missing years. Uploads and pastes pass null and behave as before. `today` stays in the context.

### The text is a snapshot, removed once extraction succeeds
The note's plain text is written to the private disk when the person clicks, so the permission check and the text read happen together, and a retry resumes from the same chunks. The note stays the source of truth, so the copy is deleted when extraction finishes. A failed import keeps it for retry until that import is deleted. The review page has no "show text" panel for notes; it links to the note instead.

### Permissions
- **Start:** `MeetingNotePolicy::view`, and a new `NotePolicy::view`. Both use the model's `isVisibleTo`.
- **Retry:** the starter, and only while they can still read the note. This stops a revoked reader from sending the stored text to Claude again. A deleted note also blocks retry.
- **Opening an import:** today admins may open anyone's import. For a note import an admin must also be able to read the note. Otherwise an admin could read a private daily note's contents through the proposals, which `NotePolicy` says even admins may not do. Admins keep delete, which reveals nothing.
- **Confirm:** unchanged, the starter only. The starter keeps a draft they could read when they made it, even if they later lose access to the note.

### Plain text
`noteText` always treats the body as HTML. `pastedText` only converts when it spots markup, which would leave `Q&amp;A` undecoded in a note with no tags. `noteText` also keeps link targets ("Join (https://…)") and turns non-breaking spaces into spaces, so a note holding only `&nbsp;` counts as empty.

### Feature gate
Turning `calendar_import` off has to stop every Claude call this feature makes, so there are two layers:

- **At the request:** the note routes refuse when the feature is not available (switched off, or no API key). The upload and paste requests apply the same check as a validation error, except for `.ics` files and pasted iCalendar data, which don't need Claude.
- **In the job:** `process()` checks the switch right before the first Claude call. This catches what the request can't see: imports queued before the switch went off, retries, and `.ics` files with no events, which fall through to Claude. The import fails with a message that says why, and the stored text stays, so it can be retried once the feature is back on.

`.ics` uploads had to be fixed for this to hold. Their content can be detected as `text/x-vcalendar`, which maps to the extension `vcs`, so the `mimes` rule refused real calendar files. `vcs` is now allowed for the content check only; the `extensions` rule still requires a listed file name.

The job checks only the switch, not the key: `ClaudeEventExtractor` already reports a missing key with its own message.

### Repeat clicks
If this person already has a running import for this note, they are sent to it instead of paying for a second one. Finished imports do not block a new one, since the note may have been edited; the review page's duplicate flag still catches events already on the calendar.

## Risks

- Proposals default to **Shared**, as in the existing review flow, even when they come from a private note. The reviewer can switch each row or all rows to Private before confirming.
- A note's title is supplied by whoever wrote it and reaches the prompt as the source name. Angle brackets are removed and the system prompt treats it as data. Proposals are always reviewed by a person.
