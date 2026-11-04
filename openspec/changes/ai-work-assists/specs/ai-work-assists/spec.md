## ADDED Requirements

### Requirement: Claude suggests an assignee from people the viewer may pick
The system SHALL suggest at most three people for a task, chosen only from the people the viewer may assign to it, excluding the current assignee and read-only viewers. Nothing SHALL be assigned until the viewer accepts a suggestion.

#### Scenario: An answer names someone outside the list
- **WHEN** Claude returns an id that is not a candidate
- **THEN** that id is dropped from the result

#### Scenario: Only one person can take the task
- **WHEN** there is exactly one candidate
- **THEN** that person is returned without calling Claude

#### Scenario: A viewer who can't edit the task
- **WHEN** a user who may not update the task asks for suggestions
- **THEN** the request is refused with 403

#### Scenario: Private data stays out
- **WHEN** a suggestion is requested
- **THEN** the prompt contains no performance scores, lead feedback or leave type

### Requirement: Claude drafts tasksheet plans and results
The system SHALL draft a plan from the member's open tasks and previous day, and a result from that day's board activity, for anyone allowed to edit that row. Drafts SHALL NOT be saved until the row is saved.

#### Scenario: A plan item has no evidence
- **WHEN** a plan item has no matching board activity
- **THEN** it is returned as unconfirmed and left out of the draft text

#### Scenario: Nothing to draft from
- **WHEN** the member has no open tasks or activity to use
- **THEN** a clear message is returned and Claude is not called

#### Scenario: Someone else's row
- **WHEN** a developer asks for a draft for a teammate's row
- **THEN** the request is refused with 403

### Requirement: Claude drafts a release retrospective
The system SHALL let a release planner draft closing notes in the Mark complete form. Delivery numbers SHALL be calculated by the app, and attendees-only meeting notes SHALL NOT reach the prompt, because closing notes are read by everyone on the release.

#### Scenario: Numbers come from the app
- **WHEN** a retrospective is drafted
- **THEN** the done, late and reworked counts in the draft match the release's tasks

#### Scenario: A restricted note
- **WHEN** a release has an attendees-only note
- **THEN** that note is not sent to Claude

#### Scenario: A user who can't manage releases
- **WHEN** a developer requests a retrospective
- **THEN** the request is refused with 403

### Requirement: Admins control the new features
Each new feature SHALL have its own switch, model and effort at `/ai/usage`, and every call SHALL be logged.

#### Scenario: A feature is switched off
- **WHEN** an admin turns a feature off
- **THEN** its endpoint answers 409 and its button is hidden
