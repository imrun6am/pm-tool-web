## ADDED Requirements

### Requirement: Rules create tasks
A rule that's due and active SHALL create one task with its details, due the set number of days later, and move to its next date. Running again the same day SHALL create nothing. A rule overdue by several periods SHALL create one task.

#### Scenario: Long gap
- **WHEN** a weekly rule was missed for a month
- **THEN** one task is created and the next date is in the future

### Requirement: Schedules
Daily rules SHALL skip configured weekend days. Monthly rules SHALL keep their day, using a shorter month's last day when needed.

#### Scenario: Short month
- **WHEN** a rule set for the 31st runs in January
- **THEN** its next dates are the last day of February, then March 31

### Requirement: Pausing and removing
Leads SHALL be able to pause, resume and remove rules. Resuming SHALL skip what was missed. Removing SHALL keep tasks already made. Others SHALL get a 403.

#### Scenario: Resume
- **WHEN** a paused rule is resumed after its date has passed
- **THEN** no task is created for the missed time
