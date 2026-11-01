## ADDED Requirements

### Requirement: Celebrate finishing a task
When a signed-in user changes a task's status from an open status to Done, the response SHALL carry a celebration message for that user, naming the task. Moving a task to Archive, saving a task that was already Done, or reopening a task SHALL NOT celebrate.

#### Scenario: Drag to Done on the board
- **WHEN** a developer drags a card into the Done column
- **THEN** the JSON response carries an `X-Celebrate` header, and the page shows a celebration toast

#### Scenario: Mark done on the task page
- **WHEN** a user sets a task's status to Done and saves
- **THEN** the redirected page shows a celebration toast

#### Scenario: Archive
- **WHEN** a lead moves a done task to Archive
- **THEN** no celebration is sent

### Requirement: All-clear message
When the completed task was assigned to the actor and they have no other open assigned tasks, the celebration SHALL use the all-clear message.

#### Scenario: Last open task
- **WHEN** a developer completes their only remaining open task
- **THEN** the message says their plate is clear

### Requirement: Opt-out preference
Each user SHALL have a "Celebrate finished tasks" preference, on by default, that they can change on their Profile page and over the API. When it is off, no celebration is sent.

#### Scenario: Turned off
- **WHEN** a user who turned celebrations off completes a task
- **THEN** the response carries no celebration

### Requirement: Reduced motion
The celebration's animation SHALL NOT play when the user's system requests reduced motion. The message still appears.

#### Scenario: Reduced motion
- **WHEN** a user with reduced motion enabled completes a task
- **THEN** the toast appears without the sparkle animation
