## ADDED Requirements

### Requirement: Everyone can list all the tasks they may see
The system SHALL offer a paged list of top-level tasks with title search, filters by release, team, assignee, status, priority, label and blocked, and sorting. Developers and QA SHALL see only tasks they may see, whatever the filters ask for.

#### Scenario: Search
- **WHEN** a user searches for "50%"
- **THEN** only titles containing that exact text are listed

#### Scenario: Sort
- **WHEN** a user sorts by priority
- **THEN** tasks run Urgent, High, Medium, Low, then tasks with none

#### Scenario: Team scoping
- **WHEN** a developer filters by another team
- **THEN** nothing is listed

### Requirement: Many tasks can be changed at once
A user SHALL be able to change the status, assignee or priority of selected tasks, or add a label to them, on the web and through the API. The change SHALL apply to all of them or none, and SHALL update status history, the activity log and notifications as a single edit would.

#### Scenario: Change
- **WHEN** a user selects two tasks and sets a status and an assignee
- **THEN** both change, each notifying the assignee, and other tasks don't

#### Scenario: Task out of reach
- **WHEN** a selection includes a task the user can't edit
- **THEN** the system answers 403 and nothing changes

#### Scenario: Unsuitable assignee
- **WHEN** a new assignee isn't on the team of one selected task's release
- **THEN** the system refuses it and nothing changes

#### Scenario: Nothing chosen
- **WHEN** every change field is blank
- **THEN** the system refuses it
