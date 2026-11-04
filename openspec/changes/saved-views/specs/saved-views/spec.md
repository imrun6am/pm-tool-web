## ADDED Requirements

### Requirement: People can save and reopen filters
A user SHALL be able to save the current filters of the board or the task list under a name, and reopen them. Saving under an existing name SHALL replace that view. Filters the page doesn't accept SHALL be dropped.

#### Scenario: Save and open
- **WHEN** a user saves a view with a priority filter and opens it later
- **THEN** the page opens with that filter applied

#### Scenario: Stray filters
- **WHEN** a request includes a filter the page doesn't accept
- **THEN** it isn't stored

### Requirement: Views are personal unless a lead shares them
Each page SHALL list the user's own views and shared views, and SHALL NOT list other people's personal views. Only leads SHALL be able to share.

#### Scenario: Non-lead shares
- **WHEN** a developer saves a shared view
- **THEN** the system answers 403

### Requirement: Removing views
The owner SHALL be able to remove their view, and a lead SHALL be able to remove a shared view. Others SHALL get a 403.

#### Scenario: Someone else's personal view
- **WHEN** a lead tries to remove another person's personal view
- **THEN** the system answers 403
