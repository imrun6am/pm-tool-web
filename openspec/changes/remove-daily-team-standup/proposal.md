## Why

The daily team standup (`daily-team-standup`) made one meeting note per team each working day from the tasksheets. It isn't wanted: teams don't need a generated standup note, and it fills the meeting notes list with a note per team per day.

## What Changes

- **BREAKING** Remove standup generation: `DailyStandupService`, the `standup:generate` command and its schedule entry, the web `POST /standups` action and the API `POST /api/v1/standups` endpoint, and the tasksheet's standup link.
- **BREAKING** Remove the `standup` block from meeting notes in the API and the `standup` meeting note type from `meeting_note_types`.
- Meeting notes go back to how they worked before: author-only editing, a required body, and the usual visibility rules.
- A migration copies each generated note's tasksheet listing into its body, sets its type to Meeting and its visibility to attendees only, then drops `daily_standups`.

Unchanged: the tasksheet's standup attendance (`POST /tasksheet/standup`, `verifyStandup`) and the AI standup digest. Both came before this feature and don't depend on it.

## Capabilities

### Removed Capabilities
- `daily-team-standup`: every requirement is removed.

## Impact

- **Backend**: `DailyStandup`, `DailyStandupPolicy`, `DailyStandupService`, `GenerateDailyStandups`, `StandupRun`, `GenerateStandupRequest`, both `DailyStandupController`s and `DailyStandupResource` are deleted. `MeetingNote`, `MeetingNotePolicy`, `MeetingNoteRequest`, `MeetingNoteService`, `MeetingNoteResource`, both `MeetingNoteController`s, `PeopleAnalyticsService` and `WorkspaceAsk` lose their standup branches.
- **Data**: new migration `2026_11_14_100001_drop_daily_standups_table`. `meeting_notes.created_by` stays nullable.
- **Frontend**: the meeting note page, list card and form lose their standup branches; the tasksheet loses the standup link.
- **API**: `docs/api-v1.md` drops the standup section and `POST /standups`.
- **Tests**: `DailyStandupTest` is replaced by `DropDailyStandupsMigrationTest`.
