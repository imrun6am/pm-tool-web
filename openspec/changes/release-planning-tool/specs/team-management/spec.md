## ADDED Requirements

### Requirement: Manage teams
The system SHALL allow an admin to create, view, edit, and archive teams. Each team SHALL have a name and MAY have a description and a color used to distinguish it on the dashboard. Team names SHALL be unique among non-archived teams.

#### Scenario: Create a team
- **WHEN** an admin submits a team with a unique name
- **THEN** the system creates the team and it becomes available to own release plans

#### Scenario: Reject duplicate team name
- **WHEN** an admin submits a team whose name matches an existing non-archived team
- **THEN** the system rejects it with a validation error and does not create a duplicate

#### Scenario: Edit a team
- **WHEN** an admin edits a team's name, description, or color
- **THEN** the system saves the changes

### Requirement: Archive instead of hard delete when in use
The system SHALL prevent permanent deletion of a team that owns release plans; instead it SHALL support archiving. A team with no releases MAY be deleted permanently.

#### Scenario: Archive a team with releases
- **WHEN** an admin archives a team that owns release plans
- **THEN** the team is marked archived, hidden from new-release team pickers, and its existing releases remain visible on the dashboard

#### Scenario: Delete an empty team
- **WHEN** an admin deletes a team that owns no release plans
- **THEN** the system permanently removes the team

### Requirement: Per-team workload view
The system SHALL provide a view of a single team showing its release plans ordered by start date, so a planner can see when the team is busy and when it is next free.

#### Scenario: View a team's schedule
- **WHEN** an admin or viewer opens a team's page
- **THEN** the system lists that team's releases in chronological order with their windows and highlights any overlaps
