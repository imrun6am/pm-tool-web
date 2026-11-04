## Why

The Claude risk brief is a point-in-time opinion, and a lead's own judgment isn't recorded anywhere. People ask "how's this release going?" in chat, and the answer isn't kept.

## What Changes

- Leads post a status update on a release: on track, at risk or off track, with a note. Earlier updates stay as history.
- Everyone who can see the release can read them.
- Moving to at risk or off track tells the team's lead and the org-level leads in their inbox.
- "Draft with Claude" fills in the form from the release's progress, forecast and last update. Nothing is saved until a lead posts it.
- A matching API.

## Capabilities

### New Capabilities
- `release-status-updates`: written status history on a release.

### Modified Capabilities
<!-- None: `openspec/specs/` has no base specs yet. -->

## Impact

- **Backend**: new `ReleaseStatusUpdate`, `ReleaseStatusService`, `ReleaseStatusRequest`, `ReleaseStatusChanged`, `ReleaseStatusWriter`, web, API and AI controllers.
- **Frontend**: new `releases/_status`, a new notification kind.
- **API**: new `GET` and `POST /releases/{id}/status-updates`; notifications gain `release_id` when they have one.
- **Data**: new `release_status_updates` table.
- **Claude**: new feature `release_status`, Sonnet by default, `low` effort.
