## Why

Leads asked for "more detailed analysis" on two pages:

- **Activity** is a feed with a 14-day bar and a top-five list. It can't answer how work flowed, where it got stuck, who carries the load, or what changed against last month.
- **Performance** shows ratings for one week. It has no view of which competencies are moving, how ratings are spread, how a member compares with their own past, or the objective record (tasksheet, finished work, rework, overdue) beside the ratings.

## What Changes

- **Activity analysis** (`ActivityInsights::analysis`, the web page and `GET /api/v1/activities/analysis`):
  - a 7/30/90-day window compared with the previous one;
  - volume per day or week;
  - breakdowns by event, record type, team (through the release) and release;
  - the most active people with their event mix;
  - a weekday × hour rhythm heatmap;
  - task workflow: milestones, the most common moves, rework rate, and time spent in each status;
  - recent deletions.
- **Filters:** scope filters (person, team, project, release) drive the charts. Each chart drills into the feed through slice filters (event, record type, dates). The feed stays on the page.
- **Performance analysis.** `PerformanceAnalytics` and the existing views gain:
  - team average against last week;
  - competency trends per week;
  - the week's 1–5 rating spread;
  - each member against the team average;
  - an objective record per member over 4 weeks: tasksheet, standup, completed, on-time, cycle time, rework, overdue.
- **Member page** additions:
  - change against last week;
  - strengths and growth areas from the member's own 8-week averages;
  - a per-competency week grid;
  - the objective record against the 4 weeks before;
  - lead notes.
- **Shared code:** `TaskCompletions` (completion, start and rework from the audit trail) is extracted and shared by delivery, activity and performance analytics.
- **Performance fix:** the per-request competency catalog removes an N+1 on the team overview (109 → 10 queries).
- **Access rules are unchanged.** Activity stays full-access. Performance stays lead-only, and a team lead is scoped to their teams. Archived tasks never count.

## Capabilities

### New Capabilities
- `activity-analysis`
- `performance-analysis`

## Impact

- **Services:**
  - `ActivityInsights` and `PerformanceAnalytics` are extended;
  - `TaskCompletions` is new;
  - `DeliveryAnalyticsService` now uses `TaskCompletions`.
- **Controllers:** the web and API Activity controllers; API route `activities/analysis`.
- **Views:**
  - `activity/index.blade.php` is rewritten;
  - `performance/_team_depth.blade.php` and `performance/_member_depth.blade.php` are new;
  - small edits to `performance/index` and `performance/show`;
  - the column-chart and donut partials accept an optional `href` per bar or segment.
- **Docs and tests:**
  - `docs/api-v1.md`;
  - `tests/Feature/ActivityAnalysisTest.php` and `tests/Feature/PerformanceDepthTest.php`.
