## 1. Database & Model

- [x] 1.1 Create `meeting_notes` migration: `release_id` nullable FK (`nullOnDelete`), `created_by` FK to users (`cascadeOnDelete`), `title` string, `meeting_date` date, `body` text, timestamps, index on (`release_id`, `meeting_date`)
- [x] 1.2 Create `MeetingNote` model: fillable, `meeting_date` cast, `body` Attribute setter running `HtmlSanitizer::clean()`, `bodyHtml()` accessor, `author()` and `release()` belongsTo relations, `scopeForRelease`/`scopeGeneral` query scopes
- [x] 1.3 Add `meetingNotes()` hasMany relation to `Release` model
- [x] 1.4 Run migration and verify schema

## 2. Authorization & Validation

- [x] 2.1 Create `MeetingNotePolicy`: view/viewAny/create for all authenticated users, update for author only, delete for author or admin (docblock noting the deliberate divergence from `NotePolicy`)
- [x] 2.2 Create `MeetingNoteRequest`: title required max:255, meeting_date required date, release_id nullable exists:releases,id, body required max:20000 with the visually-empty Trix markup check (reuse pattern from `NoteRequest`)

## 3. Controller & Routes

- [x] 3.1 Create `MeetingNoteController` with index (filter by `release` query param incl. `general` sentinel, order by meeting_date desc, eager-load author + release), create (preselect release from `?release={id}`), store, show, edit, update, destroy — with policy authorization and flash messages
- [x] 3.2 Register `meeting-notes` resource routes in the collaboration section of `routes/web.php`
- [x] 3.3 Record activity on the release when a linked meeting note is created (RecordsActivity pattern)

## 4. Views

- [x] 4.1 Create `meeting-notes/index.blade.php`: card list (title, meeting date, author, release badge, body excerpt), filter control (All / General / per-release), "New meeting note" button, empty state
- [x] 4.2 Create `meeting-notes/form.blade.php` shared partial + `create.blade.php` and `edit.blade.php`: title input, meeting date input, release select (optional, preselectable), Trix editor bound to hidden `body` input, validation error display
- [x] 4.3 Create `meeting-notes/show.blade.php`: full sanitized body via `bodyHtml()` with `prose-notes` styling, title, date, author, link to release when set, edit/delete actions gated by policy
- [x] 4.4 Add "Meetings" entry to the `$nav` array in `layouts/navigation.blade.php` (route `meeting-notes.index`, pattern `meeting-notes.*`)

## 5. Release Details Integration

- [x] 5.1 Eager-load `meetingNotes.author` (capped, latest first) in `ReleaseController@show`
- [x] 5.2 Add Meeting Notes card to `releases/show.blade.php` main column below Comments: recent notes list linking to show pages, "view all" link to release-filtered index, "New meeting note" button with `?release={id}`, empty state

## 6. Tests & Verification

- [x] 6.1 Feature tests: create general and release-linked notes, validation rejections (empty body, missing title/date), sanitization of script tags
- [x] 6.2 Feature tests: index filtering (all / release / general), release show page displays linked notes
- [x] 6.3 Feature tests: policy — non-author cannot edit, author and admin can delete, non-author non-admin cannot delete
- [x] 6.4 Feature test: deleting a release nulls `release_id` on its notes (notes survive as general)
- [x] 6.5 Run full test suite and fix regressions

## 7. Revision: exclude completed releases from linking

- [x] 7.1 Create form lists only ongoing releases (`Release::ongoing()`); edit form additionally includes the note's own since-completed release
- [x] 7.2 `MeetingNoteRequest` rejects completed release ids, allowing a note's existing link to persist
- [x] 7.3 Feature tests: completed release absent from create form, store rejects it, edit keeps an existing since-completed link

## 8. Revision: create note from a meeting-type event (prefill only)

- [x] 8.1 `MeetingNoteController@create` seeds title and meeting date from `?title=` / `?date=` query params (alongside existing `?release=`)
- [x] 8.2 Event show page: "Write meeting note" button for meeting-type events carrying the event's title, start date, and release; hidden for other types; add `Event::isMeeting()` helper
- [x] 8.3 Feature tests: button shown only for meeting events, create form pre-fills from event params

## 9. Revision: persistent event link — note visible on both release and event pages

- [x] 9.1 Migration: add nullable `event_id` FK (`nullOnDelete`) to `meeting_notes`
- [x] 9.2 `MeetingNote::event()` belongsTo + fillable; `Event::meetingNotes()` hasMany; `event_id` in `MeetingNoteRequest`
- [x] 9.3 Create-from-event flow: button passes `?event={id}`; controller seeds title/date/release/`event_id` from the event; form carries hidden `event_id` with a "Linked to event" hint
- [x] 9.4 Event show page: Meeting notes card listing linked notes; note show page displays linked event; `EventController@show` eager-loads notes
- [x] 9.5 Feature tests: note from event stores `event_id`, dual visibility (release + event pages), event deletion keeps notes; run full suite

## 10. Revision: date-range filter on the meeting notes list

- [x] 10.1 `MeetingNoteController@index`: filter by `from`/`to` meeting-date params (reversed span swapped), combinable with the release filter
- [x] 10.2 Index view: from/to date inputs in the filter form (release select carries over), Apply + Clear controls
- [x] 10.3 Feature tests: date span filtering, reversed span, combined release + date filter
