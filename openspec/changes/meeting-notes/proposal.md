## Why

Teams hold meetings that are either tied to a specific release (planning, standups, retros) or general (all-hands, cross-team syncs), but there is no place in the tool to capture what was discussed and decided. Daily notes are personal/day-scoped and comments are threaded discussion — neither fits structured meeting minutes that need to be findable from the release they concern.

## What Changes

- Add a meeting notes feature: rich-text notes with a title and meeting date, authored by any authenticated user.
- A meeting note can be **release-wise** (linked to a release) or **general** (no release).
- New top-level "Meeting Notes" section (navbar entry) listing all meeting notes with filtering by release / general, where users can create, view, edit, and delete notes.
- Release details page gains a "Meeting Notes" card showing that release's notes, with a shortcut to create a note pre-linked to the release.
- Meeting-type calendar events gain a "Write meeting note" shortcut that opens the note form pre-filled from the event and links the note to that event; the event's details page lists its meeting notes. A note related to both a release and an event is visible on both details pages.
- Note body uses the existing Trix rich-text + HTML sanitization pipeline.
- Authors can edit/delete their own notes; admins can also delete any meeting note (they are shared team artifacts).

## Capabilities

### New Capabilities
- `meeting-notes`: Creating, listing, viewing, editing, and deleting meeting notes; optional linkage of a note to a release; release-details visibility of that release's notes; access rules (all users read/create, author edits, author/admin deletes).

### Modified Capabilities

<!-- None. Main specs have not been synced yet; the release-details "Meeting Notes" card requirement is covered inside the new `meeting-notes` capability rather than as a delta to a release spec. -->

## Impact

- **Database**: new `meeting_notes` table (`release_id` nullable FK following the `events` precedent, `created_by`, `title`, `meeting_date`, `body`).
- **Backend**: new `MeetingNote` model, `MeetingNoteController`, `MeetingNoteRequest`, `MeetingNotePolicy`; `Release` gains a `meetingNotes()` relationship and cleanup cascade.
- **Routes**: new `meeting-notes` resource routes in the authenticated collaboration section of `routes/web.php`.
- **UI**: new `resources/views/meeting-notes/` views; new card on `releases/show.blade.php`; new navbar item in `layouts/navigation.blade.php`.
- **No breaking changes**; daily notes, comments, and calendar events are untouched.
