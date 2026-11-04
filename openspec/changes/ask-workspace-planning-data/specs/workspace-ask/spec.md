## ADDED Requirements

### Requirement: Release health in every answer
The system SHALL give the model each live release's forecast, latest status update, blocked and overdue counts, working days left, upcoming off-days and current phase, for releases the asker may see.

#### Scenario: Asking when a release will finish
- **WHEN** the team has enough finished-task history
- **THEN** the context holds the forecast's 50%, 85% and 95% dates and the chance of finishing by the end date

#### Scenario: Too little history
- **WHEN** the team hasn't finished enough work to forecast
- **THEN** the context says so, and no dates are given

#### Scenario: Another team's release
- **WHEN** a Developer or QA asks
- **THEN** no release outside their teams appears

### Requirement: Blocked and overdue work
The system SHALL always include blocked, overdue and urgent work the asker may see, with totals, narrowed to any team or release the question names.

#### Scenario: Blocked by a hidden task
- **WHEN** a Developer's task waits on another team's task
- **THEN** the context says it's waiting on a task they can't see, and doesn't name that task

### Requirement: Task detail
The system SHALL describe each task with its priority, estimate, logged total, labels, phase, time in status and blocked reason.

#### Scenario: Quoting a task
- **WHEN** the question contains `PM-123` and the asker may see that task
- **THEN** that task is in the context

#### Scenario: Quoting a hidden task
- **WHEN** the question contains `PM-123` and the asker may not see that task
- **THEN** it isn't in the context

### Requirement: Working calendar
The system SHALL state today, the working week and the next days off in every request, from `WorkingWeek`.

#### Scenario: Alternate day off
- **WHEN** an alternate day off is configured
- **THEN** the next ones are listed, and working days left on a release exclude them

### Requirement: Capacity for leads
The system SHALL include team capacity only for leads, and only for the teams `CapacityService::teamsFor` returns.

#### Scenario: Team lead
- **WHEN** a team lead asks who has capacity
- **THEN** only teams they lead appear

#### Scenario: Developer or viewer
- **WHEN** a Developer, QA or viewer asks who has capacity
- **THEN** no capacity figures are sent, and the asker block says they aren't available

### Requirement: Reports
The system SHALL include the flow report for leads and viewers, and the logged-hours report for leads within `TimeReportService`'s reach.

#### Scenario: Developer asks about hours
- **WHEN** a Developer asks about logged hours
- **THEN** only their own hours are sent

#### Scenario: Viewer asks about hours
- **WHEN** a viewer asks about logged hours
- **THEN** no hours report or per-person hours are sent

### Requirement: Requests
The system SHALL include only requests the asker reported, and pending requests they may decide.

#### Scenario: Someone else's request
- **WHEN** a Developer asks about requests
- **THEN** other people's requests aren't sent

### Requirement: Notifications
The system SHALL include only the asker's own notifications.

#### Scenario: Another person's inbox
- **WHEN** anyone asks about notifications
- **THEN** only rows from their own inbox are sent

### Requirement: Tasksheets
The system SHALL include plans, results, leave and standup attendance for teams `TasksheetService::teamsFor` allows, and SHALL never send feedback or points.

#### Scenario: Lead asks about tasksheets
- **WHEN** a lead asks about a team's tasksheet
- **THEN** the row's plan and result are sent, and its feedback and points aren't

#### Scenario: Another team's sheet
- **WHEN** a Developer asks who is on leave
- **THEN** only their own teams' rows are sent

### Requirement: Recent changes
The system SHALL include status moves, new tasks, finished releases and release status updates for a period the question names, or the last seven days, for records the asker may see.

#### Scenario: Asking what changed
- **WHEN** a Developer asks what changed this week
- **THEN** only their visible tasks and releases appear

### Requirement: Notes and meetings by intent
The system SHALL include the latest notes the asker may see when the question is about meetings or notes, even if no word matches.

#### Scenario: Asking about the last standup
- **WHEN** the question asks what was decided in the last standup
- **THEN** the latest visible meeting notes are in the context, and attendee-only notes the asker didn't attend aren't

### Requirement: Saying what isn't available
The system SHALL tell the model what the asker's role can't see, so the answer says that rather than reporting no data.

#### Scenario: Restricted data
- **WHEN** a Developer asks
- **THEN** the asker block names capacity, flow and hours reports as unavailable, and performance scores and feedback as never available

### Requirement: Cited references become links
The system SHALL link every reference it supplied and drop any other, and SHALL leave ordinary bracketed text alone.

#### Scenario: Unknown reference
- **WHEN** the answer cites a reference that wasn't supplied
- **THEN** it's removed from the answer and sources
