## ADDED Requirements

### Requirement: Create a meeting note
The system SHALL allow any authenticated user to create a meeting note with a title, a meeting date, and a non-empty rich-text body. The note MAY be linked to an ongoing (not completed) release or left general (no release). The system SHALL record the note's author and SHALL sanitize the body HTML before storing it.

#### Scenario: Create a general meeting note
- **WHEN** a user creates a meeting note with a title, date, and body and no release selected
- **THEN** the system stores it as a general note, owned by the user, and redirects to the note with a success message

#### Scenario: Create a release-wise meeting note
- **WHEN** a user creates a meeting note and selects a release
- **THEN** the system stores the note linked to that release and it appears on that release's details page

#### Scenario: Create pre-linked from a release page
- **WHEN** a user opens the create form via the "New meeting note" action on a release's details page
- **THEN** the form has that release preselected

#### Scenario: Reject an empty body
- **WHEN** a user submits a meeting note whose body is empty or contains only empty markup
- **THEN** the system rejects it with a validation error

#### Scenario: Reject missing title or date
- **WHEN** a user submits a meeting note without a title or without a meeting date
- **THEN** the system rejects it with a validation error

#### Scenario: Sanitize body HTML
- **WHEN** a note body containing disallowed HTML (e.g., script tags) is submitted
- **THEN** the system strips the disallowed markup before storing the note

#### Scenario: Completed releases cannot be linked
- **WHEN** a user creates a meeting note
- **THEN** the release selector offers only ongoing releases, and submitting a completed release is rejected with a validation error

### Requirement: Create a meeting note from a meeting event
The system SHALL offer a "Write meeting note" action on calendar events of type meeting, which opens the meeting note create form pre-filled with the event's title, the event's start date as the meeting date, and the event's release (when set), and SHALL link the saved note to that event. The system SHALL NOT offer this action on events of other types.

#### Scenario: Create from a meeting event
- **WHEN** a user uses "Write meeting note" on a meeting-type event
- **THEN** the create form opens pre-filled with the event's title, start date, and release, and the saved note is linked to the event

#### Scenario: Non-meeting events have no note action
- **WHEN** a user views an event whose type is not meeting
- **THEN** no "Write meeting note" action is shown

### Requirement: Event details show meeting notes
The system SHALL display the meeting notes linked to an event on that event's details page, with links to each note. A note linked to both a release and an event SHALL be visible on both the release's and the event's details pages. The system SHALL NOT delete meeting notes when their linked event is deleted; such notes keep their release link (if any).

#### Scenario: Event with meeting notes
- **WHEN** a user views an event that has linked meeting notes
- **THEN** the details page lists those notes linking to each one

#### Scenario: Note related to both release and event
- **WHEN** a meeting note is linked to both a release and an event
- **THEN** the same note appears on the release's details page and on the event's details page

#### Scenario: Linked event is deleted
- **WHEN** an event with linked meeting notes is deleted
- **THEN** those notes remain, still visible on their release's page when release-linked

### Requirement: Meeting notes section
The system SHALL provide a Meeting Notes section, reachable from the main navigation, listing all meeting notes to every authenticated user, ordered by meeting date descending. The list SHALL support filtering by a specific release, by general (release-less) notes, and by a custom meeting-date range (from/to), with the release and date filters combinable.

#### Scenario: Browse all meeting notes
- **WHEN** a user opens the Meeting Notes section
- **THEN** the system lists all meeting notes with title, meeting date, author, release (if any), and a body excerpt, newest meeting date first

#### Scenario: Filter by release
- **WHEN** a user filters the list by a release
- **THEN** only notes linked to that release are shown

#### Scenario: Filter general notes
- **WHEN** a user filters the list by general
- **THEN** only notes with no release are shown

#### Scenario: Filter by date range
- **WHEN** a user filters the list with a from and/or to date
- **THEN** only notes whose meeting date falls within the given span are shown, and a reversed span is tolerated

#### Scenario: Combine release and date filters
- **WHEN** a user filters by a release and a date range together
- **THEN** only that release's notes within the span are shown

### Requirement: View a meeting note
The system SHALL provide a detail view for a meeting note rendering its full sanitized body, title, meeting date, author, and linked release (if any), visible to every authenticated user.

#### Scenario: View a note
- **WHEN** a user opens a meeting note
- **THEN** the system shows its full content, and a link to the linked release when one exists

### Requirement: Release details show meeting notes
The system SHALL display a Meeting Notes card on a release's details page listing that release's most recent meeting notes with links to each note, a link to the release-filtered Meeting Notes list, and an action to create a note pre-linked to the release.

#### Scenario: Release with meeting notes
- **WHEN** a user views a release that has linked meeting notes
- **THEN** the details page shows the most recent notes with title, date, and author, linking to each note

#### Scenario: Release without meeting notes
- **WHEN** a user views a release with no linked meeting notes
- **THEN** the Meeting Notes card shows an empty state and the create action

### Requirement: Manage meeting notes
The system SHALL allow a meeting note's author to edit its title, meeting date, release link, and body, and SHALL allow its author or an admin to delete it. Other users SHALL NOT be able to edit or delete it.

#### Scenario: Author edits their note
- **WHEN** the author updates a note's fields
- **THEN** the system saves the changes and confirms

#### Scenario: Note linked to a since-completed release stays editable
- **WHEN** the author edits a note whose linked release has been completed after the note was created
- **THEN** the existing link remains selectable and saving without changing it succeeds

#### Scenario: Non-author cannot edit
- **WHEN** a user who is not the author attempts to edit a note
- **THEN** the system forbids the action

#### Scenario: Author deletes their note
- **WHEN** the author deletes a note
- **THEN** the note is removed and the user is redirected to the list with a confirmation

#### Scenario: Admin deletes any note
- **WHEN** an admin deletes another user's meeting note
- **THEN** the note is removed

#### Scenario: Non-author non-admin cannot delete
- **WHEN** a user who is neither the author nor an admin attempts to delete a note
- **THEN** the system forbids the action

### Requirement: Release deletion preserves meeting notes
The system SHALL NOT delete meeting notes when their linked release is deleted; such notes SHALL become general notes.

#### Scenario: Linked release is deleted
- **WHEN** a release with linked meeting notes is deleted
- **THEN** those notes remain and appear as general notes
