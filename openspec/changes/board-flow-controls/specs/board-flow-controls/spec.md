## ADDED Requirements

### Requirement: Column limits
A team's lead or an org-level lead SHALL be able to set or clear a limit on an open column for that team. With the board showing only that team, the column SHALL show its count against the limit and be marked when over. A limit SHALL NOT stop a move. Others SHALL get a 403.

#### Scenario: Over the limit
- **WHEN** a column holds more cards than its limit
- **THEN** it's marked as over, and a move into it warns but succeeds

#### Scenario: Other filters
- **WHEN** the board has no team filter, or another filter too
- **THEN** no limit is shown

### Requirement: Card age
Open cards SHALL show how many days they've been in their status once it reaches three, from the status history. Other edits SHALL NOT reset it.

#### Scenario: Renamed card
- **WHEN** a card is renamed after five days in a status
- **THEN** it still shows five days

### Requirement: Grouping
The board SHALL be able to group each column's cards by person or by priority under headings, in the priority's own order or by name, with "none" last.

#### Scenario: By priority
- **WHEN** a user groups by priority
- **THEN** cards run Urgent, High, Medium, Low, then none
