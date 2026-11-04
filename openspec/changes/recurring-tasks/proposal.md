## Why

Weekly reports, backup checks and monthly reviews get created by hand each time, or forgotten.

## What Changes

- Leads set up rules: daily on working days, weekly or monthly, with a team, assignee, priority, estimate and how long until it's due.
- A daily scheduled command creates a normal task from each rule that's due.
- Rules can be paused, resumed and removed. Tasks already made stay.
- Web page and API.

## Capabilities

### New Capabilities
- `recurring-tasks`: rules that create tasks on a schedule.

### Modified Capabilities
<!-- None: `openspec/specs/` has no base specs yet. -->

## Impact

- **Data**: new `recurring_tasks`.
- **Backend**: `RecurringTask`, `RecurringTaskService`, request, controllers, `tasks:recur` scheduled at 06:00.
- **Frontend**: new `recurring-tasks/index`, linked from All tasks.
- **Operations**: needs the scheduler running.
