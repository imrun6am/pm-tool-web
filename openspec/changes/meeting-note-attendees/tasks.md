## 1. Database & Model

- [x] 1.1 Migration: `meeting_note_user` pivot (`meeting_note_id`, `user_id`, timestamps, unique pair); add `visibility` string to `meeting_notes` (default `everyone`), indexed
- [x] 1.2 `MeetingNote`: `attendees()` belongsToMany (`withTimestamps()`, `withTrashed()`); `VISIBILITIES` const + `visibility` in fillable; `isAttendeesOnly()` helper; `scopeVisibleTo(User)` (everyone OR author OR attendee OR lead)
- [x] 1.3 Run migration and verify schema

## 2. Validation, Policy & Controller

- [x] 2.1 `MeetingNoteRequest`: `visibility` in:everyone,attendees; `attendees` nullable array, `attendees.*` exists:users,id
- [x] 2.2 `MeetingNotePolicy@view`: reuse the visibleTo predicate (everyone/author/attendee/lead) — return false otherwise
- [x] 2.3 `MeetingNoteController`: authorize `view` in `show`; sync attendees on store/update; persist `visibility`; scope `index` by `visibleTo`; create-from-event seeds attendees from the event; load `attendees` on show/edit

## 3. Views & Integration

- [x] 3.1 Meeting-note form: attendee multi-select (active users, event-prefill) + visibility select (Everyone / Attendees only) with a note that leads can always read attendees-only notes
- [x] 3.2 Show page: attendee chips (with user tags) + "Attendees only" badge; index cards: attendee count
- [x] 3.3 Scope the release-details and event-details meeting-notes cards by `visibleTo` (eager-load `attendees`); apply `visibleTo` counts/lists consistently

## 4. Tests & Verification

- [x] 4.1 Feature tests: store/update syncs attendees; visibility persisted; invalid visibility rejected; event create prefills attendees
- [x] 4.2 Feature tests: attendees-only visibility — attendee/author/lead can view (200), non-attendee non-lead gets 403; everyone-notes public
- [x] 4.3 Feature tests: listings (index, release card, event card) hide attendees-only notes from non-viewers and show everyone-notes
- [x] 4.4 Run full test suite and fix regressions
