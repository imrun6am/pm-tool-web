## ADDED Requirements

### Requirement: Manage projects
The system SHALL allow an admin to create, view, edit, and archive projects. Each project SHALL have a name and MAY have a description and a color used to distinguish it on the dashboard. Project names SHALL be unique among non-archived projects.

#### Scenario: Create a project
- **WHEN** an admin submits a project with a unique name
- **THEN** the system creates the project and it becomes available for release plans

#### Scenario: Reject duplicate project name
- **WHEN** an admin submits a project whose name matches an existing non-archived project
- **THEN** the system rejects it with a validation error and does not create a duplicate

#### Scenario: Edit a project
- **WHEN** an admin edits a project's name, description, or color
- **THEN** the system saves the changes and reflects the new color on the dashboard

### Requirement: Archive instead of hard delete when in use
The system SHALL prevent permanent deletion of a project that has release plans; instead it SHALL support archiving so historical releases remain intact. A project with no releases MAY be deleted permanently.

#### Scenario: Archive a project with releases
- **WHEN** an admin archives a project that has release plans
- **THEN** the project is marked archived, hidden from new-release project pickers, and its existing releases remain visible on the dashboard

#### Scenario: Delete an empty project
- **WHEN** an admin deletes a project that has no release plans
- **THEN** the system permanently removes the project
