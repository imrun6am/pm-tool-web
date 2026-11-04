## ADDED Requirements

### Requirement: Likely finish dates
For an open release with open tasks, the system SHALL show a likely date (50%), a fairly safe date (85%) and the chance of meeting the planned end, from the team's finished tasks per week over the last 12 full weeks, starting at its first week with a finished task.

#### Scenario: Steady team
- **WHEN** a team finishes exactly two tasks every week and six are open
- **THEN** the likely and fairly safe dates are both three weeks from today

#### Scenario: Uneven team
- **WHEN** a team's weeks vary
- **THEN** the fairly safe date is later than the likely date

#### Scenario: Past the planned end
- **WHEN** the planned end has passed and work is open
- **THEN** the chance of meeting it is 0%

#### Scenario: Repeatable
- **WHEN** the page is reloaded and nothing changed
- **THEN** it shows the same dates

### Requirement: No false precision
The system SHALL NOT show dates when the team finished work in fewer than 3 of those weeks, when nothing is left, or when the release is completed.

#### Scenario: New team
- **WHEN** a team has finished work in only two weeks
- **THEN** the card explains it needs more history

### Requirement: Shared with Claude
The release risk brief and status drafts SHALL receive the forecast as facts, worded as a likelihood.

#### Scenario: Risk brief
- **WHEN** a risk brief is generated
- **THEN** the facts sent include the team's weekly pace and the forecast dates
