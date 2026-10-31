## ADDED Requirements

### Requirement: Record meeting-note attendees
The system SHALL allow the author of a meeting note to record its attendees — a set of users selected when creating or editing the note. The show page SHALL list the attendees and the list SHALL show an attendee count. When a note is created from a meeting-type event, its attendees SHALL pre-fill from that event's attendees (editable before saving).

#### Scenario: Select attendees
- **WHEN** a user creates or edits a meeting note and selects attendees
- **THEN** the system stores that set of attendees and shows them on the note

#### Scenario: Attendees pre-fill from an event
- **WHEN** a user uses "Write meeting note" on a meeting-type event that has attendees
- **THEN** the create form preselects the event's attendees

#### Scenario: No attendees selected
- **WHEN** a note is saved with no attendees
- **THEN** the note is stored with an empty attendee list

### Requirement: Meeting note visibility scope
The system SHALL give each meeting note a visibility of `everyone` (default) or `attendees`. A note with visibility `everyone` SHALL be viewable by every authenticated user (subject to existing role access). A note with visibility `attendees` SHALL be viewable only by its attendees, its author, and the leadership tier (admin, CTO, tech lead, team lead); all other users SHALL be unable to view it. This rule SHALL be enforced server-side on the Meeting Notes list, the release-details meeting-notes card, the event-details meeting-notes card, and the note's detail page.

#### Scenario: Everyone-visibility note is public
- **WHEN** any authenticated user opens a meeting note whose visibility is everyone
- **THEN** they can view it

#### Scenario: Attendee views an attendees-only note
- **WHEN** a user who is an attendee opens an attendees-only note
- **THEN** they can view it

#### Scenario: Author views their attendees-only note
- **WHEN** the author opens their attendees-only note without being listed as an attendee
- **THEN** they can view it

#### Scenario: Lead views any attendees-only note
- **WHEN** a leadership-tier user opens an attendees-only note they did not attend
- **THEN** they can view it

#### Scenario: Non-attendee is refused
- **WHEN** a non-lead user who is not an attendee or the author opens an attendees-only note
- **THEN** the system responds 403

#### Scenario: Attendees-only notes are hidden from listings
- **WHEN** a non-attendee, non-lead user views the Meeting Notes list, a release's meeting-notes card, or an event's meeting-notes card
- **THEN** attendees-only notes they may not view do not appear, while everyone-visibility notes do

#### Scenario: Invalid visibility rejected
- **WHEN** a note is saved with a visibility other than everyone or attendees
- **THEN** the system rejects it with a validation error
