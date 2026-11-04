## Why

"Will this release make its date?" is usually answered with a guess. The team's own history can answer it with a range and a likelihood instead, without anyone estimating more.

## What Changes

- The release page's Progress card shows a likely finish date, a fairly safe date, and the chance of meeting the planned end.
- Dates come from replaying the team's recent weekly throughput many times against the open tasks.
- When there's too little history, or nothing left, it says so instead of showing a number.
- Claude's risk brief and status drafts receive the same forecast.

## Capabilities

### New Capabilities
- `release-forecast`: likely finish dates from team throughput.

### Modified Capabilities
<!-- None: `openspec/specs/` has no base specs yet. -->

## Impact

- **Backend**: `ReleaseProgressService::forecast`; `ReleaseRiskGenerator::signals` gains a `forecast` block.
- **Frontend**: part of `releases/_progress`.
- **API**: part of `GET /releases/{id}/progress`.
- **Data**: none.
