## ADDED Requirements

### Requirement: Posting a status update
Leads SHALL be able to post a status of on track, at risk or off track with a note of up to 2,000 characters. Others SHALL NOT.

#### Scenario: Viewer
- **WHEN** a viewer posts an update
- **THEN** the request is refused and no form is shown to them

#### Scenario: Empty note
- **WHEN** a lead posts with no note
- **THEN** it's rejected with a message

### Requirement: History
Everyone who can see a release SHALL be able to read its updates, newest first, and earlier ones SHALL be kept. The web page and `GET /releases/{id}/status-updates` SHALL follow the same rules.

#### Scenario: Developer on another team
- **WHEN** a developer outside the release's team reads the API
- **THEN** the request is refused

### Requirement: Telling people when things get worse
Moving a release into at risk or off track SHALL notify its team's lead and active org-level leads, except the author. Repeating the same status or posting on track SHALL NOT notify anyone. Opening the notification SHALL go to the release.

#### Scenario: Newly at risk
- **WHEN** a lead moves a release from on track to at risk
- **THEN** the team lead and CTO get an inbox notification

#### Scenario: Still at risk
- **WHEN** a lead posts at risk again
- **THEN** nobody is notified

### Requirement: Claude draft
Leads SHALL be able to ask Claude to draft the status and note from the release's computed facts, forecast and last update. A draft SHALL NOT be saved until a lead posts it. The button SHALL appear only when the feature is available.

#### Scenario: Draft
- **WHEN** a lead asks for a draft
- **THEN** the form fills in and no update exists yet

#### Scenario: Not a lead
- **WHEN** a developer asks for a draft
- **THEN** the request is refused and Claude isn't called
