## Why

Teams coordinate around meetings, reviews, and deadlines, but the tool has no place to see them. A shared calendar where anyone can add events — meetings and the like — and see them on a month grid closes that gap and complements the release timeline.

## What Changes

- Add a **calendar** with a **month view** (previous/next navigation, today marker) showing events on their days.
- Add **events** that any authenticated user can create: title, type (Meeting, Review, Release, Deadline, Other), start and end date/time, an all-day option, optional location, optional description, an optional link to a release, and a list of **attendees** (users).
- Multi-day and all-day events appear on **every day they span** within the view.
- **Edit/delete** an event is limited to its creator or an Admin (others can view).
- Event changes are recorded in the **activity log**, attributed to the acting user.
- Add a **Calendar** link to the main navigation.

## Capabilities

### New Capabilities
- `calendar-events`: A shared month calendar of events (meetings, reviews, deadlines, …) with attendees and optional release links; any authenticated user creates events, creator/Admin edits or deletes.

### Modified Capabilities
<!-- None as a formal delta. New, self-contained capability. -->

## Impact

- **Database**: new `events` table and an `event_user` attendee pivot.
- **Models**: `Event` (creator, release, attendees, `RecordsActivity`); a `calendarEvents`/`attendedEvents` relation on `User`.
- **Auth**: event routes under `auth` (any user creates); an `EventPolicy` limits edit/delete to creator or Admin.
- **UI**: a month calendar view, event create/edit/show screens, and a "Calendar" nav link.
- **Seed data**: a few demo events (a couple linked to releases, with attendees).
