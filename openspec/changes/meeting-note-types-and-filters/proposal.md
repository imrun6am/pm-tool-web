## Why

Every meeting note is currently an undifferentiated "meeting". Teams run distinct rituals — sync-ups, day-end wrap-ups, handovers, first-round QA feedback — and once notes accumulate there is no way to tell them apart in a list, or to answer "show me the day-end notes for this release" or "what did we hand over in March".

The list only filters by release and meeting-date range. There is no way to narrow by kind of meeting, by who wrote it or who was in the room, or by what was actually discussed — and the index loads every visible note in a single unpaginated page, which only gets worse as notes pile up.

## What Changes

- A meeting note gains a **type**, chosen on create and edit from a server-side constant (`MeetingNote::TYPES`) — the same `key => label` pattern `Event::TYPES` already uses. Adding or retiring a type is a one-line constant edit: no migration, no client release.
- Seed types, in the order they render: **Meeting** (the default), **Sync Up**, **Day End**, **Progress Monitoring**, **Initial Handover**, **Initial QA Feedback**, **Final Handover**.
- **Existing notes are unchanged** — they take the `meeting` default, which keeps its current "Meeting" label, so nothing already recorded reads as re-categorized.
- Types are published at `GET /v1/meta` as `meeting_note_types`, so the desktop client renders its picker and type filter from the server's list instead of embedding its own copy.
- The Meeting Notes list gains a **type filter**, a **"Written by" (author) filter**, an **"Attended by" (attendee) filter**, and a **text search** over title and body — all combinable with the existing release and date-range filters, with one Clear that drops every filter.
- The list becomes **paginated**, with filters preserved across pages.
- The type shows as a coloured badge on list cards, the note's show page, and the release-details and event-details meeting-note cards.
- `GET /v1/meeting-notes` accepts the same filters, so the desktop client and the browser stay in parity.

## Capabilities

### Modified Capabilities

- `meeting-notes`: adds a type attribute driven by a server-defined constant, and extends the Meeting Notes list with type, author, attendee, and text-search filters plus pagination. The new "Combined filtering and pagination" requirement supersedes the filtering clause of the existing "Meeting notes section" requirement.

<!-- Delta specs are written as ADDED requirements, matching the repo convention: main specs under openspec/specs/ have not been synced yet, so there is no synced requirement text for a MODIFIED block to amend. -->

## Impact

- **Database**: `type` string column on `meeting_notes` (default `meeting`, indexed alongside `meeting_date`). Existing rows take the column default — no data backfill pass required.
- **Backend**: `MeetingNote` gains `TYPES` / `TYPE_COLORS` consts, `typeLabel()`, `typeColor()`, `scopeOfType()`; `MeetingNoteService::visibleTo()` grows type/author/attendee/search filters and returns a paginated result; `MeetingNoteRequest` validates `type`; `MetaController` publishes `meeting_note_types`; `MeetingNoteResource` exposes `type`, `type_label`, `type_color`.
- **UI**: type select on the meeting-note form; expanded filter bar and pagination controls on the Meeting Notes index; type badge on cards, the show page, and the release/event meeting-note cards.
- **Docs**: `docs/api-v1.md` — the meeting-notes row gains the new query params, and the meta key is listed.
- **Reverses a prior non-goal**: the original `meeting-notes` design listed full-text search as an explicit non-goal. Search over title and body is now in scope (see design decision 6).
- **No breaking changes**: `type` defaults to `meeting`, every new filter is optional, and the API response only gains fields.
