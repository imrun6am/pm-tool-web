## Why

Leads and viewers land on a planning dashboard that shows the release timeline and three charts about release counts. It says nothing about how work is *flowing*: how much gets finished, how long it takes, what is stuck or overdue, which releases are slipping, who is overloaded, or whether tasksheets and standups are happening. Each role makes different decisions, so each needs a different slice.

## What Changes

- **New `DeliveryAnalyticsService`.** It reads role-tailored delivery health from tasks, their status history (`activities`), release phases and tasksheet rows, using aggregate queries.
- **Audiences:**
  - **org** (Admin, CTO, Tech Lead) sees every team.
  - **team** (Team Lead) sees the teams they lead, unless they filter to another team.
  - **viewer** sees portfolio health only: no per-person load, compliance or named task lists.
- **A "Delivery health" section** sits below the existing timeline and insights:
  - flow KPIs (throughput, cycle time, on-time rate, rework rate) for the last 28 days against the 28 before;
  - a 12-week throughput chart;
  - open work and its age;
  - release progress against time used, with at-risk flags;
  - open tasks by team;
  - for non-viewers: workload by person, tasksheet/standup compliance (with today's standup per team), and overdue and waiting-in-review lists.
- **"Your work" strip** for any lead with tasks assigned.
- **API:** `GET /api/v1/dashboard` (planning) gains a `delivery` object. Hidden sections are `null`.
- **Archived tasks are never counted.**

## Capabilities

### New Capabilities
- `delivery-analytics`: delivery health on the planning dashboard, per audience.

## Impact

- **Backend:**
  - new `app/Services/DeliveryAnalyticsService.php` and `app/Support/Metric.php` (shared with member analytics);
  - `DashboardController` (web and API) passes `delivery`.
- **Views:** new `partials/delivery-health.blade.php`, one include and a "Your work" strip in `dashboard.blade.php`. The timeline, filters and conflicts are unchanged.
- **Docs and tests:** `docs/api-v1.md`, `tests/Feature/Services/DeliveryAnalyticsServiceTest.php`.
