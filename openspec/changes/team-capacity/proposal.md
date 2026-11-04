## Why

Leads assign work without seeing who has room. The dashboard counts open tasks per person, but ten small tasks and one huge one look the same, and leave isn't considered. With estimates and logged hours in place, capacity can now be real numbers.

## What Changes

- A new Capacity page for leads shows each team member, by week for six weeks: time available against the estimated work due, flagged when over.
- Available time allows for weekends, the team's release off-days, recorded leave and days already past.
- Load is what's left of each estimate. Overdue work counts this week. Undated work goes in a "later" column. Unestimated tasks are counted separately so nobody looks free by accident.
- A matching API endpoint.

## Capabilities

### New Capabilities
- `team-capacity`: available time per person per week against estimated work.

### Modified Capabilities
<!-- None: `openspec/specs/` has no base specs yet. -->

## Impact

- **Backend**: new `CapacityService`, web and API controllers, `config/planning.php`.
- **Frontend**: new `capacity/index`, a sidebar item for leads, one icon.
- **API**: new `GET /capacity`.
- **Data**: none.
