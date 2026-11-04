## ADDED Requirements

### Requirement: Tasks open in a side panel
Clicking a task title on the board or the task list SHALL open that task in a side panel without leaving the page. A modified click SHALL still follow the link. The panel SHALL follow the same visibility rules as the task page.

#### Scenario: Open
- **WHEN** a user clicks a card's title
- **THEN** the task's details open in a panel over the board

#### Scenario: Out of reach
- **WHEN** a developer requests the panel for another team's task
- **THEN** the system answers 403

#### Scenario: Direct address
- **WHEN** someone opens the panel address outside its frame
- **THEN** they go to the task page

### Requirement: The panel stays open through changes
Saving details, logging time, commenting and changing dependencies from the panel SHALL update that part in place and keep the panel open, including when validation fails.

#### Scenario: Validation failure
- **WHEN** a user logs an unreadable time in the panel
- **THEN** the panel stays open and shows the error

#### Scenario: Other pages
- **WHEN** the same action runs from outside the panel
- **THEN** it answers as before

### Requirement: The page behind catches up
When the panel closes after a change, the page behind SHALL refresh.

#### Scenario: Status changed
- **WHEN** a user changes a task's status in the panel and closes it
- **THEN** the card is in its new column
