## ADDED Requirements

### Requirement: Admins can delete a board card
The system SHALL let a user with the admin role delete a top-level task from the board, on the web and through the API. Deleting a card SHALL also delete its subtasks and the comments on the task and its subtasks, and SHALL record a `deleted` activity entry for each deleted task, with the admin as the causer.

#### Scenario: Admin deletes one card
- **WHEN** an admin deletes a card that has subtasks and comments
- **THEN** the task, its subtasks and their comments are removed, and each deleted task has a `deleted` activity entry caused by the admin

#### Scenario: A subtask is not a card
- **WHEN** an admin calls the single-card delete endpoint with a subtask's id
- **THEN** the system answers 403 and the subtask remains

### Requirement: Admins can delete cards in bulk
The system SHALL accept a list of task ids and delete those tasks together in one transaction. Every submitted id SHALL be validated as an existing top-level task and authorized individually. If any id fails, the system SHALL delete nothing.

#### Scenario: Bulk delete
- **WHEN** an admin submits the ids of three cards
- **THEN** all three cards and their subtasks and comments are removed, and tasks not listed are untouched

#### Scenario: One id is invalid
- **WHEN** an admin submits valid ids together with an id that doesn't exist, a subtask's id, a repeated id or a non-integer value
- **THEN** the system answers 422 and deletes nothing

#### Scenario: Empty list
- **WHEN** an admin submits no ids
- **THEN** the system answers 422

### Requirement: Select all covers the cards shown
The board SHALL offer admins a "select all" control that selects exactly the cards currently shown under the active release, team and assignee filters. The control SHALL state how many cards it covers and name the active filters. Cards in a collapsed Archive column are not shown, so select all SHALL leave them out and SHALL say so.

#### Scenario: Select all with a filter
- **WHEN** an admin filters the board by a team, selects all and confirms
- **THEN** only that team's cards are deleted, and cards of other teams remain

#### Scenario: The bar states its scope
- **WHEN** an admin opens the board with a team filter active
- **THEN** the bulk bar names that team next to the select-all control

#### Scenario: Collapsed archive is left out
- **WHEN** the Archive column is collapsed and an admin selects all
- **THEN** archived cards are not selected, and the bar says how many were left out

### Requirement: Deleting asks for confirmation
The board SHALL ask for confirmation before any delete. The confirmation SHALL state how many tasks will be deleted and, when subtasks would go too, how many, and SHALL say that comments go with them and that this can't be undone.

#### Scenario: Bulk confirmation with subtasks
- **WHEN** an admin deletes two selected cards that have three subtasks between them
- **THEN** the confirmation states two tasks and three subtasks before anything is deleted

#### Scenario: Cancelling keeps everything
- **WHEN** an admin cancels the confirmation
- **THEN** nothing is deleted and the selection stays as it was

### Requirement: Only admins can delete tasks anywhere
The general task delete route SHALL answer 403 to every role but admin, on the web and through the API, for tasks and subtasks. The task page and a release's task panel SHALL render delete buttons for admins only.

#### Scenario: Non-admin deletes their own task
- **WHEN** a non-admin calls `DELETE tasks/{task}` for a task or subtask they created
- **THEN** the system answers 403 and the task remains

#### Scenario: Admin deletes a subtask
- **WHEN** an admin calls `DELETE tasks/{task}` with a subtask's id
- **THEN** the subtask is removed and its parent remains

#### Scenario: Non-admin opens a task page
- **WHEN** a non-admin opens a task page or a release's task panel
- **THEN** no task or subtask delete buttons are shown

### Requirement: Only admins can delete from the board
The board delete endpoints SHALL answer 403 to every role but admin, on the web and through the API, before looking at the submitted ids. The board SHALL render no selection or delete controls for those roles.

#### Scenario: Non-admin calls the endpoint
- **WHEN** a CTO, tech lead, team lead, developer, QA or viewer calls either endpoint
- **THEN** the system answers 403 and deletes nothing

#### Scenario: Non-admin opens the board
- **WHEN** a non-admin opens the board
- **THEN** the board shows no checkboxes, delete buttons or bulk bar

### Requirement: The board updates in place
After a delete succeeds, the board SHALL remove the deleted cards and update its column counts, total and progress without reloading the page. If a delete fails, the cards and the selection SHALL stay and the user SHALL see an error message.

#### Scenario: Successful delete
- **WHEN** a delete succeeds
- **THEN** the cards leave the board, the counts update, and a toast confirms it, with no page reload

#### Scenario: Failed delete
- **WHEN** the server rejects a delete
- **THEN** the cards remain and an error toast appears

### Requirement: The board fits laptop screens
On `lg` screens and up, the board's title, filters, summary and bulk controls SHALL take as little height as is practical so the cards get most of the viewport, and the status columns SHALL share the available width down to a minimum width, below which the board scrolls sideways. On narrower screens the filters and bulk controls SHALL wrap without making the page scroll sideways.

#### Scenario: Laptop screen
- **WHEN** a user opens the board at about 1280x720 or 1440x800
- **THEN** the columns take most of the height, and the column set fits the width or nearly so

#### Scenario: Phone screen
- **WHEN** a user opens the board at phone width
- **THEN** the filters and bulk controls wrap, and only the columns scroll sideways

### Requirement: Web and API parity
The web and API endpoints SHALL share one service and one validation path and SHALL produce the same result for the same input. The API SHALL report the deleted ids and the number of subtasks removed.

#### Scenario: Same result on both surfaces
- **WHEN** the same kind of bulk delete runs through the web and through the API
- **THEN** both report the deleted ids and subtask count in the same fields, and both leave the same records behind
