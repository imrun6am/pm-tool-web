## ADDED Requirements

### Requirement: Supported roles
The system SHALL support exactly these roles: Admin, CTO, Team Lead, Developer, QA, and Viewer. Each user SHALL have exactly one role, and the role SHALL be displayed with a human-readable label.

#### Scenario: Assign any supported role
- **WHEN** a manager assigns one of the supported roles to a user
- **THEN** the system stores it and shows its label in the UI

#### Scenario: Reject an unsupported role
- **WHEN** a role value outside the supported set is submitted
- **THEN** the system rejects it with a validation error

### Requirement: Only admins manage structure
The system SHALL restrict creating, editing, and deleting projects, teams, releases, phases, off-days, and documents to users with the Admin role. All other roles SHALL have read access to that structure.

#### Scenario: Non-admin cannot edit structure
- **WHEN** a CTO, Team Lead, Developer, QA, or Viewer attempts to create or edit a project, team, release, or off-day
- **THEN** the system denies the action

#### Scenario: Admin manages structure
- **WHEN** an Admin creates or edits structure
- **THEN** the system permits it

### Requirement: All roles collaborate on tasks and comments
The system SHALL allow every authenticated user, regardless of role, to add and edit tasks and subtasks, change task status ("check" tasks), and post comments.

#### Scenario: Any role can check a task
- **WHEN** a Developer or QA changes a task's status
- **THEN** the system saves the new status

#### Scenario: Any role can comment
- **WHEN** a Team Lead posts a comment on a release or task
- **THEN** the system stores it attributed to that user

### Requirement: Managing users is limited to Admin and CTO
The system SHALL allow only Admin and CTO roles to manage user accounts.

#### Scenario: CTO manages users but not structure
- **WHEN** a CTO opens the app
- **THEN** the system allows user management but does not allow editing projects, teams, releases, or off-days

### Requirement: No self-registration
The system SHALL NOT provide public self-registration. New accounts SHALL be created only through user management.

#### Scenario: Registration route is unavailable
- **WHEN** a visitor requests the registration page
- **THEN** the system does not serve a registration form

#### Scenario: Login remains available
- **WHEN** an existing active user submits valid credentials
- **THEN** the system logs them in
