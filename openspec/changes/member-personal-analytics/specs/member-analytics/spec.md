## ADDED Requirements

### Requirement: Personal analytics on the member dashboard
The member dashboard SHALL show developer and QA users analytics computed only from their own data: tasks assigned to them, status changes of those tasks, their own tasksheet rows, comments they wrote and meetings they attended. It SHALL NOT show other users' metrics, rankings, lead feedback or performance scores.

#### Scenario: Only own data
- **WHEN** a developer views their dashboard while a colleague has completed tasks and filled tasksheet rows
- **THEN** none of the colleague's work is reflected in the developer's counts, trends or streak

#### Scenario: Feedback never exposed
- **WHEN** a lead has written feedback on the member's tasksheet row
- **THEN** neither the dashboard nor the API's analytics contain it

### Requirement: Today strip and focus list
The dashboard SHALL show counts of open, overdue (open and due before today), due soon (open and due within the next 7 days, including today) and in-review tasks. It SHALL also show a focus list ordered overdue first, then due today, then in progress, capped at six items.

#### Scenario: Focus ordering
- **WHEN** the member has an in-progress task, a task due today and an overdue task
- **THEN** the focus list shows the overdue task first, then the one due today, then the in-progress one

### Requirement: Period KPIs with like-for-like comparison
For the selected period (`week`, `month` or `quarter`, default `month`), the dashboard SHALL show:
- tasks completed;
- the on-time completion rate;
- average cycle time (In Progress → Done);
- tickets logged on the tasksheet.

Each SHALL be compared with the same elapsed span of the previous period.

#### Scenario: Completed count and change
- **WHEN** the member completed 3 tasks so far this month and 1 by the same day last month
- **THEN** the completed KPI shows 3 with a +2 change

#### Scenario: Cycle time from status history
- **WHEN** a task moved to In Progress and was moved to Done 2 days later
- **THEN** it contributes 2 days to the average cycle time

#### Scenario: Empty denominator
- **WHEN** no task completed in the period had a due date
- **THEN** the on-time rate is reported as no data (`null`), not 0%

### Requirement: Trends and habits
The dashboard SHALL show:
- weekly completions for the last 12 weeks, marking the member's best week;
- a status breakdown of their assigned tasks;
- a 26-week daily contribution heatmap (completed tasks, comments and fully filled tasksheet days);
- the current and best weekday streak;
- standup attendance rate and tasksheet fill rate for the period;
- progress toward their usual weekly pace.

#### Scenario: Streak skips weekends and leave
- **WHEN** the member was active on Friday and Monday, and was on full-day leave on Tuesday, and today is Wednesday with no activity yet
- **THEN** the current streak is 2

### Requirement: Encouraging empty states
A member with no tasks, tasksheet rows or comments SHALL see welcoming empty states instead of zeros framed as failures. Declines SHALL never be styled as errors.

#### Scenario: Brand-new member
- **WHEN** a newly created developer opens the dashboard
- **THEN** the page renders with friendly empty states and no percentage is computed from an empty denominator

### Requirement: API parity
`GET /api/v1/dashboard` for limited roles SHALL include an `analytics` object with the same values the web dashboard renders. It SHALL accept a `period` parameter, validated to `week`, `month` or `quarter`.

#### Scenario: Same numbers on both surfaces
- **WHEN** the same member requests the web dashboard and the API with the same period
- **THEN** the completed count, rates, streak and weekly series are identical

#### Scenario: Invalid period
- **WHEN** the API is called with `period=year`
- **THEN** it responds 422
