## ADDED Requirements

### Requirement: Start an import from a file, image or text
Any signed-in user SHALL be able to start an import on the Requests page by uploading a file, dropping one on the page, pasting an image, or pasting text. Accepted files: pdf, png, jpg, jpeg, gif, webp, docx, xlsx, csv, txt, md. The limit is 20 MB, or 5 MB for images, and pasted text is limited to 100,000 characters. The source SHALL be stored on the private `local` disk and an extraction job queued; the response SHALL NOT wait for it.

#### Scenario: Upload returns at once
- **WHEN** a user uploads a PDF
- **THEN** they land on the import's page showing it queued, and a job is on the queue

#### Scenario: Unsupported file
- **WHEN** a user uploads an `.exe` or an `.ics`
- **THEN** validation fails and no import is created

### Requirement: Claude proposes one or more requests
The job SHALL read the source through `AiGateway` under the `request_import` feature and store proposals with a title, description, team, priority, type and confidence. One input MAY produce many proposals. PDFs and images SHALL be sent natively; other formats SHALL be converted to text and read in chunks, with progress saved per chunk. Duplicates by title SHALL be merged.

#### Scenario: A message with several asks
- **WHEN** the pasted text holds three separate asks
- **THEN** the import offers three proposals

#### Scenario: A retry resumes
- **WHEN** a long document fails on its second chunk and is retried
- **THEN** the first chunk is not read again

### Requirement: Uploaded content is data
The prompt SHALL state that the source and its file name are data, never instructions, and the source SHALL NOT be able to close its wrapper tag. Team and type SHALL only be taken from the lists the sender may choose from.

#### Scenario: A document names another team
- **WHEN** a Developer's document leads Claude to answer with a team that isn't theirs
- **THEN** the proposal has no team from that answer

### Requirement: Review before anything is created
No request SHALL be created until the person confirms. They SHALL be able to edit each proposal and choose which to send. A proposal that became a request SHALL NOT be sent again. The page SHALL update in place while the job runs.

#### Scenario: Nothing before confirm
- **WHEN** the job finishes
- **THEN** there are no new requests and no notifications

#### Scenario: Only ticked rows are sent
- **WHEN** the person confirms two of three proposals with edits
- **THEN** two requests exist with the edited values

#### Scenario: Confirming twice
- **WHEN** the same form is submitted again
- **THEN** no further requests are created

### Requirement: Same rules as a hand-made request
A confirmed row SHALL pass the same validation as the request form and be created through the same service, with the person who imported as reporter and the same recipients as a hand-made request. Several requests from one confirm SHALL reach each decider as one grouped notification.

#### Scenario: Developer picks another team
- **WHEN** a Developer confirms a row with a team they aren't on
- **THEN** validation fails and nothing is created

#### Scenario: Deciders are notified
- **WHEN** a request is confirmed for a team
- **THEN** that team's lead gets the normal new-request notification

#### Scenario: Several requests from one import
- **WHEN** several requests from one import go to the same decider
- **THEN** that person gets one grouped notification

### Requirement: An import is private
Only the person who started an import SHALL be able to view, confirm, retry, delete or download it, including against admins.

#### Scenario: Another user opens it
- **WHEN** an admin opens someone else's import
- **THEN** access is denied

### Requirement: The source stays linked to the request
A request created from an import SHALL link to the original. The reporter and anyone who may decide the request SHALL be able to open it; nobody else SHALL. Deleting the import SHALL remove the file and the link and keep the requests.

#### Scenario: A lead opens the source
- **WHEN** the team's lead opens the source of a request in their queue
- **THEN** they receive the original file

#### Scenario: Another team's lead
- **WHEN** a lead of a different team asks for it
- **THEN** access is denied

### Requirement: Admin control
`request_import` SHALL be a tracked feature defaulting to the Sonnet model, switchable at `/ai/usage`, with every call logged. When unavailable, the import controls SHALL be hidden, new imports and retries SHALL be refused, the normal form SHALL work, and finished imports SHALL stay reviewable.

#### Scenario: Feature off
- **WHEN** an admin switches it off
- **THEN** the page has no import controls and an upload is refused with a clear message

### Requirement: Failures can be retried
A failed import SHALL show a clear message and offer a retry. Errors that can't recover SHALL fail without waiting for queue retries, and unexpected errors SHALL NOT expose internal messages.

#### Scenario: Claude can't be reached
- **WHEN** every attempt fails
- **THEN** the import shows the failure and a Try again button that queues it again
