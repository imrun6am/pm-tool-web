## ADDED Requirements

### Requirement: Developers and QA see only their own teams' people
A Developer or QA user SHALL see, in every picker and filter, only themselves, their current teammates and their teams' assigned leads. Choosing anyone else SHALL be rejected with a validation error. People already on a record SHALL remain allowed.

#### Scenario: Inviting someone from another team
- **WHEN** a developer adds a person from another team as an event attendee, meeting-note attendee or note recipient
- **THEN** the system rejects it with a validation error

### Requirement: Developers and QA reach only their own teams' releases
A Developer or QA user SHALL be refused (403) on every route of a release that doesn't belong to one of their teams, on the web and API: detail, conflicts, phases, off-days, documents, document download and upload, tasks and comments. Release pickers SHALL list only their teams' releases, and linking another team's release SHALL be rejected. Leads and viewers SHALL be unaffected.

#### Scenario: Opening another team's release
- **WHEN** a developer opens, comments on, or downloads a document from another team's release
- **THEN** the system refuses with 403

### Requirement: Developers and QA read only their own teams' tasksheets
The tasksheet SHALL offer a Developer or QA user only their own teams. A request for another team SHALL show their own team instead.

#### Scenario: Asking for another team's sheet
- **WHEN** a developer requests the tasksheet of a team they don't belong to
- **THEN** the system shows their own team's sheet

### Requirement: Developers and QA see only events and meeting notes shared with their teams
A Developer or QA user SHALL see only these events and everyone-visibility meeting notes:
- those they created or attend;
- those linked to one of their teams' releases;
- unlinked ones with no attendees, created by a teammate or by a lead or viewer.

Opening any other event or meeting note SHALL be refused (403).

#### Scenario: Another team's meeting
- **WHEN** a developer opens the calendar or the meeting notes
- **THEN** another team's release events and notes, and unlinked meetings they don't attend, are not listed

#### Scenario: Organisation-wide entry
- **WHEN** a lead creates an unlinked event or everyone-note with no attendees
- **THEN** every developer sees it

### Requirement: Shared notes come only from teammates
A Developer or QA user SHALL see "shared" daily notes only from their teammates.

#### Scenario: Another team's shared note
- **WHEN** a developer lists notes, or opens a shared note from someone outside their teams
- **THEN** the note isn't listed, and opening it returns 404
