## ADDED Requirements

### Requirement: Timeline of all releases
The system SHALL present a dashboard that shows every release plan as a horizontal bar on a shared time axis, positioned by its start and end dates, so overlapping and sequential releases are visible at a glance. Each release bar SHALL display its project, name, owning team, and window, and SHALL render its four phases as distinct colored segments.

#### Scenario: Releases rendered on a time axis
- **WHEN** a user opens the dashboard
- **THEN** the system draws each release as a bar on a shared date axis with its phase segments and labels

#### Scenario: Empty state
- **WHEN** no releases match the current view
- **THEN** the system shows an empty-state message instead of a blank timeline

### Requirement: Group releases by team
The system SHALL be able to group the timeline by team so that all releases owned by a team appear on that team's row(s), making it easy to see when a team is continuously busy and when it is next free.

#### Scenario: Team grouping shows a team's continuous workload
- **WHEN** a user views the dashboard grouped by team
- **THEN** each team's releases appear together in chronological order on the time axis

### Requirement: Highlight team booking overlaps
The system SHALL visually flag on the dashboard any two releases owned by the same team whose windows overlap, so double-booked periods are obvious without opening each release.

#### Scenario: Overlap is visually flagged
- **WHEN** two releases owned by the same team overlap in time
- **THEN** the system highlights the overlapping bars (or the overlapping region) and shows a conflict indicator

#### Scenario: No overlap, no flag
- **WHEN** a team's releases do not overlap
- **THEN** the system shows them without any conflict indicator

### Requirement: Filter the timeline
The system SHALL allow filtering the dashboard by year, quarter, project, and team, and SHALL default to the current year and the current quarter. An explicit "all quarters" choice SHALL show the whole year. Filters SHALL be combinable.

#### Scenario: Filter by quarter and team
- **WHEN** a user selects a specific year, quarter, and team
- **THEN** the system shows only releases matching all selected filters

#### Scenario: Default view is the current quarter
- **WHEN** a user opens the dashboard without choosing filters
- **THEN** the system shows releases for the current quarter of the current year

#### Scenario: All quarters on request
- **WHEN** a user explicitly selects "All quarters"
- **THEN** the system shows the whole current year
