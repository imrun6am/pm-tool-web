## ADDED Requirements

### Requirement: Tasks have optional priority, labels and estimate
A user who can edit a task SHALL be able to set its priority, labels and estimate, on the web and through the API. All three SHALL be optional.

#### Scenario: Set the fields
- **WHEN** a member sets High priority, the Bug label and an estimate of "4h 30m"
- **THEN** the task stores them and the card shows the priority and label

#### Scenario: Clear the labels from the task page
- **WHEN** a member unticks every label and saves
- **THEN** the task has no labels

#### Scenario: Partial update keeps labels
- **WHEN** an API client updates a task without sending `label_ids`
- **THEN** its labels are unchanged

#### Scenario: Bad values
- **WHEN** a request sends an unknown priority, an unreadable estimate or a label that doesn't exist
- **THEN** the system answers 422

### Requirement: Tasks have a short key
Every task SHALL have a key made of the prefix and its id, shown on its card and task page and returned by the API. Search SHALL find a task by its key, within what the viewer may see.

#### Scenario: Search by key
- **WHEN** a user searches for "PM-12"
- **THEN** task 12 is listed first, if they may see it

### Requirement: Lists filter by priority and label
The board and the task list SHALL filter by priority, by no priority and by label, in combination with other filters, on the web and through the API. The board's select all SHALL name these filters when active.

#### Scenario: Filter the board
- **WHEN** a user filters the board by Urgent and the Bug label
- **THEN** only tasks with both are shown

#### Scenario: Select all scope
- **WHEN** an admin filters by a label
- **THEN** the bulk bar names that label

### Requirement: Estimates are shown against time logged
The time panel and the API's time summary SHALL show the estimate, adding subtasks' estimates to the task's own.

#### Scenario: Roll-up
- **WHEN** a task estimated at 2 hours has a subtask estimated at 1 hour
- **THEN** its summary estimate is 3 hours

### Requirement: Leads manage labels
Leads SHALL be able to add, rename, recolor and delete labels. Anyone SHALL be able to read the list through the API. Deleting a label SHALL leave its tasks in place.

#### Scenario: Non-lead
- **WHEN** a developer or viewer tries to change a label
- **THEN** the system answers 403
