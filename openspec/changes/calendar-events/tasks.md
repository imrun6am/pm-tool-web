> Implemented and verified. 68 tests pass (6 new calendar tests). Live smoke test confirmed the
> month view renders events, a Developer created an event with attendees, and edit/delete is
> limited to creator/admin (others 403).

## 1. Database & model

- [x] 1.1 Migration: `events` (title, description, type, starts_at, ends_at, all_day, location, release_id nullOnDelete, created_by nullOnDelete)
- [x] 1.2 Migration: `event_user` attendee pivot (unique event+user)
- [x] 1.3 `Event` model: fillable/casts, `TYPES` + colors, relations (creator, release, attendees), `RecordsActivity` with `activityReleaseId()` = release_id, date-span helper
- [x] 1.4 `User`: `createdEvents` + `attendedEvents` relations

## 2. Auth & requests

- [x] 2.1 `EventPolicy` (update/delete = creator or admin); auto-discovered
- [x] 2.2 `EventRequest`: title/type/start required, end ≥ start, all_day bool, attendees exist; normalize all-day to day bounds

## 3. Controllers & routes

- [x] 3.1 `CalendarController@index`: month grid (default current), events intersecting the visible range expanded to a date→events map
- [x] 3.2 `EventController`: create, store (sync attendees), show, edit, update, destroy (policy-guarded)
- [x] 3.3 Routes under `auth` (events/create before events/{event}); `GET calendar`

## 4. Views & nav

- [x] 4.1 Month view: 6-week grid, prev/next + today, event chips per day, empty days
- [x] 4.2 Event form partial (type, all-day toggle, start/end datetime, location, description, release, attendees) + create/edit pages
- [x] 4.3 Event show: details, attendees, edit/delete for creator/admin
- [x] 4.4 "Calendar" nav link (desktop + mobile)

## 5. Seed, test, verify

- [x] 5.1 Seeder: a few demo events (some linked to releases, with attendees)
- [x] 5.2 Feature tests: create event; end-before-start rejected; month view renders event on its day(s); creator/admin can edit-delete, others 403; guest redirected
- [x] 5.3 Migrate, `npm run build`, run full suite, live smoke test, then check off tasks and `openspec validate`
