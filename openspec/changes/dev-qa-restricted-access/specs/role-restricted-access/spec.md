## ADDED Requirements

### Requirement: Developers and QA reach only their work surfaces
The system SHALL allow users with the developer or QA role to access: the dashboard, the board (including task pages and board task creation), the calendar and events, daily notes, meeting notes, the tasksheet, a release's **detail page** (a restricted, no-edit view — see "Restricted release detail view"), release-scoped collaboration (view/create tasks, view/post comments, view/add links, download and upload documents), and their own profile. The system SHALL respond 403 to developer/QA requests for the **releases list**, projects, teams, and the activity feed — enforced server-side.

#### Scenario: Restricted sections return 403
- **WHEN** a developer or QA user requests the releases list, the projects list, the teams list, or the activity feed
- **THEN** the system responds 403

#### Scenario: Allowed sections remain reachable
- **WHEN** a developer or QA user opens the board, calendar, notes, meetings, tasksheet, a task detail page, or a release detail page
- **THEN** the page loads normally

#### Scenario: Other roles are unaffected
- **WHEN** an admin, CTO, team lead, or viewer requests the releases list, projects, teams, or activity feed
- **THEN** access behaves exactly as before this change

### Requirement: Navigation reflects the allowed set
The system SHALL show developer/QA users only the navigation entries for their allowed sections (Dashboard, Board, Calendar, Notes, Meetings, Tasksheet) and SHALL NOT show entries for restricted sections.

#### Scenario: Limited nav for developers and QA
- **WHEN** a developer or QA user views any page
- **THEN** the navigation shows only the allowed sections

#### Scenario: Full nav for other roles
- **WHEN** a viewer or lead views any page
- **THEN** the navigation is unchanged from before this change

### Requirement: Restricted release detail view
The system SHALL present a release's detail page to developer/QA users as a read-mostly view: the phase timeline, double-booking alert, the Details metadata card (status, project, team, quarter, window, working days), off-days, release-completion controls, activity history, and all release-editing controls (edit release, team-member editing) SHALL be hidden, while the tasks, comments, links, meeting notes, documents, and read-only member list remain. Developer/QA users MAY create tasks, post comments, add links, and upload documents on the release; they SHALL NOT edit release settings or team membership. Document upload SHALL be available to all non-viewer roles; pure viewers SHALL NOT upload.

#### Scenario: Timeline, details, and edit controls hidden for developer/QA
- **WHEN** a developer or QA user opens a release detail page
- **THEN** no timeline, Details metadata card, off-days, history, edit-release, or completion controls are shown

#### Scenario: Developer/QA can collaborate on a release
- **WHEN** a developer or QA user adds a task, posts a comment, adds a link, or uploads a document on a release
- **THEN** the action succeeds

#### Scenario: Viewers cannot upload documents
- **WHEN** a viewer attempts to upload a document to a release
- **THEN** the system refuses

#### Scenario: Release names link to the detail page
- **WHEN** any authenticated user views a board card, task page, meeting note, or event that references a release
- **THEN** the release name links to the release detail page
