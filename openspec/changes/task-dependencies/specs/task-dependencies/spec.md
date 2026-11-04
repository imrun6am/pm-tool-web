## ADDED Requirements

### Requirement: Tasks can be flagged as blocked
A user who can edit a task SHALL be able to flag it as blocked with a required reason, and clear the flag, on the web and through the API. Finishing the task SHALL clear the flag.

#### Scenario: Flag
- **WHEN** a member flags a task with a reason
- **THEN** the task reads as blocked, and the card and task page show it

#### Scenario: No reason
- **WHEN** a member flags a task without a reason
- **THEN** the system refuses it

### Requirement: Tasks can wait for other tasks
A user who can edit a task SHALL be able to make it wait for another task they can see, by key, and remove that link. The system SHALL refuse a task waiting for itself and any loop, direct or indirect.

#### Scenario: Blocked until finished
- **WHEN** a task waits for an unfinished task
- **THEN** it reads as blocked until that task is Done or Archived

#### Scenario: Loop
- **WHEN** A waits for B, B waits for C, and someone makes C wait for A
- **THEN** the system refuses it

#### Scenario: Task out of reach
- **WHEN** a developer names a task on another team
- **THEN** the error is the same as for a task that doesn't exist

### Requirement: Links respect team scoping
The system SHALL list only linked tasks the viewer may see, while still reporting a task as blocked because of one they can't.

#### Scenario: Hidden blocker
- **WHEN** a developer opens a task waiting for another team's task
- **THEN** it reads as blocked and doesn't show that task

### Requirement: Lists filter on blocked
The board and the task list SHALL accept a blocked filter that selects flagged tasks and tasks waiting for an unfinished one, leaving out finished work.

#### Scenario: Filter
- **WHEN** a client asks for `blocked=1`
- **THEN** only blocked tasks are listed

### Requirement: The right people hear about it
Flagging a task SHALL notify its team lead and assignee, not the person flagging it. A changed reason SHALL NOT notify again. When a task's last blocker finishes and it isn't flagged, its assignee SHALL be notified.

#### Scenario: Last blocker
- **WHEN** the second of two blockers finishes
- **THEN** the assignee is notified, and wasn't after the first
