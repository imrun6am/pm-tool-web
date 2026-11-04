## Why

The weekend here is Friday, but most of the app assumes Saturday and Sunday. Capacity and recurring tasks already follow a setting, while tasksheet expectations, streaks, compliance, standup attendance, "Mark weekends", Claude's schedule assistant and the standup digest don't, so the same day can be a working day on one page and the weekend on another.

## What Changes

- One `WorkingWeek` helper answers "is this a working day?", read from `PLANNING_WEEKEND_DAYS` (default Friday) and `PLANNING_ALTERNATE_OFF_DAY` (default 2026-08-01: every second Saturday off).
- Tasksheet expectations, streaks, compliance and standup attendance follow it.
- "Mark weekends" follows it, on the release page, the form and the API.
- Claude's schedule assistant is told the weekend and counts working days from it.
- The standup digest runs on working days, for the previous working day.
- Existing off-days and past records aren't changed. Figures recompute from the new rule, so some past performance and delivery numbers change.

## Capabilities

### New Capabilities
- `working-week`: one definition of the weekend across the app.

### Modified Capabilities
<!-- None: `openspec/specs/` has no base specs yet. -->

## Impact

- **Backend**: new `App\Support\WorkingWeek`; `MemberAnalyticsService`, `PerformanceAnalytics`, `DeliveryAnalyticsService`, `TeamService`, `ReleaseOffDayService`, `CapacityService`, `RecurringTaskService`, `ScheduleAssistant`, `StandupDigestGenerator`, `GenerateStandupDigests`, `AiInsightController`, the digest schedule.
- **Frontend**: the release form's "Mark weekends", the team health note, the digest panel.
- **API**: same shapes, new values.
- **Tests**: `phpunit.xml` pins Saturday and Sunday so date fixtures stay stable; `WorkingWeekTest` covers Friday.
