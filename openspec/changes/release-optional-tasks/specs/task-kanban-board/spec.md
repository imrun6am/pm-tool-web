## ADDED Requirements

### Requirement: Tasks without a release
The system SHALL allow a task to exist without a release. Linking a task to a release SHALL be optional, and a top-level task's release SHALL be changeable or removable after creation. A subtask SHALL always carry its parent's release, or none.

#### Scenario: Quick-add with no release
- **WHEN** a user adds a card on the board and leaves the release as "No release"
- **THEN** the system creates the task with no release, and it appears on the board

#### Scenario: Create through the API with no release
- **WHEN** an API client creates a task with `release_id` omitted or `null`
- **THEN** the system creates the task with no release and returns `release_id: null` and `release: null`

#### Scenario: Link and unlink
- **WHEN** a user sets a task's release on the task page, or clears it
- **THEN** the system links or unlinks the task, and its subtasks follow

#### Scenario: Subtask inherits
- **WHEN** a subtask is added to a task with no release
- **THEN** the subtask has no release

#### Scenario: Deleting a release keeps its tasks
- **WHEN** a release is deleted
- **THEN** its tasks remain, unlinked

### Requirement: Unlinked tasks stay out of release views
Release detail pages, release task panels and release, project and dashboard progress roll-ups SHALL include only tasks linked to the release(s) in question. Unlinked tasks SHALL be ignored there. Activity on an unlinked task SHALL be recorded with no release.

#### Scenario: Release page excludes unlinked tasks
- **WHEN** a user opens a release page while unlinked tasks exist
- **THEN** the task panel lists only that release's tasks

#### Scenario: Progress ignores unlinked tasks
- **WHEN** project or dashboard task counts are computed
- **THEN** unlinked tasks are not counted

### Requirement: Visibility of unlinked tasks
Every signed-in user, Developer and QA included, SHALL be able to see and work on unlinked tasks, as on the global board.

#### Scenario: Developer opens an unlinked task
- **WHEN** a developer opens an unlinked task's page
- **THEN** the system shows it

## MODIFIED Requirements

### Requirement: Scope and filter the board
The system SHALL provide the board globally (all tasks) and scoped to a single release. It SHALL allow filtering by release, including a "No release" option for unlinked tasks, and by assignee. A release page SHALL link to the board scoped to that release. A card SHALL show its release only when one is linked.

#### Scenario: Release-scoped board
- **WHEN** a user opens the board for a specific release
- **THEN** the system shows only that release's tasks

#### Scenario: No-release filter
- **WHEN** a user filters the board by "No release"
- **THEN** the system shows only tasks with no release

#### Scenario: Filter by assignee
- **WHEN** a user filters the board by an assignee
- **THEN** the system shows only cards assigned to that user

#### Scenario: Reach the board from a release
- **WHEN** a user clicks the board link on a release page
- **THEN** the system opens the board filtered to that release
