## ADDED Requirements

### Requirement: Upload a file to import events
Any signed-in user SHALL be able to upload one file from the calendar's import page. Accepted formats: pdf, png, jpg, jpeg, gif, webp, ics, docx, xlsx, csv, txt or md. The limit is 20 MB, or 5 MB for images. The file SHALL be stored on the private `local` disk, an import record SHALL be created with status `pending`, and an extraction job SHALL be queued. The response SHALL NOT wait for extraction.

#### Scenario: Upload returns immediately
- **WHEN** a user uploads a PDF schedule
- **THEN** they are redirected to the import's page showing "Queued", and the extraction job is on the queue

#### Scenario: Unsupported file
- **WHEN** a user uploads a `.exe`
- **THEN** validation fails and no import is created

### Requirement: Background extraction
The job SHALL set the import to `processing`, extract proposed events, store them and set the import to `needs_review`.
- `.ics` files SHALL be parsed without calling Claude.
- PDFs and images SHALL be sent to Claude natively.
- Other formats SHALL be converted to text and split into chunks, with one Claude call per chunk. Results SHALL be merged and de-duplicated.

The prompt SHALL include today's date and the uploader's timezone, and SHALL treat document content as data. Each proposal SHALL have:
- title;
- type (one of the event types);
- start date and time;
- end date and time;
- all-day flag;
- description;
- location;
- attendees;
- release hint;
- confidence.

#### Scenario: ICS without Claude
- **WHEN** an `.ics` with two VEVENTs is processed
- **THEN** two proposals are stored and no Claude call is made

#### Scenario: Large text is chunked
- **WHEN** a text document is longer than one chunk
- **THEN** the extractor is called once per chunk and the proposals from all chunks are stored

#### Scenario: Failure is recorded
- **WHEN** the API key is missing, or extraction fails after retries
- **THEN** the import is `failed` with the error message, and the user can retry or delete it

### Requirement: Review and confirm
Once an import is `needs_review`, the uploader SHALL see an editable list of proposals. Each row can be included or excluded, and its fields edited. Attendees SHALL be matched to existing users, with unmatched names flagged, and release hints SHALL be matched to releases. Confirming SHALL create the included rows as events through `EventService`, attributed to the uploader, and mark the import `completed`. No event SHALL be created before confirmation.

#### Scenario: Confirm selected events
- **WHEN** the uploader confirms two of three proposals
- **THEN** exactly two events exist, with the edited values, and the import is completed

#### Scenario: Live status
- **WHEN** the import is pending or processing
- **THEN** the page polls its status and shows the review list as soon as it is ready

### Requirement: Import privacy
Only the uploader and admins SHALL see or delete an import. Only the uploader SHALL confirm or retry it. The history SHALL list the user's own imports.

#### Scenario: Another user
- **WHEN** a different non-admin user opens or confirms someone's import
- **THEN** they receive 403
