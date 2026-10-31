## ADDED Requirements

### Requirement: Mark off-days on a release
The system SHALL allow an admin to mark specific calendar dates within a release's window as off-days (non-working), each with an optional reason, and to unmark them. A given date SHALL be markable at most once per release.

#### Scenario: Mark an off-day
- **WHEN** an admin marks a date within the release window as an off-day
- **THEN** the system records it (with any reason) and shows it on the release

#### Scenario: Reject an off-day outside the window
- **WHEN** an admin marks a date outside the release's window
- **THEN** the system rejects it with a validation error

#### Scenario: Reject a duplicate off-day
- **WHEN** an admin marks a date already marked off for that release
- **THEN** the system rejects the duplicate

#### Scenario: Unmark an off-day
- **WHEN** an admin removes an off-day
- **THEN** the system deletes it and it no longer counts against working days

### Requirement: Mark weekends helper
The system SHALL provide a helper that marks all Saturdays and Sundays within the release window as off-days in one action, skipping any dates already marked.

#### Scenario: Mark all weekends
- **WHEN** an admin uses the "mark weekends" helper
- **THEN** the system marks every Saturday and Sunday in the window that is not already an off-day

### Requirement: Working-day count and timeline display
The system SHALL compute and display a release's working days as its total window days minus its off-days, and SHALL indicate off-days on the release's timeline.

#### Scenario: Working days reflect off-days
- **WHEN** a release has off-days marked
- **THEN** the system shows working days = total days − off-days

#### Scenario: Off-days do not block scheduling
- **WHEN** a release has off-days that coincide with phase dates or another team booking
- **THEN** the system still allows saving; off-days are informational only
