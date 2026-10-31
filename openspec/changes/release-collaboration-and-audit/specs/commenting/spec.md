## ADDED Requirements

### Requirement: Comment on releases and tasks
The system SHALL allow any authenticated user to post comments on a release and on a task. Each comment SHALL record its author and creation time and SHALL be displayed in chronological order under its subject.

#### Scenario: Comment on a release
- **WHEN** a user posts a non-empty comment on a release
- **THEN** the system stores it with the author and shows it in the release's comment thread

#### Scenario: Comment on a task
- **WHEN** a user posts a non-empty comment on a task
- **THEN** the system stores it with the author and shows it in the task's comment thread

#### Scenario: Reject an empty comment
- **WHEN** a user submits a comment with no body
- **THEN** the system rejects it with a validation error

### Requirement: Edit and delete own comments
The system SHALL allow a comment's author, or an admin, to edit or delete that comment. Other users SHALL NOT be able to edit or delete it.

#### Scenario: Author deletes their comment
- **WHEN** the author deletes their own comment
- **THEN** the system removes it from the thread

#### Scenario: Admin deletes any comment
- **WHEN** an admin deletes another user's comment
- **THEN** the system removes it from the thread

#### Scenario: Non-author, non-admin cannot modify
- **WHEN** a user who is neither the author nor an admin attempts to edit or delete a comment
- **THEN** the system denies the action
