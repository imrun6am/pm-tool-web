## ADDED Requirements

### Requirement: Server-side enforcement
The system SHALL enforce every API restriction on the server. The system SHALL NOT rely on a client omitting an endpoint, hiding a button, or filtering a payload to keep a role out of data or an action. Any endpoint a role may not use SHALL be refused even when called directly with a valid token.

#### Scenario: Direct call is still refused
- **WHEN** a developer's client calls a lead-only endpoint directly with a valid token
- **THEN** the system responds 403 regardless of what the client's interface offers

#### Scenario: Client-side filtering is not trusted
- **WHEN** a caller requests a collection containing records they may not see
- **THEN** the system excludes those records from the response rather than returning them

### Requirement: API role parity with the web application
The system SHALL grant, for each of the seven roles, the same access over the API that the role has in the web application, by reusing the application's existing policies, gates, capability methods, and role checks rather than reimplementing them. A change to a permission rule SHALL take effect in both the web app and the API.

#### Scenario: Parity for a restricted section
- **WHEN** a role cannot reach a section on the web
- **THEN** the corresponding API endpoints refuse that role too

#### Scenario: Rule change propagates
- **WHEN** a capability rule is changed in the domain model
- **THEN** both the web app and the API reflect the change without separate edits

### Requirement: Leadership tier permissions
The system SHALL treat admin, CTO, tech lead, and team lead as one leadership tier over the API: all four SHALL be able to manage projects and teams, plan releases, manage team membership, upload and delete release documents, manage off-days, manage user accounts, and reach the performance section.

#### Scenario: Any lead role plans a release
- **WHEN** an admin, CTO, tech lead, or team lead creates or edits a release
- **THEN** the system permits it

#### Scenario: Non-lead cannot plan
- **WHEN** a developer, QA, or viewer attempts to create a release
- **THEN** the system responds 403

### Requirement: Limited-access roles are confined to collaboration
The system SHALL block developers and QA from the planning surfaces over the API — the projects list and detail, the teams list and detail, the releases list, and the activity feed — while permitting them the release detail endpoint, tasks, the board, the calendar, daily notes, meeting notes, quick links, and the tasksheet. The system SHALL serve these roles the personal member dashboard rather than the planning timeline.

#### Scenario: Developer blocked from planning lists
- **WHEN** a developer requests the projects, teams, releases-list, or activity endpoints
- **THEN** the system responds 403

#### Scenario: Developer reaches a release detail
- **WHEN** a developer requests a single release they work on
- **THEN** the release is returned in its restricted, no-edit form

#### Scenario: Developer dashboard is personal
- **WHEN** a developer requests the dashboard endpoint
- **THEN** the personal member dashboard payload is returned rather than the planning timeline

### Requirement: Viewer role is read-only for collaboration writes it may not make
The system SHALL prevent a viewer from uploading release documents, consistent with the web application, while allowing viewers the read access and the collaboration writes the web application already grants them.

#### Scenario: Viewer cannot upload a document
- **WHEN** a viewer posts a document to a release
- **THEN** the system responds 403

### Requirement: Ownership rules on collaboration records
The system SHALL apply the existing ownership policies over the API: a comment SHALL be editable and deletable only by its author or a lead; a calendar event only by its creator or a lead; a daily note and a quick link only by their author, not by any lead; a meeting note SHALL be editable only by its author and deletable by its author or a lead.

#### Scenario: Author edits their comment
- **WHEN** the author of a comment updates it
- **THEN** the update succeeds

#### Scenario: Lead cannot edit someone's personal note
- **WHEN** a lead attempts to update another user's daily note or quick link
- **THEN** the system responds 403

#### Scenario: Lead deletes a meeting note
- **WHEN** a lead deletes a meeting note written by someone else
- **THEN** the deletion succeeds

### Requirement: Per-record visibility scoping in collections
The system SHALL scope every collection to what the caller may see: daily notes SHALL return shared notes, the caller's own notes, and "specific" notes the caller is a recipient of; meeting notes SHALL exclude attendees-only notes the caller neither wrote nor attended unless the caller is a lead; quick links SHALL return the caller's own links plus shared links, and SHALL return only the caller's own links for limited-access roles.

#### Scenario: Private note of another user is invisible
- **WHEN** a user lists daily notes
- **THEN** another user's private note is not in the response

#### Scenario: Attendees-only meeting note is filtered
- **WHEN** a non-lead user who did not attend lists meeting notes
- **THEN** attendees-only notes they did not attend are absent

#### Scenario: Limited role sees only its own quick links
- **WHEN** a developer lists quick links
- **THEN** only their own links are returned, with no shared links from others

### Requirement: Performance data confined to leads and scoped by team
The system SHALL restrict every performance endpoint to the leadership tier. The system SHALL further scope a team lead to the teams they are the assigned lead of, while admin, CTO, and tech lead reach every team. The system SHALL restrict competency-catalog writes to admin, CTO, and tech lead. Performance scores and their private notes SHALL never appear in any payload served to a non-lead.

#### Scenario: Developer blocked from performance
- **WHEN** a developer requests any performance endpoint
- **THEN** the system responds 403

#### Scenario: Team lead scoped to their own team
- **WHEN** a team lead requests performance data for a team they do not lead
- **THEN** the system responds 403

#### Scenario: Team lead cannot manage the catalog
- **WHEN** a team lead attempts to create or edit a competency
- **THEN** the system responds 403

### Requirement: Tasksheet access and lead-only feedback
The system SHALL let any authenticated user read a team's tasksheet, SHALL let a member save only their own row and only while they remain on the team, and SHALL let a lead save any row. The lead feedback column SHALL be writable only by a lead and SHALL be omitted from payloads served to non-leads. A member's own tasksheet history SHALL be readable by that member and by leads only.

#### Scenario: Member saves their own row
- **WHEN** a current team member saves their own tasksheet row
- **THEN** the save succeeds

#### Scenario: Member cannot save another member's row
- **WHEN** a member attempts to save a teammate's row
- **THEN** the system responds 403

#### Scenario: Non-lead cannot write feedback
- **WHEN** a member submits a row containing the feedback field
- **THEN** the feedback is not persisted and the stored row keeps any existing lead feedback

#### Scenario: Another member's history is private
- **WHEN** a non-lead requests another user's tasksheet history
- **THEN** the system responds 403
