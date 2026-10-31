## ADDED Requirements

### Requirement: Record attributable activity
The system SHALL record an activity entry whenever a project, team, release, release phase, off-day, task, or comment is created, updated, or deleted. Each entry SHALL capture the acting user (causer), the subject, the event type (`created`, `updated`, `deleted`), a human-readable description, and the timestamp.

#### Scenario: Creation is recorded with the actor
- **WHEN** a user creates a task
- **THEN** the system records a `created` activity naming that user as the causer and the task as the subject

#### Scenario: Update captures old and new values
- **WHEN** a user changes a release's dates
- **THEN** the system records an `updated` activity that includes the changed fields with their previous and new values

#### Scenario: Deletion is recorded
- **WHEN** a user deletes a comment
- **THEN** the system records a `deleted` activity attributed to that user

### Requirement: View activity history
The system SHALL provide a global activity feed showing recent entries newest-first, and SHALL show a per-release history of entries for that release and its tasks, phases, off-days, and comments. Each entry SHALL clearly display who did what and when.

#### Scenario: Global feed lists recent changes
- **WHEN** an authenticated user opens the Activity page
- **THEN** the system lists recent activity entries newest-first with causer, description, and time

#### Scenario: Per-release history is scoped
- **WHEN** a user views a release's history panel
- **THEN** the system shows only entries related to that release and its child records

#### Scenario: Changed values are visible for updates
- **WHEN** a user inspects an `updated` entry
- **THEN** the system shows which fields changed and their old → new values
