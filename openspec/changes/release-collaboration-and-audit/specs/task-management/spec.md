## ADDED Requirements

### Requirement: Manage tasks on a release
The system SHALL allow any authenticated user to create, edit, and delete tasks belonging to a release. Each task SHALL have a title and a status of `todo`, `in_progress`, `in_review`, or `done` (default `todo`), and MAY have a description, an assignee (a member of the release's owning team), a due date, and a link to one of the release's phases (Development, QA, Retest, or Release).

#### Scenario: Create a task
- **WHEN** an authenticated user adds a task with a title to a release
- **THEN** the system creates the task with status `todo` and lists it under the release

#### Scenario: Reject a task without a title
- **WHEN** a user submits a task with an empty title
- **THEN** the system rejects it with a validation error

#### Scenario: Set task details
- **WHEN** a user sets a task's status, assignee, due date, or phase
- **THEN** the system saves those values and shows them on the task

#### Scenario: Assignee options are the owning team's members
- **WHEN** a user opens a task's assignee selector
- **THEN** it offers only members of the release's team (plus the current assignee, even if they have left the team)

#### Scenario: Assignee outside the team rejected
- **WHEN** a task is saved with an assignee who is not a member of the release's team
- **THEN** the system rejects it with a validation error (keeping an unchanged existing assignee is allowed)

#### Scenario: Delete a task removes its subtasks and comments
- **WHEN** a user deletes a task
- **THEN** the system deletes the task, its subtasks, and all comments on the task and its subtasks

### Requirement: Subtasks
The system SHALL allow a task to have subtasks — child tasks limited to one level of nesting (a subtask cannot itself have subtasks). Subtasks share the same fields as tasks and belong to the same release as their parent.

#### Scenario: Add a subtask
- **WHEN** a user adds a subtask under a task
- **THEN** the system creates it as a child of that task within the same release

#### Scenario: Nesting is limited to one level
- **WHEN** a user attempts to add a subtask under a subtask
- **THEN** the system prevents it (the subtask has no add-subtask affordance and the request is rejected)

#### Scenario: Parent shows subtask progress
- **WHEN** a task has subtasks
- **THEN** the system shows how many of its subtasks are `done` out of the total

### Requirement: Task visibility
The system SHALL show a release's tasks (with subtasks) on the release page and SHALL provide a task detail view showing the task's fields, its subtasks, and its comment thread.

#### Scenario: Open a task
- **WHEN** a user opens a task
- **THEN** the system shows its details, subtasks, and comments
