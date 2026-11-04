## ADDED Requirements

### Requirement: Work in each status
The system SHALL show how many top-level tasks sat in each status at the end of each of the last 12 weeks, for all teams or one. Archived work and work finished before the window SHALL be left out.

#### Scenario: A task moves through
- **WHEN** a task moves from In Progress to In Review to Done over three weeks
- **THEN** it counts in each of those statuses in the matching week

### Requirement: Added against finished
The system SHALL show tasks added and tasks finished per week.

#### Scenario: More coming in
- **WHEN** two tasks are added in a week and none finished
- **THEN** that week shows 2 added and 0 finished

### Requirement: Time to finish
The system SHALL show the days from starting to finishing each task, with the 50th and 85th percentiles. A task with no start SHALL NOT have a duration.

#### Scenario: Never started
- **WHEN** a task went straight to Done
- **THEN** it counts as finished and isn't on the chart

### Requirement: Work taking longer than usual
The system SHALL list work in progress for longer than the 85th percentile, oldest first.

#### Scenario: Not started yet
- **WHEN** a task is old but still in To Do
- **THEN** it isn't listed

### Requirement: Access
Leads and viewers SHALL be able to see the report and developers and QA SHALL NOT. The report SHALL NOT name people. The web page and `GET /reports/flow` SHALL follow the same rules.

#### Scenario: Developer
- **WHEN** a developer opens the report
- **THEN** the request is refused
