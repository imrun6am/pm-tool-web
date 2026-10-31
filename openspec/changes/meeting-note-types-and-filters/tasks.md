## 1. Database & Model

- [x] 1.1 Migration: add `type` string to `meeting_notes`, default `meeting`, with an index on (`type`, `meeting_date`) for the type-filtered list
- [x] 1.2 `MeetingNote`: `TYPES` const in render order (`meeting`, `sync_up`, `day_end`, `progress_monitoring`, `initial_handover`, `initial_qa_feedback`, `final_handover`) + `TYPE_COLORS`; `type` in fillable; `TYPE_DEFAULT`; `typeLabel()` and `typeColor()` with the same unknown-value fallback `Event` uses; `scopeOfType()`
- [x] 1.3 Run the migration and verify every existing note reads as type `meeting` labelled "Meeting"

## 2. Filtering, Search & Pagination

- [x] 2.1 `MeetingNoteService::visibleTo()`: add `type`, `author`, `attendee`, and `search` filters onto the existing release/date query, each via `->when()` so any combination composes; every filter applied after `visibleTo()` scoping
- [x] 2.2 Search: match `title` and `body` with `LIKE ? ESCAPE '!'`, escaping `!`, `%`, and `_` in the term so wildcards stay literal identically on MySQL and SQLite (a backslash escape diverges between the two — see design decision 6)
- [x] 2.3 Attendee filter via `whereHas('attendees')`; author filter on `created_by`
- [x] 2.4 Paginate the web index (`->paginate()` + `withQueryString()`); keep the service returning a builder so the API's existing `paginate()` path is untouched

## 3. Validation & Form

- [x] 3.1 `MeetingNoteRequest`: `type` validated with `Rule::in(array_keys(MeetingNote::TYPES))`; `prepareForValidation()` supplies an omitted type — the default when creating, the note's current type when updating — so a write that never mentions the type is accepted without silently re-categorizing the note, while an explicit unknown type still fails
- [x] 3.2 Meeting-note form: type select defaulting to `meeting`; notes created from a calendar event keep the default (the event's calendar type is not mapped across)

## 4. Web UI

- [x] 4.1 Index filter bar: type / release / written-by / attended-by selects, date range, and a search box in one GET form, with Apply and a single Clear that drops every param
- [x] 4.2 `MeetingNoteController@index`: read and pass the new filter params; supply the user list for the person filters and the type list for the type filter
- [x] 4.3 Type badge (using `TYPE_COLORS`) on index cards, the show page, and the release-details and event-details meeting-note cards
- [x] 4.4 Pagination controls on the index; empty state keeps the active filters visible and offers Clear

## 5. API & Docs

- [x] 5.1 `MetaController`: publish `meeting_note_types` via `options(MeetingNote::TYPES, MeetingNote::TYPE_COLORS)`
- [x] 5.2 `MeetingNoteResource`: expose `type`, `type_label`, `type_color`
- [x] 5.3 `Api\V1\MeetingNoteController@index`: validate and pass `type`, `author`, `attendee`, `search` through to the service
- [x] 5.4 `docs/api-v1.md`: update the meeting-notes query params and list the new `meeting_note_types` meta key

## 6. Tests & Verification

- [x] 6.1 Feature tests: type persisted on create/update; unknown type rejected; a note submitted without a type gets the default
- [x] 6.2 Feature tests: each filter narrows correctly, and type + release + author + attendee + date range compose
- [x] 6.3 Feature tests: search matches a title-only hit and a body-only hit; a wildcard in the term is literal
- [x] 6.4 Feature tests: author and attendee filters and search never surface an attendees-only note to a non-viewer
- [x] 6.5 API tests: `/v1/meta` exposes the type list; the index accepts the new filters; web/API parity holds for the filtered query
- [x] 6.6 Run the full test suite and fix regressions
