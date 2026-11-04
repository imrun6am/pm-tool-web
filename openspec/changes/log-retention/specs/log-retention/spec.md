## ADDED Requirements

### Requirement: Logs are removed after the retention period
The system SHALL remove activity rows, AI usage rows and failed jobs older than the retention period, once a day. The period SHALL come from one setting and default to 3 months.

#### Scenario: Old row
- **WHEN** the daily cleanup runs
- **THEN** rows older than the retention period are gone

#### Scenario: Recent row
- **WHEN** the daily cleanup runs
- **THEN** rows inside the retention period remain

#### Scenario: Longer period
- **WHEN** the setting is raised to 6 months
- **THEN** rows 4 months old remain

#### Scenario: Invalid period
- **WHEN** the setting is 0 or isn't a number
- **THEN** the period is 1 month, and recent rows remain

### Requirement: Release history is kept
Activity rows that belong to a release SHALL NOT be removed by the cleanup, however old they are.

#### Scenario: Row linked to a release
- **WHEN** an old activity row has a release
- **THEN** it remains

#### Scenario: Row about a release with no link
- **WHEN** an old activity row has no release set and its subject is a release, phase or off day
- **THEN** it remains

### Requirement: Older rows are linked to their release
The migration SHALL give each activity row with no release the one its subject belongs to, without changing rows that already have one.

#### Scenario: Subject in a release
- **WHEN** a row with no release is about a release, phase, off day, task, meeting note, event or comment in a release
- **THEN** it's linked to that release and appears in the release's History

#### Scenario: Subject with no release
- **WHEN** a row's subject has no release, or no longer exists
- **THEN** it stays unlinked

#### Scenario: Row already linked
- **WHEN** a row already has a release
- **THEN** it keeps that release

### Requirement: Cleanup doesn't hold up the app
Rows SHALL be deleted in batches, not in one statement.

#### Scenario: More rows than one batch
- **WHEN** more rows are due than fit in a batch
- **THEN** all of them are removed across several statements

### Requirement: Log files follow the same period
Daily log files SHALL be kept for the retention period, and the file in use SHALL NOT be removed.

#### Scenario: Retention setting
- **WHEN** the retention period is 3 months
- **THEN** the daily channel keeps 90 days of files

### Requirement: Reports don't compare against removed data
A report SHALL NOT show a change against a period the log no longer holds, and SHALL NOT depend on the activity log for completed work.

#### Scenario: 90-day activity view
- **WHEN** the retention period is 3 months and the 90-day view is opened
- **THEN** it shows its totals with no comparison

#### Scenario: 30-day activity view
- **WHEN** the retention period is 3 months and the 30-day view is opened
- **THEN** it compares with the previous 30 days

#### Scenario: Rating evidence
- **WHEN** a task's activity rows have been removed
- **THEN** rating evidence still counts it as completed in its period
