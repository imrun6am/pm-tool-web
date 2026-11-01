## ADDED Requirements

### Requirement: Tasksheet PDF exports are generated in the background
The system SHALL NOT render a tasksheet PDF during the web request that asks for it. Requesting an export SHALL create an export record in the `pending` state and queue its generation, and the request SHALL return immediately. The record SHALL move to `processing` when generation starts and end as `ready` or `failed`.

#### Scenario: Requesting an export
- **WHEN** a user submits the export form with a team, member, date range and row filters they may export
- **THEN** an export record is created as pending with those filters, generation is queued, and the user is taken to their exports list without waiting for a PDF

#### Scenario: Export finishes
- **WHEN** every part of the export has been rendered and merged
- **THEN** the export is ready and its file is stored on the private disk

### Requirement: Exports are generated in bounded chunks
The system SHALL split an export into chunks of consecutive days holding at most a configured number of rows, splitting a single day that exceeds that number by member. Each chunk SHALL be rendered by its own queued job into a partial PDF, and the parts SHALL be merged into one document in day-then-member order. No job SHALL load more than one chunk of rows.

#### Scenario: A large range
- **WHEN** an export covers more rows than one chunk holds
- **THEN** it is rendered as several parts, each within the row budget, and the merged file contains every row exactly once

#### Scenario: A day larger than a chunk
- **WHEN** a single day in the export holds more rows than one chunk
- **THEN** that day is split across several parts by member, and each part starts with that day's heading

#### Scenario: Empty selection
- **WHEN** no rows match the export's filters
- **THEN** the export still completes with a one-page document saying there are no rows

### Requirement: Export progress and failure are tracked
The system SHALL record how many chunks are rendered out of the total and expose a percentage that reaches 100 only when the file is ready. A generation step that fails after its retries SHALL mark the export failed, record the error message, and delete every partial file. A failed export SHALL be retryable by its requester.

#### Scenario: Failure cleans up
- **WHEN** a chunk or the merge fails permanently
- **THEN** the export is failed with the error recorded and no partial files remain

#### Scenario: Retry
- **WHEN** the requester retries a failed export
- **THEN** it returns to pending with its progress reset and is generated again from scratch

### Requirement: Exports list with live status
The system SHALL provide a page listing the current user's own exports, newest first, each showing its filters (team, member, date range, row filters), who requested it and when, a status badge, a progress bar while it runs, Download when ready, and Retry and Delete when failed. While any listed export is pending or processing, the page SHALL refresh statuses from a JSON endpoint without a page reload.

#### Scenario: Live progress
- **WHEN** an export in the list is processing
- **THEN** its progress bar and badge update as chunks finish, and Download appears when it becomes ready, without the user reloading

#### Scenario: Status endpoint is scoped
- **WHEN** a user asks the status endpoint about an export they did not request
- **THEN** that export is omitted from the response

### Requirement: Export access matches the report rules
Creating an export SHALL follow the existing report rule: a lead may export any team or member, and a non-lead only their own rows. Lead feedback SHALL be included only when the requester is a lead. Viewing status, downloading, retrying and deleting an export SHALL be limited to its requester; leads and admins SHALL NOT see or download other users' exports. Download SHALL also require that the requester may still make that export and, for a file containing feedback, is still a lead.

#### Scenario: A member exports a team
- **WHEN** a non-lead requests an export of a team or of another member
- **THEN** the request is refused and no export is created

#### Scenario: Someone else's export
- **WHEN** any user other than the requester, including an admin, tries to download, retry or delete an export
- **THEN** the request is refused

#### Scenario: Old report URL
- **WHEN** a user opens the old report URL with filters they may export
- **THEN** they are redirected to the exports page with the export form prefilled, and nothing is rendered or created

### Requirement: Exports expire
The system SHALL delete exports, and their files, 14 days after they were requested.

#### Scenario: Pruning
- **WHEN** the scheduled prune runs
- **THEN** exports older than 14 days and their files are removed
