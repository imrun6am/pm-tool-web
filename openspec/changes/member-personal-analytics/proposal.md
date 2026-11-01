## Why

Developers and QA land on "My day", which today shows three counts and three lists. It tells them what to do, but not how they're doing: nothing about what they finished, how fast work moves, whether their habits (tasksheet, standup) are holding, or how this week compares with their own past. The data is already recorded. Task status changes are in the `activities` audit trail, and the daily tasksheet holds tickets, points and standup attendance. A personal analytics view built on that data gives members a reason to open the page beyond triage, and helps them keep a steady pace.

## What Changes

- A new `MemberAnalyticsService` derives a member's personal analytics from their own assigned tasks, the status-change history in `activities`, their tasksheet rows, their comments and the meetings they attended. It returns domain values only and runs a fixed, small number of aggregate queries per request, with no N+1.
- **"My day" becomes a personal analytics page.** It keeps every existing section and adds:
  - a **momentum** banner (an encouraging headline, the current streak and progress toward the member's usual weekly pace);
  - a **Today** strip: open, overdue, due soon, in review;
  - **Focus today**: overdue, then due today, then in progress;
  - **period KPIs** (completed, on-time rate, average cycle time, tickets logged), each compared with the same point in the previous period;
  - a **12-week completion trend** that marks the member's best week;
  - a **status donut** of their assigned tasks;
  - a **26-week contribution heatmap**;
  - **habit meters**: standup attendance, tasksheet fill rate and a lead-time trend;
  - **recently completed** work.
- **Period switcher** (`?period=week|month|quarter`, default `month`) on both web and API.
- **API:** the member branch of `GET /api/v1/dashboard` gains an `analytics` object and accepts `period`. The existing member keys are unchanged.
- **Tone:** a decline never shows in red, a new user gets encouraging empty states, and every rate guards an empty denominator (`null` means "no data yet", never 0%).
- **Privacy:** a member only ever sees their own data. There is no leaderboard and no colleague comparison, and lead feedback and performance scores stay out.

## Capabilities

### New Capabilities
- `member-analytics`: the personal analytics a developer/QA member sees on their dashboard. It covers metric definitions, period comparison, empty states, data isolation and the API shape.

### Modified Capabilities
<!-- `member-dashboard` (dev-qa-restricted-access) is not archived yet; its requirements still hold. The open-task list, tasksheet status and meetings sections are kept verbatim. -->

## Impact

- **Backend:**
  - new `app/Services/MemberAnalyticsService.php` and `app/Services/MemberPeriod.php`;
  - the member branch of `DashboardController` (web) and `Api\V1\DashboardController` passes the analytics;
  - no migrations: everything is derived from existing tables.
- **Views:**
  - `resources/views/dashboard-member.blade.php`;
  - new partials `partials/column-chart`, `partials/contribution-heatmap`, `partials/delta` and `partials/donut`;
  - `partials/spark-line` gains optional `color`, `guide` and `label` parameters (defaults unchanged).
- **Docs:** `docs/api-v1.md` (member dashboard payload).
- **Tests:**
  - a service feature test;
  - member dashboard render tests for developer and QA;
  - API parity.
