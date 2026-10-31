## ADDED Requirements

### Requirement: Mark a release complete
The system SHALL allow an admin to mark a release complete, recording the completing user, the completion time, and optional completion notes. The notes MAY contain Markdown and SHALL be rendered as safe HTML (no script/raw HTML injection).

#### Scenario: Complete with notes
- **WHEN** an admin marks a release complete with notes
- **THEN** the system records it as completed with the notes, the acting user, and the time

#### Scenario: Complete without notes
- **WHEN** an admin marks a release complete leaving notes empty
- **THEN** the system completes it with no notes

#### Scenario: Notes render safely
- **WHEN** completion notes contain Markdown and raw HTML
- **THEN** the system renders the Markdown formatting and strips unsafe HTML

#### Scenario: Non-admin cannot complete
- **WHEN** a non-admin attempts to complete or reopen a release
- **THEN** the system denies the action

### Requirement: Reopen a completed release
The system SHALL allow an admin to reopen a completed release, clearing its completed state so it returns to the dashboard.

#### Scenario: Reopen
- **WHEN** an admin reopens a completed release
- **THEN** the system clears its completion and it appears again on the dashboard

### Requirement: Completed releases hidden from the dashboard and overlap checks
The system SHALL exclude completed releases from the dashboard timeline and from same-team overlap detection.

#### Scenario: Not on the dashboard
- **WHEN** a release is completed
- **THEN** it no longer appears on the dashboard timeline

#### Scenario: Does not cause overlap warnings
- **WHEN** a new release overlaps a completed release for the same team
- **THEN** the system does not warn, because completed releases no longer book the team

### Requirement: All-releases list
The system SHALL provide a list of every release — active and completed — showing status, project, team, quarter, and window, filterable by status, project, team, and year, available to any authenticated user.

#### Scenario: List shows completed and active
- **WHEN** a user opens the releases list
- **THEN** the system shows all releases with their status

#### Scenario: Filter by status
- **WHEN** a user filters the list to completed releases
- **THEN** the system shows only completed releases
