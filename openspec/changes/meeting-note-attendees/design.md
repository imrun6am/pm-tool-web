## Context

`MeetingNote` (from the `meeting-notes` change) has title, meeting_date, rich body, nullable `release_id`/`event_id`, `created_by`, and is visible to everyone. It is listed in three places: the Meeting Notes index (filterable by release), the release-details "Meeting notes" card, and the event-details "Meeting notes" card. The `Event` model already models attendees via a `event_user` pivot and an `attendees()` belongsToMany — the exact pattern to copy. Roles now share a leadership tier via `User::isLead()`.

## Goals / Non-Goals

**Goals:**
- Record meeting-note attendees (a set of users).
- A per-note visibility scope: Everyone (default) or Attendees only.
- Enforce Attendees-only visibility server-side everywhere a note can surface.
- Reuse existing conventions (Event's attendee pivot, Trix form, policy authorization).

**Non-Goals:**
- No attendance status beyond "attended" (no tentative/declined — that's the calendar Event's job).
- No per-attendee permissions (edit stays author-only; delete stays author-or-admin).
- No notification of attendees.
- No change to release/event linkage or the rich-text pipeline.

## Decisions

1. **Attendees via a `meeting_note_user` pivot**, `attendees()` belongsToMany with `withTimestamps()` — mirrors `Event::attendees()`. Users are soft-deleted, so the relation uses `withTrashed()` so a departed attendee still shows (tagged) rather than vanishing.

2. **`visibility` string column (`everyone` | `attendees`, default `everyone`)** with a `VISIBILITIES` const map, mirroring `Note`/`QuickLink`. Default preserves current behaviour; existing rows are Everyone.

3. **`scopeVisibleTo(User)`** — the single source of truth for who sees a note:
   - `visibility = everyone` → always; OR
   - `created_by = user` (author always sees their own); OR
   - `user` is an attendee (`whereHas('attendees')`); OR
   - `user->isLead()` (leadership tier oversees — consistent with leads deleting any note).
   Applied to the index query, the release-card query (`release->meetingNotes` filtered), and the event-card query. A `MeetingNotePolicy@view` reuses the same predicate for the show route (`view($user, $note)`), returning 403 to anyone outside the set.

4. **Author is not auto-added as an attendee** — authorship and attendance are distinct (someone may write up a meeting they ran but list only the participants). The author still always *sees* the note via decision 3. The form defaults the attendee selection to empty (or the event's attendees when created from one).

5. **Prefill attendees from the event** — creating a note via a meeting-type event's "Write meeting note" action preselects the event's attendees (the controller seeds them alongside title/date/release). Editable before saving.

6. **Form control** — an attendee multi-select (all active users, like the Event form's picker) plus a visibility select (Everyone / Attendees only). The show page renders the attendee chips and, when Attendees-only, a badge; index cards show an attendee count.

7. **Validation** (`MeetingNoteRequest`) — `attendees` optional array of existing user ids; `visibility` in the allowed set. No rule forcing attendees when Attendees-only (an author-only private note is legitimate); the author + leads still see it.

## Risks / Trade-offs

- [An Attendees-only note with no attendees is visible only to author + leads] → Acceptable and occasionally desired (a private write-up); not an error.
- [Leads bypass Attendees-only] → Chosen deliberately (per product decision) for oversight; documented on the visibility control so authors know leads can always read it.
- [Extra `whereHas` on every listing] → One indexed subquery on small per-page sets; negligible. The pivot is indexed by the FK pair.
- [Soft-deleted attendee still counts for visibility] → Fine: they attended; their access is moot once the account can't log in.
