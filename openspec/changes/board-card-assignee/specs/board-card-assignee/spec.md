## ADDED Requirements

### Requirement: The board filters to unassigned cards
The board SHALL accept `assignee_id=none`, on the web and through the API, and SHALL then return only top-level tasks with no assignee. The filter SHALL combine with the release and team filters, SHALL stay within what the viewer may see, and SHALL be kept in the URL like the other board filters. The web board SHALL offer it as an "Unassigned" option in the Assignee filter.

#### Scenario: Unassigned alone
- **WHEN** a user opens the board with `assignee_id=none`
- **THEN** only cards with no assignee are listed

#### Scenario: Combined with the team filter
- **WHEN** a user opens the board with `assignee_id=none` and a `team_id`
- **THEN** only that team's cards with no assignee are listed

#### Scenario: A developer stays within their teams
- **WHEN** a developer opens the board with `assignee_id=none`
- **THEN** unassigned cards from other teams are not listed

#### Scenario: The API echoes the filter
- **WHEN** a client reads `GET /board?assignee_id=none`
- **THEN** the response lists the same cards as the web board and `filters.assignee_id` is `none`

### Requirement: A card's assignee can be set from the board
A user who may update a task SHALL be able to assign someone to its card, change the assignee or clear it, on the web and through the API. The write SHALL change only the assignee: the card's status, release, team and position SHALL stay as they were. The change SHALL be recorded in the activity log, and the new assignee SHALL be notified as for any other assignment.

#### Scenario: Assigning
- **WHEN** a user assigns an active person to an unassigned card
- **THEN** the card has that assignee, its status is unchanged, and the assignee is notified

#### Scenario: Clearing
- **WHEN** a user sends an empty assignee for an assigned card
- **THEN** the card has no assignee

#### Scenario: The assignee is missing from the request
- **WHEN** a request leaves out `assignee_id`
- **THEN** the system answers 422 and the card keeps its assignee

### Requirement: Assignment follows the existing rules
The system SHALL enforce on the server the same rules as the task form. A user who may not update the task SHALL get a 403. On a task linked to a release, a new assignee SHALL be a member of the release's team, and the release used for this check SHALL be the task's own, whatever the request sends. A developer or QA SHALL only assign themselves or a teammate. A new assignee SHALL be an active user. Keeping the current assignee and clearing the assignee SHALL always pass these person checks.

#### Scenario: Developer on another team's task
- **WHEN** a developer assigns someone to a task they can't see
- **THEN** the system answers 403 and the task is unchanged

#### Scenario: Developer picks someone from another team
- **WHEN** a developer assigns a user who isn't their teammate to a task on their own team
- **THEN** the system answers 422 and the task is unchanged

#### Scenario: Assignee outside the release's team
- **WHEN** a lead assigns a user who isn't on the release's team to a linked task
- **THEN** the system answers 422 and the task is unchanged

#### Scenario: The request names another release
- **WHEN** a request for a linked task sends a `release_id` of another release, or null, in order to pass the team check
- **THEN** the task's own release is still used, the system answers 422, and the task is unchanged

#### Scenario: Deactivated person
- **WHEN** a user assigns a deactivated person
- **THEN** the system answers 422 and the task is unchanged

### Requirement: The picker lists only people the viewer may pick
The system SHALL list, for one card, the people the viewer may assign: the release team's active members, or every active user when the task has no release, narrowed to the viewer's teammates for a developer or QA. The list SHALL also include the task's current assignee. It SHALL NOT include the assignees of the task's subtasks. A user who may not update the task SHALL get a 403.

#### Scenario: Linked task
- **WHEN** a lead asks who can be assigned to a linked card
- **THEN** the list holds the release team's active members and the current assignee, and nobody else

#### Scenario: Developer sees only teammates
- **WHEN** a developer asks who can be assigned to a card
- **THEN** users from other teams are not listed, including anyone assigned to one of its subtasks

#### Scenario: A task the viewer can't see
- **WHEN** a developer asks about a task they can't see
- **THEN** the system answers 403

### Requirement: The board updates in place
The web board SHALL offer the assignee control on every card through the app's searchable dropdown, including an option to leave the card unassigned. After a successful change the card's assignee SHALL update without a page reload. A card that no longer matches the active assignee filter, or that the viewer can no longer see, SHALL leave the board, and the column counts, progress bar and admin selection SHALL update. A failed change SHALL leave the card as it was and SHALL show the reason.

#### Scenario: Assigning under the Unassigned filter
- **WHEN** the board is filtered to unassigned cards and a user assigns someone to a card
- **THEN** the card leaves the board and the column count drops, with no page reload

#### Scenario: The server refuses
- **WHEN** the server rejects the change
- **THEN** the card keeps its assignee and a toast shows the error

### Requirement: Board columns stay wide enough to read a card
On laptop and desktop screens each board column SHALL be at least 18rem wide and SHALL share any spare width with the others. Columns that don't all fit SHALL scroll sideways inside the board, and the page itself SHALL NOT scroll sideways. Phone widths SHALL keep working the same way.

#### Scenario: A laptop screen
- **WHEN** the board is shown at 1280 pixels wide
- **THEN** every column is at least 18rem wide, the columns scroll sideways, and the page doesn't

#### Scenario: A wide screen
- **WHEN** all the columns fit at their minimum width
- **THEN** they grow to share the spare width and there is no sideways scroll

### Requirement: A card can be given an assignee when it is created
The board's Add a card form SHALL offer an optional assignee, on the web and through the API, under the same limits as assigning an existing card. An unlinked card with no team SHALL take its assignee's team when they are on exactly one.

#### Scenario: Created with an assignee
- **WHEN** a user adds a card and picks an assignee
- **THEN** the card is created with that assignee and they are notified

#### Scenario: Left unassigned by a lead
- **WHEN** a lead adds a card without picking anyone
- **THEN** the card is created with no assignee

#### Scenario: A developer or QA adds a card
- **WHEN** a developer or QA adds a card without picking a teammate
- **THEN** the card is assigned to them

#### Scenario: Someone off the release's team
- **WHEN** a user adds a card linked to a release and picks someone outside its team
- **THEN** the card is not created, the form stays open and the error is shown

#### Scenario: A developer picks outside their team
- **WHEN** a developer adds a card and picks someone who is not a teammate
- **THEN** the card is not created
