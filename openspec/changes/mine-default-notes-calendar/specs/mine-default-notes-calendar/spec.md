## ADDED Requirements

### Requirement: Developers and QA open on their own items
Notes, Meetings and the Calendar SHALL show a Developer or QA user only their own items when opened with no filters. Other roles SHALL keep seeing everything they may see.

#### Scenario: Opening Notes
- **WHEN** a Developer or QA user opens Notes with no filters
- **THEN** only notes they wrote or that were sent specifically to them are listed, and My notes is selected

#### Scenario: Opening Meetings
- **WHEN** a Developer or QA user opens Meetings with no filters
- **THEN** only meeting notes they wrote or attended are listed, and My meetings is selected

#### Scenario: Opening the Calendar
- **WHEN** a Developer or QA user opens the Calendar with no `show` value
- **THEN** the Mine tab is selected and only events they created or attend are shown

#### Scenario: Other roles
- **WHEN** a lead or viewer opens any of these pages with no filters
- **THEN** everything they may see is listed

### Requirement: Everything allowed stays reachable
A Developer or QA user SHALL be able to switch to everything they may see, and visibility rules SHALL still apply.

#### Scenario: Turning My notes or My meetings off
- **WHEN** they turn the button off, with or without other filters
- **THEN** every item visible to them is listed and the choice is kept as other filters change

#### Scenario: Following a filtered link
- **WHEN** they open Notes or Meetings with a filter such as a release, author or date
- **THEN** the results are not narrowed to their own items

#### Scenario: Changing month on the Calendar
- **WHEN** they change month on the All or Shared tab
- **THEN** that tab stays selected

### Requirement: The API only narrows when asked
The API SHALL keep returning everything the caller may see by default, and `mine=1` SHALL narrow notes and meeting notes in the same way as the pages.

#### Scenario: Listing notes through the API
- **WHEN** a Developer lists notes with no `mine` value
- **THEN** every note visible to them is returned
