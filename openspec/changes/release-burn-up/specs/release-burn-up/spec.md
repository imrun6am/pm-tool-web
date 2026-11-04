## ADDED Requirements

### Requirement: Burn-up chart
The release page SHALL show total and finished top-level tasks at each point from the release's start to today, or to its completion date if completed. Archived and trashed tasks SHALL NOT count. The chart SHALL have at most 25 points and always include its last day.

#### Scenario: Scope added
- **WHEN** a task is added to the release partway through
- **THEN** the scope line rises from that day

#### Scenario: Work reopened
- **WHEN** a finished task moves back to In Progress
- **THEN** it stops counting as done from that day, and still counts on the earlier days

#### Scenario: No tasks
- **WHEN** the release has no tasks
- **THEN** the card says progress shows once it has some

### Requirement: Readable without the chart
The same figures SHALL be available in a table under the chart and through `GET /releases/{id}/progress`, for anyone who can see the release.

#### Scenario: Developer on another team
- **WHEN** a developer who isn't on the release's team calls the endpoint
- **THEN** the request is refused
