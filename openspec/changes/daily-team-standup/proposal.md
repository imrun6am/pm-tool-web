## Why

Teams hold a standup every working day, and everything they need for it is already on the tasksheet: what each person logged yesterday, what they plan today, and who is off. Someone has to read the sheet row by row to get that picture, and nothing keeps a record of the meeting. The AI standup digest helps only leads, only on the tasksheet page, and only when AI is on.

## What Changes

- Each working day, each active team with something to report gets one meeting note of a new **Standup** type, built from its tasksheets: what each person did on the previous working day, what they plan today, what's finished so far today, who is on leave, and who hasn't filled theirs in.
- The note refreshes on a schedule through the day and can be refreshed by hand. It never duplicates, and the listing needs no AI.
- Generated content lives apart from the note's own `body`, so anything written by hand is never overwritten. Attendees come from the team until someone edits them by hand; after that they're left alone. A note someone deleted stays deleted unless it's asked for again.
- The note shows the summary and blockers from the existing AI standup digest when one is ready. No second AI call is added: the digest stays the one AI layer, and the digest's lead-only follow-ups stay on the tasksheet.
- A standup is visible to whoever can see that team's tasksheet. No notifications are sent.
- New web action and API endpoint to generate or refresh a standup, a link from the tasksheet page, and a `standup:generate` command.

## Capabilities

### New Capabilities
- `daily-team-standup`: one generated standup note per team and working day, kept current from the tasksheets.

### Modified Capabilities
<!-- None: `openspec/specs/` has no base specs yet. -->

## Impact

- **Backend**: new `daily_standups` table, `DailyStandup` model and policy, `DailyStandupService`, `standup:generate` command and schedule. `meeting_notes.created_by` becomes nullable. `MeetingNote` gains the `standup` type and team-based visibility for these notes. `MeetingNoteService`, `MeetingNotePolicy` and `MeetingNoteRequest` treat generated notes differently. People analytics and Ask AI leave generated notes out.
- **Frontend**: the meeting note page, list card and edit form; a standup link on the tasksheet page.
- **API**: `standup` block on meeting notes, `POST /standups`, a new `standup` value in `meeting_note_types`; `author` can be null on a generated note. See `docs/api-v1.md`.
- **Tests**: `DailyStandupTest`.
