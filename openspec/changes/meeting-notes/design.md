## Context

The app is server-rendered Laravel (Blade + Alpine.js + Tailwind), traditional form POSTs with redirects and flash messages. Rich text is Trix, sanitized through `App\Support\HtmlSanitizer` via a model Attribute setter (see `Note`), with a FormRequest guard against visually-empty Trix markup (`NoteRequest`). Two existing patterns matter here:

- **Event** already models "release-wise or general" with a nullable `release_id` FK (`nullOnDelete`) — no polymorphism.
- **Daily notes** are personal/day-scoped with a private/shared visibility flag; meeting notes are a different thing (shared team artifacts with a title and meeting date) and must not be conflated with them.

The release details page (`releases/show.blade.php`) is stacked cards in a 2-column grid; new sections are added as card includes, not tabs.

## Goals / Non-Goals

**Goals:**
- Capture meeting minutes as rich-text notes with a title and meeting date.
- Support release-linked and general notes with one model and one UI.
- Surface a release's notes on its details page; provide a standalone browsable section for all notes.
- Reuse existing conventions: nullable FK, Trix + sanitizer pipeline, policy-based authorization, resource controller + FormRequest + Blade views.

**Non-Goals:**
- No attendee tracking on notes themselves (the linked `Event` already tracks attendees; duplicating that here is deferred).
- No private visibility — all meeting notes are visible to every authenticated user.
- No file attachments, comments on notes, or full-text search.

## Decisions

1. **Dedicated `MeetingNote` model with nullable `release_id`** — follows the `Event` precedent exactly. Alternatives: (a) polymorphic `notable` relation — rejected, only `Comment` uses polymorphism and we have exactly one optional parent type; (b) extending the daily `Note` model with a type column — rejected, daily notes have per-user visibility semantics and day navigation that don't apply, and mixing the two would complicate `NotePolicy` and the notes UI.

2. **Columns**: `release_id` (nullable FK, `nullOnDelete`), `created_by` (FK users, `cascadeOnDelete` following `notes.user_id`), `title` (string), `meeting_date` (date), `body` (text, sanitized HTML). Timestamps. Index on (`release_id`, `meeting_date`) for the release card query.

3. **Top-level resource routes, not nested** — `meeting-notes` CRUD lives in the collaboration section of `routes/web.php` (open to all authenticated users, like notes/comments/events). Release pre-linking on create uses a `?release={id}` query param that preselects the release dropdown, rather than a nested `releases/{release}/meeting-notes` resource. Rationale: one set of routes/views serves both entry points; nested routes would duplicate create/edit flows for the general case.

4. **Dedicated pages (index/create/show/edit) instead of inline forms** — unlike daily notes' inline Alpine editing, a meeting note has title + date + release + long rich body; a full-page form matches the releases form pattern better. The show page renders the full sanitized body; index shows cards with title, date, release badge, author, and a body excerpt.

5. **Index filtering** — filter tabs/dropdown: All / General / per-release, driven by a `release` query param (`general` sentinel for release-less notes), plus an optional meeting-date range via `from`/`to` params (reversed spans swapped, mirroring daily notes). All filters live in one GET form so they compose. Default sort: `meeting_date` desc.

4a. **Create-from-event links the note to the event** — the event show page shows a "Write meeting note" action for `type === 'meeting'` events, linking to the create form with `?event={id}`; the controller seeds title, meeting date, release, and `event_id` from that event. `meeting_notes.event_id` is a nullable FK (`nullOnDelete`, same rationale as `release_id`: deleting an event never destroys minutes). The event's details page lists its linked notes, so a note related to both a release and an event appears on both details pages — one record, two entry points. The form carries the event link as a hidden field showing a "Linked to event" hint; unlinking is not offered in the UI. If the event's release has completed, the release prefill is simply not selectable (rule 5a wins).

5a. **Only ongoing releases are linkable** — the create form's release selector uses `Release::ongoing()`, and `MeetingNoteRequest` enforces it (a completed release id is rejected). Exception: a note already linked to a release that has since completed keeps its link — the edit form includes that one completed release and validation accepts it unchanged — so completing a release never invalidates its meeting history. (The index filter still lists all releases, since completed releases' notes remain browsable.)

6. **Authorization via `MeetingNotePolicy`** — viewAny/view: any authenticated user; create: any authenticated user; update: author only; delete: author or admin. Diverges deliberately from `NotePolicy` (author-only even for admins) because meeting notes are shared team records that may need admin cleanup after someone leaves.

7. **Sanitization pipeline reuse** — `body` Attribute setter runs `HtmlSanitizer::clean()` (same as `Note`), `MeetingNoteRequest` reuses the visually-empty-Trix-markup check, view renders via a `bodyHtml()` accessor with the `prose-notes` styling.

8. **Release details integration** — `ReleaseController@show` eager-loads `meetingNotes.author`; a new `partials`-style card in the main column (below Comments) lists the release's notes (title, date, author, excerpt) linking to their show pages, plus a "New meeting note" button carrying `?release={id}`. `Release::booted()` cleanup is unnecessary for deletes (FK `nullOnDelete` handles it — release deletion turns its notes into general notes rather than destroying content). Record activity on the release when a linked note is created, matching the `RecordsActivity` usage elsewhere.

9. **Navigation** — add "Meetings" entry to the `$nav` array in `layouts/navigation.blade.php` (label kept short to fit the navbar), route `meeting-notes.index`, active pattern `meeting-notes.*`.

## Risks / Trade-offs

- [Release deletion silently converts its notes to general notes] → Acceptable: preserves meeting history over cascading deletion; the note's title/date still identify it. Documented in spec scenario.
- [Two "notes" concepts (daily notes vs meeting notes) may confuse users] → Distinct nav labels ("Notes" vs "Meetings"), distinct page headers, no shared UI.
- [Unbounded note list on release details page] → Card shows the most recent notes (capped) with a "view all" link to the filtered index.
- [Admin-delete diverges from NotePolicy precedent] → Intentional; noted in policy docblock so the asymmetry reads as deliberate.
