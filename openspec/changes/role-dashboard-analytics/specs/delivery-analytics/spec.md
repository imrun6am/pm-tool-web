## ADDED Requirements

### Requirement: Role-tailored delivery health
The planning dashboard SHALL show a delivery-health section scoped by audience:
- org leads (admin, CTO, tech lead) see all teams;
- team leads see the teams they lead unless they filter to another team;
- viewers see portfolio-level numbers only.

#### Scenario: Team lead scope
- **WHEN** a team lead who leads Alpha opens the dashboard without a team filter
- **THEN** delivery health counts only Alpha's tasks and releases

#### Scenario: Viewer privacy
- **WHEN** a viewer opens the dashboard
- **THEN** no per-person workload, compliance or named task lists are shown, and the API returns them as null

### Requirement: Flow KPIs from status history
The section SHALL show throughput, average cycle time (In Progress → Done), on-time rate and rework rate (the share of finished work that went through Recheck) for the last 28 days, each compared with the 28 days before. Rates SHALL be null when there is nothing to divide by.

#### Scenario: Rework rate
- **WHEN** two tasks were finished in the window and one had been moved to Recheck
- **THEN** the rework rate is 50%

### Requirement: Release progress against plan
For each ongoing release in view, the section SHALL show the done share against the share of time used, and SHALL flag it at risk when its team is double-booked, a phase has ended with open tasks, the done share trails time used by 25 or more points, or it is past its end date.

#### Scenario: Late phase
- **WHEN** a release's development phase has ended and a development task is still open
- **THEN** the release is flagged "Late: Development"

### Requirement: Archived tasks are never counted
Archived tasks SHALL be excluded from every delivery-health count, rate, trend and release progress figure.

#### Scenario: Archived work
- **WHEN** a task is archived
- **THEN** it counts toward neither throughput, open work nor release progress
