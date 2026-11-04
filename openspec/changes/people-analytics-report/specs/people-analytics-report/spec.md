## ADDED Requirements

### Requirement: Only admins can reach the report
The page, the person page, the exports and the API SHALL be available to admins only.

#### Scenario: Another role asks for it
- **WHEN** a CTO, tech lead, team lead, developer, QA or viewer requests any of them
- **THEN** the response is 403

#### Scenario: Menu
- **WHEN** someone other than an admin views the sidebar or the Reports tabs
- **THEN** the report isn't listed

### Requirement: Report per team and per person
The report SHALL show, for a date range and an optional team, a summary, one row per team and one row per person. The range SHALL default to the last 30 days and all teams.

#### Scenario: Default view
- **WHEN** an admin opens the report with no filters
- **THEN** it covers the last 30 days across all teams

#### Scenario: Work counts once
- **WHEN** a task has a team, or has no team and its assignee is on exactly one team
- **THEN** it counts for that one team
- **AND** team rows plus "No team" add up to the all-teams total

#### Scenario: Team filter
- **WHEN** a team is selected
- **THEN** each person's work figures cover only that team's work
- **AND** people who worked on the team without being members are tagged

### Requirement: Figures the app holds
Each row SHALL cover tasks finished, on-time rate, cycle and lead time, rework, open, overdue and blocked work, time logged and available, estimates against time logged, tasksheet days, plan with result, leave, points, tickets, standups, requests, meetings, comments, AI uses, ratings and KPI results. Archived tasks SHALL NOT count, and rates SHALL be computed from summed parts.

#### Scenario: Reopened work
- **WHEN** a task was finished in the period and then reopened
- **THEN** it doesn't count as finished

#### Scenario: Team rate
- **WHEN** a team row shows an on-time rate
- **THEN** it's on-time tasks over dated tasks across the team, not an average of its members' rates

#### Scenario: Working week
- **WHEN** tasksheet days or available hours are counted
- **THEN** only working days per `WorkingWeek` count, and full-day leave doesn't

#### Scenario: Private and lead-only data
- **WHEN** figures are computed
- **THEN** private events aren't counted, and tasksheet feedback and rating notes aren't read

### Requirement: Too little data
A rate SHALL NOT be shown as a figure when its sample is below the minimum.

#### Scenario: Two tasks
- **WHEN** a person finished two dated tasks
- **THEN** the page shows "Too few" with the sample size instead of an on-time rate
- **AND** the CSV leaves the rate blank and keeps the counts

### Requirement: Person page
Each person SHALL have a page with their figures for the period, a comparison with the period before, the split by team, and the tasks behind the numbers.

#### Scenario: Open a person
- **WHEN** an admin selects a person in the report
- **THEN** their page opens for the same period and team

### Requirement: Exports
The report SHALL export as CSV (per person or per team) and PDF. A person's tasks SHALL export as CSV and their page as PDF. Text a spreadsheet would run as a formula SHALL be neutralised.

#### Scenario: Same figures
- **WHEN** an admin exports the report
- **THEN** the file has the same period, team and figures as the page

### Requirement: API
`GET /reports/people`, `GET /reports/people/export` and `GET /reports/people/{user}` SHALL return what the pages show, for admins only.

#### Scenario: Parity
- **WHEN** an admin requests the report on the web and on the API with the same filters
- **THEN** both come from the same computation and agree
