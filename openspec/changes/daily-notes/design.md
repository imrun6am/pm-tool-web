## Context

A small, self-contained addition mirroring the app's existing patterns (day-based views like the calendar, author/admin policies like comments/events, per-authenticated-user collaboration). The one sensitivity is privacy: private notes must never leak to other users or into the audit log.

## Goals / Non-Goals

**Goals:** per-day notes; private/shared visibility; day view showing own + shared notes; author-only edit/delete.

**Non-Goals:** no rich text/attachments, no per-team or per-user sharing scopes (just private vs everyone), no comments on notes, no reminders. Notes are intentionally excluded from the activity log.

## Decisions

**Data model.** `notes`: `user_id` (author, cascade on delete), `date` (date), `body` (text), `visibility` (`private` | `shared`), timestamps. Indexed on `(date, visibility)` and `(user_id, date)` for the day query.

**Visibility as a query, enforced server-side.** The day view loads `Note::whereDate('date', $day)->where(fn($q) => $q->where('visibility','shared')->orWhere('user_id', $me))`. Private notes are filtered out for everyone but the author at the query level — never rendered and never sent to the client. A `visibleTo(User)` scope centralizes the predicate.

**No activity logging.** `Note` deliberately does not use `RecordsActivity`; logging private-note creation/edits would leak their existence and content into the global feed. Notes are personal/ephemeral and self-managed.

**Author-only management.** A `NotePolicy` (auto-discovered) grants `update`/`delete` to the author only — not even admins, since a private note is personal. (Admins already have no special claim on someone's private jottings.)

**Day-based UI.** A Notes page with the calendar's date-navigation pattern (prev/next/today + a date picker). An add-note card (textarea + private/shared toggle) posts for the viewed date. Each note card shows author, a visibility badge, timestamp, body (plain text with line breaks), and edit/delete for the author (inline edit via Alpine, matching the comments pattern). Deletes use the app's confirmation modal (`data-confirm`).

## Risks / Trade-offs

- [Private leakage] → Enforced at the query and policy layers, not just the view; private notes are never fetched for non-authors.
- [Admin cannot moderate shared notes] → Acceptable for this scale; can be added later if needed. Authors self-manage.
- [Timezone/day boundaries] → Notes store a plain `date`; the day view compares by date only, consistent with the rest of the app.

## Migration Plan

1. Migration + `Note` model (+ `visibleTo` scope) + `NotePolicy`.
2. `NoteRequest` (body required, visibility in set) + `NoteController` (index by day, store, update, destroy) + routes under `auth`.
3. Notes day view + nav link.
4. Seed a shared and a private demo note; migrate, build, test, smoke.
Rollback: drop the table; remove routes/controller/views/link.

## Open Questions

- None blocking. Team-scoped sharing (vs everyone) is a natural follow-up.
