## ADDED Requirements

### Requirement: Create and manage release plans
The system SHALL allow an admin to create, view, edit, and delete release plans. Each release plan SHALL reference exactly one project, be owned by exactly one team, belong to a quarter (Q1–Q4) of a year, have a name, and have an overall start date and end date where the end date is on or after the start date.

#### Scenario: Create a valid release plan
- **WHEN** an admin submits a release with a project, an owning team, a year, a quarter, a name, and a start date on or before the end date
- **THEN** the system creates the release and shows it on the dashboard

#### Scenario: Reject inverted date range
- **WHEN** an admin submits a release whose end date is before its start date
- **THEN** the system rejects it with a validation error

#### Scenario: Reject missing project or team
- **WHEN** an admin submits a release without a project or without an owning team
- **THEN** the system rejects it with a validation error

#### Scenario: Edit a release
- **WHEN** an admin changes a release's window, team, project, quarter, or name
- **THEN** the system saves the changes and re-evaluates overlap warnings

#### Scenario: Delete a release
- **WHEN** an admin deletes a release
- **THEN** the system removes the release and its phases and documents

### Requirement: Release phases with own dates
The system SHALL model four ordered phases for every release — Development, QA, Retest, Release — each with its own start and end date. Each phase's window SHALL fall within the release's overall window, and each phase's end date SHALL be on or after its start date. Phases SHALL follow the canonical order Development → QA → Retest → Release.

#### Scenario: Phases created with the release
- **WHEN** a release is created with dates for its four phases
- **THEN** the system stores each phase with its own start and end date in canonical order

#### Scenario: Phase outside release window is rejected
- **WHEN** an admin sets a phase start or end that falls outside the release's overall window
- **THEN** the system rejects it with a validation error

#### Scenario: Phase segments shown on the timeline
- **WHEN** a release is displayed on the dashboard
- **THEN** each phase is drawn as its own colored segment within the release bar

### Requirement: Warn on same-team booking overlap
The system SHALL detect when a release's overall window overlaps the overall window of any other release owned by the same team, and SHALL warn the admin on save while still allowing the release to be saved. Two windows overlap when one's start is on or before the other's end and its end is on or after the other's start. Overlaps SHALL NOT block saving.

#### Scenario: Overlapping team booking warns but saves
- **WHEN** an admin saves a release for a team whose window overlaps another release owned by that same team
- **THEN** the system saves the release and shows a warning naming the conflicting release(s) and the overlapping dates

#### Scenario: Non-overlapping booking saves without warning
- **WHEN** an admin saves a release for a team whose window does not overlap any other release owned by that team
- **THEN** the system saves the release with no overlap warning

#### Scenario: Different teams do not conflict
- **WHEN** two releases owned by different teams have overlapping windows
- **THEN** the system does not warn, because overlap is only evaluated within the same team
