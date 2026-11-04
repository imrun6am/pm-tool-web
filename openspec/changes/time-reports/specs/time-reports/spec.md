## ADDED Requirements

### Requirement: Hours by release, team and person
Leads SHALL be able to see logged hours grouped by release, team and person, filtered by period, team and release, defaulting to the last 30 days. Hours on deleted tasks SHALL NOT count.

#### Scenario: Filter by release
- **WHEN** a lead filters by a release
- **THEN** only hours on that release's tasks count

#### Scenario: Someone left
- **WHEN** a person who logged hours has been removed
- **THEN** their hours still show under their name

### Requirement: Estimate accuracy
The system SHALL compare logged and estimated hours on tasks finished in the period that have both, and list the tasks furthest from their estimate.

#### Scenario: Open task
- **WHEN** a task with an estimate and logged hours isn't finished
- **THEN** it isn't compared

### Requirement: Access
Only leads SHALL see this report. A team lead SHALL see only the teams they lead, and naming another team SHALL be refused. The figures SHALL NOT feed performance scores or KPI reports.

#### Scenario: Viewer
- **WHEN** a viewer opens the report or export
- **THEN** the request is refused

#### Scenario: Another team
- **WHEN** a team lead asks for a team they don't lead
- **THEN** the request is refused

### Requirement: Export
Leads SHALL be able to download every entry in the current selection as CSV. Text that a spreadsheet would run as a formula SHALL be neutralized.

#### Scenario: Formula in a title
- **WHEN** a task title starts with `=`
- **THEN** the exported cell starts with `'=`
