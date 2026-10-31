## ADDED Requirements

### Requirement: Meeting note type
The system SHALL record a type on every meeting note, selected on create and edit from the server-defined type list. When no type is chosen the system SHALL apply the default type. Meeting notes that existed before this capability SHALL carry the default type, and the default type's label SHALL remain the one previously shown for all meeting notes.

#### Scenario: Create a note with a type
- **WHEN** a user creates a meeting note and selects a type
- **THEN** the system stores the note with that type and shows the type on the note

#### Scenario: Create a note without choosing a type
- **WHEN** a user creates a meeting note without changing the type selection
- **THEN** the system stores the note with the default type

#### Scenario: Change a note's type
- **WHEN** the author edits a meeting note and selects a different type
- **THEN** the system saves the new type

#### Scenario: Reject an unknown type
- **WHEN** a meeting note is submitted with a type that is not in the server-defined list
- **THEN** the system rejects it with a validation error

#### Scenario: Existing notes keep their meaning
- **WHEN** a meeting note created before types existed is viewed
- **THEN** it shows the default type and its presentation is otherwise unchanged

#### Scenario: Note created from a calendar event
- **WHEN** a user writes a meeting note from a meeting-type calendar event
- **THEN** the note's type defaults to the default meeting-note type, independent of the event's own calendar type

### Requirement: Server-defined meeting note types
The system SHALL define the available meeting note types in one server-side list of value-and-label pairs, so that types can be added, relabeled, or retired without a schema change. The system SHALL publish this list to API clients through the shared metadata endpoint, and clients SHALL render their type pickers and type filters from it. A note whose stored type is no longer in the list SHALL still display and remain readable.

#### Scenario: A new type becomes available
- **WHEN** a new type is added to the server-side list
- **THEN** it appears in the create/edit type selector, in the type filter, and in the metadata endpoint's payload without any schema change

#### Scenario: Clients read the type list from metadata
- **WHEN** an API client requests the metadata endpoint
- **THEN** the response includes the meeting note types as value/label entries in the server's order

#### Scenario: A retired type still renders
- **WHEN** a note's stored type is no longer present in the server-side list
- **THEN** the note still opens and displays a readable label for that type

### Requirement: Filter meeting notes by type
The Meeting Notes list SHALL support filtering by a single meeting note type, and SHALL display each note's type as a distinguishable badge in the list, on the note's detail view, and on the release-details and event-details meeting-note cards.

#### Scenario: Filter by type
- **WHEN** a user filters the list by a type
- **THEN** only notes of that type are shown

#### Scenario: Type shown on listings and detail
- **WHEN** a user views the Meeting Notes list, a note's detail view, or a release's or event's meeting-notes card
- **THEN** each note shows its type

### Requirement: Filter meeting notes by person
The Meeting Notes list SHALL support filtering by the note's author and, independently, by a recorded attendee. Both filters SHALL be combinable with each other and with every other filter. Person filters SHALL NOT reveal notes the viewer is not permitted to see.

#### Scenario: Filter by author
- **WHEN** a user filters the list by a person as author
- **THEN** only notes written by that person are shown

#### Scenario: Filter by attendee
- **WHEN** a user filters the list by a person as attendee
- **THEN** only notes recording that person as an attendee are shown

#### Scenario: Combine author and attendee
- **WHEN** a user filters by one person as author and another as attendee
- **THEN** only notes written by the first and attended by the second are shown

#### Scenario: Person filter respects visibility
- **WHEN** a user filters by a person who attended an attendees-only note that the filtering user may not view
- **THEN** that note is not shown

### Requirement: Search meeting notes
The Meeting Notes list SHALL support a free-text search that matches against a note's title and its body content, combinable with every other filter. Search SHALL be restricted to the notes the viewer is permitted to see, and wildcard characters in the search term SHALL be treated as literal text.

#### Scenario: Search matches a title
- **WHEN** a user searches for a term appearing in a note's title
- **THEN** that note is shown

#### Scenario: Search matches body content
- **WHEN** a user searches for a term appearing only in a note's body
- **THEN** that note is shown

#### Scenario: Search respects visibility
- **WHEN** a user searches for a term appearing in an attendees-only note they may not view
- **THEN** that note is not shown

#### Scenario: Wildcards are literal
- **WHEN** a user searches for a term containing a wildcard character
- **THEN** the character is matched literally rather than broadening the results

#### Scenario: Search with no matches
- **WHEN** a search matches no notes
- **THEN** the list shows an empty state that keeps the active filters visible and offers a way to clear them

### Requirement: Combined filtering and pagination
The Meeting Notes list SHALL apply type, release, author, attendee, meeting-date range, and search filters together, in any combination, and SHALL provide a single action that clears all of them at once. The list SHALL be paginated, and the active filters SHALL be preserved when moving between pages. This requirement supersedes the filtering clause of the "Meeting notes section" requirement, which covered only the release and date-range filters.

#### Scenario: Combine several filters
- **WHEN** a user filters by type, release, attendee, and a date range together
- **THEN** only the notes satisfying every filter are shown

#### Scenario: Clear all filters
- **WHEN** a user clears the filters
- **THEN** the full list of notes the user may see is shown again, ordered by meeting date descending

#### Scenario: Page through filtered results
- **WHEN** a user moves to another page of a filtered list
- **THEN** the same filters remain applied and the ordering is unchanged

#### Scenario: API accepts the same filters
- **WHEN** an API client lists meeting notes with any combination of these filters
- **THEN** it receives the same paginated set of notes the web list would show that user
