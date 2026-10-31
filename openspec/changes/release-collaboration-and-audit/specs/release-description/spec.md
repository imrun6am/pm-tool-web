## ADDED Requirements

### Requirement: Release description
The system SHALL allow an admin to store an optional free-text description on a release, editable via the release form and shown on the release page. The description MAY be empty.

#### Scenario: Add a description
- **WHEN** an admin saves a release with description text
- **THEN** the system stores it and displays it on the release page

#### Scenario: Description is optional
- **WHEN** an admin saves a release with no description
- **THEN** the system saves the release without error and shows no description block

#### Scenario: Description is escaped
- **WHEN** a description contains HTML-special characters
- **THEN** the system renders them as text, not markup
