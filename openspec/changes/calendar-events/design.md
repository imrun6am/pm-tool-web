## Context

The app has releases, tasks, comments, an activity log, and an expanded user set, all server-rendered Blade with a per-authenticated-user collaboration model. This adds a standalone shared calendar of events (meetings, reviews, deadlines) with attendees, mirroring the collaboration and audit patterns already in place.

## Goals / Non-Goals

**Goals:**
- Events with title, type, start/end, all-day, location, description, optional release link, and attendees.
- A month grid that renders events on every day they span, with prev/next navigation and a today marker.
- Any authenticated user creates events; creator/Admin edit or delete; everyone can view.
- Event changes recorded in the activity log.

**Non-Goals:**
- No week/day/agenda views, recurring events, reminders/notifications, invitations/RSVP, drag-to-reschedule, or external calendar (iCal/Google) sync.
- No availability/conflict detection against release windows or other events.

## Decisions

**Data model.**
`events`: title, description, `type` (slug from a fixed set with colors), `starts_at` / `ends_at` (datetime; end nullable and ≥ start), `all_day` (bool), `location`, `release_id` (nullable, nullOnDelete), `created_by` (nullable, nullOnDelete), timestamps. Attendees are a `belongsToMany` via an `event_user` pivot (unique event+user). Types: Meeting, Review, Release, Deadline, Other — each mapped to a display color, matching how tasks/phases use fixed color maps.

**Rendering the month.**
`CalendarController@index` takes `year`/`month` (default current), computes a 6-week grid from the first Sunday on/before the 1st through the last Saturday on/after the month end, and loads events intersecting that visible range (`starts_at <= rangeEnd AND coalesce(ends_at, starts_at) >= rangeStart`). Events are expanded into a `date => [events]` map by walking each event's covered dates (clipped to the range), so multi-day and all-day events appear on every relevant cell. All pure server-side date math; no JS calendar library.

**Permissions.**
Event routes live under `auth` — any authenticated user creates and views (collaboration, consistent with tasks/comments). An `EventPolicy` (auto-discovered) limits `update`/`delete` to the creator or an Admin, exactly like `CommentPolicy`. Attendee lists are just data; being an attendee does not grant edit rights.

**Audit + release link.**
`Event` uses `RecordsActivity`; `activityReleaseId()` returns the linked `release_id` (or null), so an event tied to a release also surfaces in that release's history and the global feed, attributed to the acting user. Seeding mutes events as before.

**Forms.**
Create/edit share a Blade partial. All-day toggles the time inputs (Alpine); when all-day, times are normalized to start-of-day / end-of-day on save. Attendees use a multi-select of users. Datetime uses native `datetime-local` inputs.

## Risks / Trade-offs

- [Multi-day expansion could be heavy for very long events] → Bounded by the visible 6-week window; expansion is clipped to the range.
- [Timezone handling] → App uses its default timezone throughout; events store naive local datetimes like the rest of the app. Acceptable for a single-org tool.
- [datetime-local browser support / all-day semantics] → All-day normalizes to day bounds server-side so the stored range is unambiguous regardless of input.
- [Deleting a user or release] → `created_by`/`release_id` are nullOnDelete; events survive as unattributed/unlinked. Attendee pivot rows are removed with the user via FK.

## Migration Plan

1. Migrations: `events` and `event_user`.
2. `Event` model (+ relations, types, RecordsActivity), `User` relations, `EventPolicy`.
3. `EventRequest` + `CalendarController` (month) + `EventController` (CRUD, attendee sync) + routes under `auth`.
4. Month view + event create/edit/show + a "Calendar" nav link.
5. Seed demo events; migrate, build, run suite, smoke-test.
Rollback: drop the two tables and remove routes/controllers/views/link.

## Open Questions

- None blocking. Week/agenda views and reminders are natural follow-ups.
