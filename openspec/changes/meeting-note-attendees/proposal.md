## Why

Meeting notes currently record what was discussed but not **who was there**, and every note is visible to every authenticated user. Teams need to capture attendance and to keep sensitive minutes (retros, 1:1s, incident reviews) visible to only the people who were in the room.

## What Changes

- A meeting note can record its **attendees** — a set of users selected when creating or editing the note.
- A meeting note gains a **visibility** setting: **Everyone** (current behaviour, the default) or **Attendees only**.
- An **Attendees-only** note is viewable only by its attendees, its author, and the leadership tier (admin, CTO, tech lead, team lead); it is hidden from everyone else — on the Meeting Notes list, the release details card, and the event details card.
- When a note is created from a meeting-type **event**, its attendees pre-fill from the event's attendees.
- The note's show page lists attendees and shows an "Attendees only" badge; the list/cards show an attendee count and hide notes the viewer may not see.

## Capabilities

### Modified Capabilities
- `meeting-notes`: adds attendee selection and a per-note visibility scope (Everyone / Attendees only) with server-side view enforcement across the list, release card, and event card.

## Impact

- **Database**: new `meeting_note_user` pivot (attendees); new `visibility` column on `meeting_notes` (`everyone` | `attendees`, default `everyone`).
- **Backend**: `MeetingNote` gains an `attendees()` relation, `visibility` handling, and a `scopeVisibleTo()`; `MeetingNoteController` syncs attendees and scopes every listing; `MeetingNoteRequest` validates attendees + visibility; `MeetingNotePolicy` gains a `view` check.
- **UI**: attendee multi-select + visibility control on the meeting-note form; attendees list + badge on the show page; visibility-scoped queries on the index, release card, and event card.
- **No breaking changes**; existing notes default to Everyone and keep behaving as today.
