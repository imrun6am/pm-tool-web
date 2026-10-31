## ADDED Requirements

### Requirement: Status-column board
The system SHALL present a board with one column per task status — To Do, In Progress, In Review, Done — showing top-level tasks as cards in the column matching their status. Each card SHALL show the task title, its release, assignee (if any), due date (if any), phase (if any), subtask progress, and comment count.

#### Scenario: Board groups tasks by status
- **WHEN** an authenticated user opens the board
- **THEN** the system shows four status columns with each top-level task as a card in its status column

#### Scenario: Column counts
- **WHEN** the board is displayed
- **THEN** each column header shows the number of cards in it

#### Scenario: Empty column
- **WHEN** a status has no tasks
- **THEN** the system shows an empty placeholder in that column

### Requirement: Move a card to change status and order
The system SHALL allow any authenticated user to drag a card to another column, which SHALL change that task's status, and to reorder cards within a column, which SHALL persist their order. Changes SHALL survive a page reload.

#### Scenario: Drag to a new column changes status
- **WHEN** a user drops a card into a different status column
- **THEN** the system updates that task's status to the column's status and the card remains there after reload

#### Scenario: Reorder within a column persists
- **WHEN** a user reorders cards within a column
- **THEN** the system stores the new order and shows it after reload

#### Scenario: Guests cannot move cards
- **WHEN** an unauthenticated request attempts to move a card
- **THEN** the system rejects it

#### Scenario: Invalid status is rejected
- **WHEN** a move specifies a status outside the four supported values
- **THEN** the system rejects it with a validation error

### Requirement: Scope and filter the board
The system SHALL provide the board globally (all tasks) and scoped to a single release, and SHALL allow filtering by release and by assignee. A release page SHALL link to the board scoped to that release.

#### Scenario: Release-scoped board
- **WHEN** a user opens the board for a specific release
- **THEN** the system shows only that release's tasks

#### Scenario: Filter by assignee
- **WHEN** a user filters the board by an assignee
- **THEN** the system shows only cards assigned to that user

#### Scenario: Reach the board from a release
- **WHEN** a user clicks the board link on a release page
- **THEN** the system opens the board filtered to that release
