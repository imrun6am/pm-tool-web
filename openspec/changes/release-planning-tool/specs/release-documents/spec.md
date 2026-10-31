## ADDED Requirements

### Requirement: Attach documents to a release
The system SHALL allow an admin to upload one or more documents to a release. Each stored document SHALL retain its original file name, size, and content type, and SHALL be associated with exactly one release and record who uploaded it. Uploads SHALL be limited to a configured maximum size and to a safe set of file types.

#### Scenario: Upload a document
- **WHEN** an admin uploads a file of an allowed type within the size limit to a release
- **THEN** the system stores the file and lists it under that release with its original name and size

#### Scenario: Reject oversized or disallowed file
- **WHEN** an admin uploads a file that exceeds the size limit or is of a disallowed type
- **THEN** the system rejects the upload with a validation error and stores nothing

### Requirement: List, download, and delete documents
The system SHALL list a release's documents, allow any authenticated user to download them, and allow an admin to delete them. Deleting a document SHALL remove both its database record and its stored file.

#### Scenario: Download a document
- **WHEN** an authenticated user clicks a document on a release
- **THEN** the system streams the original file for download

#### Scenario: Delete a document
- **WHEN** an admin deletes a document
- **THEN** the system removes the file from storage and the record from the database, and it no longer appears in the release's document list

#### Scenario: Documents removed with their release
- **WHEN** a release is deleted
- **THEN** the system deletes that release's document records and their stored files
