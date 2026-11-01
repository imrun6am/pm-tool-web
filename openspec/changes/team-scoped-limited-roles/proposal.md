## Why

The user wants Developers and QA kept to their own team. Their words: they "should not see other team members tasks or any other things".

Tasks and the board were scoped in `release-optional-tasks` (design §8). An audit then found other teams' data still reachable by these roles:
- release pages, documents and comments;
- every team's tasksheet;
- every calendar event;
- "everyone" meeting notes;
- "shared" daily notes;
- release and people pickers on almost every form.

The user chose to scope all of them.

## What Changes

These rules apply to Developers and QA only (`User::hasLimitedAccess()`). Leads and viewers are unchanged.

- **People they can see:** themselves, their current teammates, and their teams' assigned leads (`User::scopeTeammatesOf` / `scopeVisibleTo`).
  - Pickers and filters list only these people: event, meeting-note and note recipients, and the meeting-note author/attendee filters.
  - Requests reject anyone else with the `VisiblePerson` rule. People already on a record may be kept.
- **Releases:** only their own teams' releases (`Release::scopeVisibleTo`, `ReleasePolicy::view`).
  - A `release-visible` route middleware returns 403 on every `releases/{release}/…` route open to all users, on the web and API: detail, conflicts, phases, off-days, documents (list, download and upload), tasks and comments.
  - Release pickers list only their teams' releases, plus a record's current release: calendar, event page, event import review, meeting notes, the quick-links drawer, the board and the task page.
  - The `VisibleRelease` rule rejects other releases in event, event-import, meeting-note, quick-link and task requests.
- **Tasksheet:** `TasksheetService::teamsFor` returns only their own teams. A request for another team falls back to theirs. This covers the web and API sheet and the exports page picker.
- **Events:** `Event::scopeVisibleTo` (through `LimitsToViewerTeams`) lets them see:
  - events they created or attend;
  - events linked to one of their teams' releases;
  - unlinked events with no attendees, created by a teammate or by a lead/viewer (organisation-wide entries).

  An unlinked event with attendees is for its attendees only. The scope applies to the calendar, `EventService::inWindow` (web and API) and `EventPolicy::view` (event page and API show). A meeting note can only be started from, or linked to, a visible event.
- **Meeting notes:** everyone-visibility notes follow the same rule. Attendees-only notes are unchanged.
- **Notes:** "shared" daily notes appear only from teammates.

## Capabilities

### New Capabilities
- `team-scoped-access`: what Developers and QA may see of releases, tasksheets, events, meeting notes, notes and people outside their own teams.

## Impact

- **Models:**
  - `User`: `LIMITED_ROLES`, `teammatesOf` (now includes team leads), `visibleTo`;
  - `Release`, `Event`, `MeetingNote`, `Note`: visibility scopes;
  - new concern `LimitsToViewerTeams`.
- **Authorization:**
  - new `ReleasePolicy` and `EnsureReleaseIsVisible` (`release-visible` alias);
  - `EventPolicy::view`;
  - new rules `VisibleRelease` and `VisiblePerson`.
- **Controllers, services and requests:** calendar and events, event imports, meeting notes, notes, quick links (service and drawer composer), tasksheet service, `routes/web.php` and `routes/api/v1/releases.php`.
- **No migration.**
- **Docs:** `docs/api-v1.md`.
- **Tests:** a new `CrossTeamScopingTest`. Existing tests that used developers who share no team now put them on one.
- **Related:** the `event-visibility` change (another session) builds on `Event::scopeVisibleTo` to add private events.
