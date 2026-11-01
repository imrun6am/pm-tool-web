## Context

Developers and QA used to see every release page, tasksheet, event and meeting note, plus everyone's shared notes. `release-optional-tasks` §8 already scoped tasks with a `visibleTo` scope and a policy. The other areas followed different patterns:
- `Note` and `MeetingNote` already had `visibleTo` scopes;
- events had no visibility check at all;
- release pages were opened deliberately to all roles;
- tasksheet teams came from a service.

## Decisions

### 1. One people rule
"People a developer may see" means:
- themselves;
- their current teammates (`team_user.left_at` null);
- the assigned leads of their teams (`teams.team_lead_id`).

Including the team lead lets a developer invite or assign their own lead without seeing the rest of the organisation. Pickers use `User::visibleTo($viewer)`, and requests use the `VisiblePerson` rule. People already on a record pass, so editing never drops an attendee or recipient.

### 2. Releases are guarded at the route
Release pages have many sub-routes on two surfaces. A single `release-visible` middleware on the open release route groups (web and API) is harder to forget than per-controller checks. It calls `ReleasePolicy::view`, which delegates to `Release::isVisibleTo`.

`Release::scopeVisibleTo($user, $keep)` takes a `$keep` id, so a record's current release stays in its edit picker. A plain `->orWhereKey()` after the scope would collapse the list for leads.

### 3. Content rule for events and meeting notes (`LimitsToViewerTeams`)
A developer may see an event or meeting note if:
- they created or attend it;
- it is linked to one of their teams' releases;
- it is unlinked, has **no attendees**, and was created by a teammate or by a lead/viewer.

The last case keeps organisation-wide entries visible: holidays, deadlines, all-hands notes. An unlinked record *with* attendees is a private meeting, so it stays with its attendees, even when a teammate or their own lead created it.

### 4. Notes
Shared daily notes are personal logs, so for limited roles they come only from teammates. There is no organisation-wide exception.

### 5. Tasksheet
Narrowing `TasksheetService::teamsFor` scopes the web sheet, the API sheet and the exports page picker together. `resolveTeam` already falls back to the first allowed team, so another team's id simply shows the developer's own sheet. A developer on no team sees the existing empty state. Exports were already limited to their own rows by `TasksheetExportPolicy`.

## Risks / Trade-offs

- **Stale links:** links to another team's release, from a member dashboard task that was lent to them for example, now return 403. That is the intended behaviour.
- **Narrower dev-created events:** an unlinked event a developer creates with attendees is now visible only to those attendees. This matches the new `event-visibility` change's notion of private meetings, which builds on the same scope.
- **Emails:** the API's `UserSummaryResource` still includes `email`, but only for people the viewer can now see.
