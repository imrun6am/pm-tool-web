## ADDED Requirements

### Requirement: Add a daily note
The system SHALL allow any authenticated user to add a note for a specific day with a non-empty body and a visibility of `private`, `shared`, or `specific`. A `specific` note records the users it is shared with. The note SHALL record its author and date.

#### Scenario: Add a private note
- **WHEN** a user adds a note for a day with visibility private
- **THEN** the system stores it for that date, owned by the user, visible only to them

#### Scenario: Add a shared note
- **WHEN** a user adds a note for a day with visibility shared
- **THEN** the system stores it and it becomes visible to all authenticated users

#### Scenario: Add a note shared with specific people
- **WHEN** a user adds a note with visibility specific and selects users to share with
- **THEN** the system stores it, visible only to the author and the selected recipients

#### Scenario: Recipients ignored unless specific
- **WHEN** a note is saved with recipients but a visibility of private or shared
- **THEN** no recipients are stored

#### Scenario: Reject an empty note
- **WHEN** a user submits a note with an empty body
- **THEN** the system rejects it with a validation error

#### Scenario: Reject an invalid visibility
- **WHEN** a visibility other than private, shared, or specific is submitted
- **THEN** the system rejects it with a validation error

### Requirement: Notes list
The system SHALL present all notes the current user may see — their own notes, every shared note, and specific notes shared with them — in a single list ordered by date descending (newest first), paginated. It SHALL NOT show other users' private notes, nor specific notes the user was not shared with. The list SHALL be filterable by a single day and by a from/to date range (a reversed range is tolerated).

#### Scenario: Default list shows all visible notes newest first
- **WHEN** a user opens the Notes page without a filter
- **THEN** the system shows all notes visible to them, newest first, paginated

#### Scenario: Own, shared, and shared-with notes are visible
- **WHEN** a user opens the Notes page
- **THEN** the system shows their own notes, every shared note, and specific notes shared with them

#### Scenario: Private and un-shared specific notes are hidden from others
- **WHEN** another user has a private note, or a specific note not shared with this user
- **THEN** the system does not show it to this user

#### Scenario: Filter by day
- **WHEN** a user filters the list by a single day
- **THEN** the system shows only that day's visible notes

#### Scenario: Filter by date range
- **WHEN** a user filters the list by a from/to range
- **THEN** the system shows only visible notes within the span, tolerating a reversed range

### Requirement: Manage own notes
The system SHALL allow a note's author to edit its body and visibility and to delete it, and SHALL prevent other users from editing or deleting it.

#### Scenario: Author edits their note
- **WHEN** the author changes their note's body or visibility
- **THEN** the system saves the change

#### Scenario: Author deletes their note
- **WHEN** the author deletes their note
- **THEN** the system removes it

#### Scenario: Non-author cannot manage
- **WHEN** a user who is not the author attempts to edit or delete a note
- **THEN** the system denies the action
