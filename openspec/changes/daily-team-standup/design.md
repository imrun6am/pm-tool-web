## Context

`TasksheetEntry` holds a member's morning plan, day-end result, comment and tickets, or a leave. `TasksheetResultSync` adds to today's result as tasks finish, so a sheet changes all day. `StandupDigestGenerator` already sends a team's day to the AI at 08:30 for the previous working day, for leads only. `MeetingNote` is where people already look for meetings, but it assumes a human author, its `body` is required, and only its author can edit it.

## Goals / Non-Goals

**Goals:**
- One standup record per team and working day, correct without anyone clicking, and useful with AI off.
- Re-running it is always safe: no duplicates, and hand-written content survives.
- Visibility matches the team's tasksheet.

**Non-Goals:**
- A new AI feature. The digest stays the only AI call.
- Notifying anyone. The notification policy leaves digest-ready and tasksheet nudges out, and a morning burst to whole teams is what it's trying to avoid.
- Standups for days the working week says are off, or per-holiday calendars.

## Decisions

### A standup is a meeting note plus a `daily_standups` row
`daily_standups` has one row per team and date (unique), linked to the note, and holds everything generated: structured `content`, a `fingerprint`, `search_text`, `generated_at`, `attendees_edited_at`. The note keeps only what people own: title, attendees and `body`.

The generator never writes `body`, so "don't overwrite hand edits" needs no markers or merging. Considered instead: writing the listing into `body` between markers. Trix and the sanitizer strip markers, and a person's edit inside the block would be lost on the next run.

Refreshing writes only the `daily_standups` row, so it doesn't touch the activity log or the note's `updated_at`. Creating the note skips model events too: one "created" line per team per day in the shared feed is noise, and there's no user to attribute it to.

### The row outlives the note, which makes deletion stick
`meeting_note_id` is `nullOnDelete`. A row with no note means someone deleted it, and the scheduled run leaves it alone. Asking explicitly (the web action, the API or `--restore`) creates it again. Without this, a lead's delete would be undone within 15 minutes. Soft-deleting meeting notes would also work but changes every existing delete path.

### A generated note has no author
`created_by` becomes nullable and stays null. Naming the team lead would be false, would credit them with a note in people analytics, and would break for a team with no lead. `$note->author` is already handled as nullable in the views, and the API's `author` can be null.

### Which days it covers
For date D: "did" comes from the row on `WorkingWeek::previous(D)`, and "today" comes from D's row (plan, plus result, comment and tickets so far). The roster is `TasksheetService::rowUsersFor`, the same people the tasksheet page lists. Only `plan`, `result`, `comment`, `tickets` and `leave_type` are read. `feedback`, points, counts and standup attendance are never copied.

A team has nothing to report when nobody has a leave or any text on D, and nobody logged a result, comment or tickets on the previous working day. A team with no such content and no existing note gets nothing.

### Updating
The command runs every 15 minutes through the working day and compares a fingerprint, so an unchanged team costs a few reads and no writes. Hooking `TasksheetEntry` saves was rejected: a move to Done must never wait on or fail with this work, and the queue worker is started by hand here. Past days aren't refreshed by the schedule, only by hand.

### Schedule
Plans are saved mostly between 09:00 and 09:59 on the app's clock and results between 18:00 and 19:59. The first run is at 08:45, after the 08:30 digest so its summary can be picked up, and the last is at 20:00. Everything uses the app clock, like the digest and reminders.

### AI: reuse the digest
Choice: the note's listing is plain and always exact, and the note quotes the digest's `summary` and `blockers` when a ready digest exists for the previous working day and `standup_digest` is available. Adding a second feature would pay twice to say nearly the same thing and leave two standup summaries that can disagree.

- The digest still runs at 08:30 and stays on the tasksheet for leads, with its follow-ups and attendance counts. Those are addressed to the lead, so the note leaves them out.
- A failed or missing digest means no AI block and nothing else changes. The next run picks one up once it's ready.
- The digest doesn't read today's plans, so the AI block is labelled with the day it covers.
- No AI schema changes.

### Visibility and editing
`scopeVisibleTo` gains one branch for limited users: a note with a standup row is visible only when the row's team is one of theirs, whatever its attendees or author. That matches `TasksheetService::teamsFor`. Leads and viewers already see every tasksheet and every everyone-visible note, so nothing changes for them. `visibility` stays `everyone` and is locked, so it can't drift from this.

Editing goes to leads and the team's current members, mirroring `TasksheetEntryPolicy`. Only title, attendees and body can change. Type, date, release, event and visibility are ignored on a generated note, because the type and date identify it and the release could reveal it to other teams. Deleting stays with leads.

### Attendees
Current active team members plus the team lead, minus anyone on full-day leave and anyone a lead marked as having missed the standup. Generated attendees send no notification. The first hand edit that changes the list sets `attendees_edited_at`, and the generator stops touching it.

These attendees are who was expected, not verified attendance, so people analytics doesn't count them.

## Risks / Trade-offs

- **The list gets busier.** Each team adds a note per day to the meeting notes list. Developers and QA see only their own team's. Leads can filter by type. → Accepted for now; hiding them by default is a small later change.
- **Up to 15 minutes behind.** → The page shows the update time and has a refresh button.
- **Ask AI doesn't read standups.** It's left out so generated notes don't crowd the latest real minutes out of its context. → Possible follow-up.
- **Clock.** `app.timezone` is UTC, so the schedule follows the app's clock, not necessarily the team's wall clock. → Same as every other scheduled job here.
