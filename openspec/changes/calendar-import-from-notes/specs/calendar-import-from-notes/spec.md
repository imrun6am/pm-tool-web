## ADDED Requirements

### Requirement: Find calendar events from a note
A meeting note's page and each daily note in the Notes feed SHALL offer a "Find calendar events" action when the `calendar_import` feature is available. The action SHALL create an event import owned by the person who started it and queue the existing extraction job. No event SHALL be created until that person confirms on the existing review page.

#### Scenario: Starting from a meeting note
- **WHEN** a reader of a meeting note with text chooses "Find calendar events"
- **THEN** they are sent to the import's page, the extraction job is queued, and no event exists yet

#### Scenario: Feature unavailable
- **WHEN** the `calendar_import` feature is switched off or no API key is set
- **THEN** the action is not shown, and a direct request creates no import and queues no job

#### Scenario: Confirming
- **WHEN** the starter confirms some of the proposed events
- **THEN** only those events are created

#### Scenario: Repeat click
- **WHEN** the same person starts the same note again while its import is still running
- **THEN** they are sent to the running import and no second import is created

### Requirement: Only a reader of the note may extract from it
The server SHALL refuse to start an import from a note the user cannot read, using the note's own visibility rules. An admin SHALL NOT open an import made from a note that admin cannot read. A retry SHALL be refused once the starter can no longer read the note.

#### Scenario: Attendees-only meeting note
- **WHEN** a user who is not an attendee, the author or a lead posts to start an import from an attendees-only note
- **THEN** the request is forbidden and no import is created

#### Scenario: Private daily note
- **WHEN** anyone other than its author, including an admin, posts to start an import from a private note
- **THEN** the request is forbidden and no import is created

#### Scenario: Admin and a private note's import
- **WHEN** an admin opens an import its author made from a private note
- **THEN** the request is forbidden

#### Scenario: Access revoked before a retry
- **WHEN** the starter of a failed note import can no longer read the note
- **THEN** a retry is forbidden

### Requirement: The note's saved text is read
The saved rich text SHALL be converted to plain text before extraction, keeping link targets. The copy SHALL be removed from the private disk once extraction succeeds.

#### Scenario: Rich text
- **WHEN** an import runs for a note containing formatting, a list and a link
- **THEN** the extractor receives text with no markup and with the link's URL

#### Scenario: Snapshot removed
- **WHEN** extraction succeeds
- **THEN** the stored copy of the note's text no longer exists

### Requirement: The note's date resolves relative dates
The extractor context SHALL include the meeting date or daily note date, and the prompt SHALL resolve relative dates against it rather than today. Imports from files and pasted text SHALL NOT have this date.

#### Scenario: Note written earlier
- **WHEN** an import runs for a meeting note dated 2026-10-27
- **THEN** the extractor context gives that date as the written date, alongside today's date

### Requirement: A note with no text is refused clearly
Starting an import from a note with no readable text SHALL show a clear message, create no import and make no Claude call.

#### Scenario: Blank meeting note
- **WHEN** a meeting note's body holds only whitespace or empty markup
- **THEN** the person sees that there is no text to read, no import exists and no job is queued

### Requirement: Imports name the note they came from
The import history and the import's page SHALL name the source note: "Meeting note: <title>" or "Daily note, <date>". They SHALL NOT show a generated file name. The label SHALL remain after the note is deleted.

#### Scenario: History row
- **WHEN** a user views their imports after starting one from a meeting note titled "Sprint sync"
- **THEN** the row reads "Meeting note: Sprint sync"

### Requirement: File and paste imports respect the feature switch
When the `calendar_import` feature is not available, uploads and pasted text that need Claude SHALL be refused with a clear message and SHALL create no import. `.ics` files and pasted iCalendar data SHALL still import. An import that reaches the point of calling Claude while the feature is switched off SHALL fail with a message saying so, without calling Claude.

#### Scenario: Uploading a PDF while switched off
- **WHEN** a user uploads a PDF while the feature is switched off or no API key is set
- **THEN** validation fails, no import exists and no job is queued

#### Scenario: Calendar data while switched off
- **WHEN** a user uploads an `.ics` file or pastes iCalendar data while the feature is switched off
- **THEN** its events are read without Claude and are ready to review

#### Scenario: Import queued before the switch went off
- **WHEN** a queued import that needs Claude runs after the feature is switched off
- **THEN** the import fails, says the feature is off, and Claude is not called

#### Scenario: Retry after switching back on
- **WHEN** that failed import is retried after the feature is switched on again
- **THEN** it is read as normal
