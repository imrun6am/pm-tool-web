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

### Requirement: Developers and QA see only their own teams' tasks
A Developer or QA user SHALL see and act on only these tasks:
- tasks of the teams they currently belong to;
- tasks assigned to them;
- teamless tasks they created.

This SHALL hold on the board, task lists, release task lists, the task page, subtasks, task comments and board moves, on the web and API. Any other task SHALL be refused (403). Leads and viewers SHALL see all tasks.

#### Scenario: Developer opens another team's task
- **WHEN** a developer opens, edits, comments on or moves a task of a team they don't belong to
- **THEN** the system refuses with 403

#### Scenario: Board hides other teams' work
- **WHEN** a developer opens the board, with any filters
- **THEN** no other team's tasks are listed

### Requirement: Developers and QA don't choose a team
A task created by a Developer or QA user SHALL get its team on the server.
- **One team:** their current team, whatever team was submitted.
- **Several teams:** one of their own teams, which they choose.
- **No team:** no team; the task is personal.

They SHALL link only their own teams' releases and assign only themselves or current teammates.

#### Scenario: Single-team developer creates a task
- **WHEN** a developer on one team creates a task and submits another team's id
- **THEN** the task is created in the developer's team

#### Scenario: Multi-team developer creates a task
- **WHEN** a developer on several teams creates an unlinked task without choosing a team, or chooses a team they're not on
- **THEN** the system rejects it with a validation error

### Requirement: Tasks carry an optional team
Each task SHALL have an optional team.
- A task linked to a release SHALL have that release's team and SHALL follow it when the release changes team.
- An unlinked task's team SHALL be chosen by the user, or default to the assignee's team when the assignee belongs to exactly one team. Unlinking a task SHALL keep its team.
- A subtask SHALL have its parent's team.

#### Scenario: Linked task takes the release's team
- **WHEN** a task is linked to a release
- **THEN** its team is the release's team

#### Scenario: Unlinked task defaults from the assignee
- **WHEN** an unlinked task is created with an assignee who belongs to exactly one team and no team is chosen
- **THEN** the task's team is the assignee's team

## MODIFIED Requirements

### Requirement: Scope and filter the board
The system SHALL provide the board globally (all tasks) and scoped to a single release. It SHALL allow filtering by release (including "No release"), by team (including "No team") and by assignee. These filters SHALL combine, and every signed-in user, Developer and QA included, SHALL be able to use them. A release page SHALL link to the board scoped to that release. A card SHALL show its release only when one is linked.

#### Scenario: Release-scoped board
- **WHEN** a user opens the board for a specific release
- **THEN** the system shows only that release's tasks

#### Scenario: No-release filter
- **WHEN** a user filters the board by "No release"
- **THEN** the system shows only tasks with no release

#### Scenario: Filter by team
- **WHEN** a user filters the board by a team, alone or with a release or assignee filter
- **THEN** the system shows only matching tasks of that team, linked or not

#### Scenario: No-team filter
- **WHEN** a user filters the board by "No team"
- **THEN** the system shows only tasks with no team

#### Scenario: Filter by assignee
- **WHEN** a user filters the board by an assignee
- **THEN** the system shows only cards assigned to that user

#### Scenario: Reach the board from a release
- **WHEN** a user clicks the board link on a release page
- **THEN** the system opens the board filtered to that release
