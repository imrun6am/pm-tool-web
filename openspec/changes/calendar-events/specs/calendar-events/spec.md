## ADDED Requirements

### Requirement: Create events
The system SHALL allow any authenticated user to create an event with a title, a type, and a start date/time. An event MAY also have an end date/time (on or after the start), an all-day flag, a location, a description, a linked release, and attendees (users).

#### Scenario: Create a meeting
- **WHEN** a user submits an event with a title, type, and start
- **THEN** the system creates it and shows it on the calendar

#### Scenario: Reject an event without a title or start
- **WHEN** a user submits an event missing a title or a start
- **THEN** the system rejects it with a validation error

#### Scenario: Reject an end before the start
- **WHEN** a user submits an event whose end is before its start
- **THEN** the system rejects it with a validation error

#### Scenario: Add attendees
- **WHEN** a user selects attendees for an event
- **THEN** the system stores those users as attendees and shows them on the event

### Requirement: Month calendar view
The system SHALL present a month view showing each event on the days it covers, with navigation to the previous and next months and an indicator for the current day. Events spanning multiple days SHALL appear on each day within the visible range.

#### Scenario: Events shown on their days
- **WHEN** a user opens the calendar for a month
- **THEN** the system renders a grid of that month's days with each event on the day(s) it falls on

#### Scenario: Navigate months
- **WHEN** a user moves to the previous or next month
- **THEN** the system shows that month's events

#### Scenario: Multi-day event spans days
- **WHEN** an event runs across several days within the visible month
- **THEN** the system shows it on each of those days

### Requirement: View, edit, and delete events
The system SHALL let any authenticated user view an event's details and attendees, and SHALL allow only the event's creator or an Admin to edit or delete it.

#### Scenario: Anyone can view
- **WHEN** an authenticated user opens an event
- **THEN** the system shows its details and attendees

#### Scenario: Creator edits their event
- **WHEN** the creator edits their event
- **THEN** the system saves the changes

#### Scenario: Admin can delete any event
- **WHEN** an Admin deletes an event they did not create
- **THEN** the system removes it

#### Scenario: Others cannot edit or delete
- **WHEN** a user who is neither the creator nor an Admin attempts to edit or delete an event
- **THEN** the system denies the action
