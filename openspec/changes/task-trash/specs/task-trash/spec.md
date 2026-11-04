## ADDED Requirements

### Requirement: Deleting moves a task to the trash
Deleting a task SHALL move it and its subtasks to the trash, keeping its comments, time and history. A trashed task SHALL read as gone: not found on its routes, and left out of lists, searches and counts.

#### Scenario: Delete
- **WHEN** an admin deletes a task with subtasks and comments
- **THEN** the task and subtasks leave the board, and their comments and time are kept

#### Scenario: Reads as gone
- **WHEN** anyone opens a trashed task or searches for it
- **THEN** it isn't found

### Requirement: Admins can restore
An admin SHALL be able to restore trashed tasks, from the Trash page, through the API, or with Undo on the board. Restoring SHALL bring back the subtasks that went with the task and its comments and time. Restoring a subtask SHALL bring back a trashed parent.

#### Scenario: Subtask deleted earlier
- **WHEN** a subtask was deleted on its own before its parent, and the parent is restored
- **THEN** that subtask stays in the trash

#### Scenario: Other roles
- **WHEN** a non-admin opens the trash or restores
- **THEN** the system answers 403

### Requirement: Admins can delete for good
An admin SHALL be able to remove a trashed task for good, after a confirmation. A task not in the trash SHALL NOT be removable this way.

#### Scenario: Live task
- **WHEN** an admin calls the purge endpoint for a task not in the trash
- **THEN** the system answers 404 and the task remains

### Requirement: The trash empties itself
Tasks SHALL be removed for good 30 days after they were trashed.

#### Scenario: Prune
- **WHEN** the daily prune runs
- **THEN** tasks trashed over 30 days ago are gone, and newer ones remain
