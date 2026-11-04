## Why

Developers and QA now open the board and task list on their own tasks. Notes, Meetings and the Calendar still open on everything they can see, so they have to look for their own items there.

## What Changes

- A Developer or QA user who opens Notes or Meetings with no filters sees only what's theirs, with a new My notes or My meetings button selected.
- A Developer or QA user who opens the Calendar lands on its Mine tab.
- "Theirs" includes what was addressed to them: notes sent specifically to them, meetings they attended and events they attend. This also changes the Calendar's existing Mine tab and `GET /events?show=mine` for every role, which used to mean only events they created.
- `GET /notes` and `GET /meeting-notes` accept `mine=1`. API defaults are unchanged.

## Capabilities

### New Capabilities
- `mine-default-notes-calendar`: Developers and QA open Notes, Meetings and the Calendar on their own items.

### Modified Capabilities
<!-- None: `openspec/specs/` has no base specs yet. -->

## Impact

- `ReadsMineFilter`, `NoteController`, `MeetingNoteController`, `CalendarController`, their API controllers, `NoteService`, `MeetingNoteService`, `EventService`
- `notes/index`, `meeting-notes/index`, `calendar/index`, `partials/my-tasks-toggle`
