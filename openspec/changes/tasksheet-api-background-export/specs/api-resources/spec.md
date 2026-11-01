## ADDED Requirements

### Requirement: Tasksheet PDF exports over the API are queued
The system SHALL NOT render a tasksheet PDF during an API request. Requesting an export through the API SHALL create an export record and queue its generation through the same service the web app uses, and SHALL respond `202 Accepted` with the export resource and a `Location` header pointing at that export. The export resource SHALL carry its status, progress percentage, rendered and total chunk counts, row count, whether it is merging, its error when failed, a download URL when ready, and the selection it was created from (team, member, date range and row filters).

#### Scenario: Creating an export
- **WHEN** a client posts a team, member, date range and row filters it may export
- **THEN** the response is 202 with the pending export and a `Location` header, and no PDF is rendered during the request

#### Scenario: Polling to completion
- **WHEN** a client polls an export it requested
- **THEN** it receives the export's current status and progress, and once ready a download URL that returns the PDF

#### Scenario: Too many running exports
- **WHEN** a client creates an export while it already has the maximum number running
- **THEN** the response is 422 and no export is created

### Requirement: The API exposes the same export actions as the web
The system SHALL provide API endpoints to list the requester's own exports newest first, report the status of several exports by id, show one export, download a ready export, retry a failed export, and delete an export. The list SHALL report whether the requester may start another export. The status endpoint SHALL omit exports the requester did not create.

#### Scenario: Status endpoint is scoped
- **WHEN** a client asks for the status of an export it did not request
- **THEN** that export is omitted from the response

#### Scenario: Retrying an export that has not failed
- **WHEN** a client retries an export that is pending, processing or ready
- **THEN** the response is 422 and the export is unchanged

#### Scenario: Downloading before the file exists
- **WHEN** a client downloads an export that is still running
- **THEN** the response is 404

### Requirement: API export access matches the web
Creating an export through the API SHALL follow the same rule as the web: a lead may export any team or member, and a non-lead only their own rows, with lead feedback included only when the requester is a lead. Showing, downloading, retrying and deleting an export SHALL be limited to its requester, with no override for leads or admins, and download SHALL re-check that the requester may still make that export.

#### Scenario: A member exports a team
- **WHEN** a non-lead posts an export of a team or of another member
- **THEN** the response is 403 and no export is created

#### Scenario: Someone else's export
- **WHEN** any user other than the requester, including an admin, shows, downloads, retries or deletes an export
- **THEN** the response is 403

### Requirement: The synchronous report endpoint is retired
`GET /api/v1/tasksheet/report` SHALL NOT render a PDF and SHALL NOT create an export. It SHALL respond `410 Gone` with a message naming the export endpoint and a `Link` header pointing at it.

#### Scenario: Calling the old report endpoint
- **WHEN** a client calls the old report endpoint with any parameters
- **THEN** the response is 410 with a message and a `Link` header naming the export endpoint, nothing is rendered, and no export is created
