## ADDED Requirements

### Requirement: Member dashboard for developers and QA
The system SHALL show developer/QA users a personal dashboard at the dashboard route in place of the release timeline, containing: their assigned open tasks, today's tasksheet status, and their upcoming meetings. Other roles SHALL continue to see the existing planning dashboard.

#### Scenario: Developer sees the member dashboard
- **WHEN** a developer or QA user opens the dashboard
- **THEN** they see their assigned tasks, today's tasksheet status, and upcoming meetings — not the release timeline

#### Scenario: Other roles keep the planning dashboard
- **WHEN** an admin, CTO, team lead, or viewer opens the dashboard
- **THEN** the release timeline dashboard renders as before

### Requirement: Assigned tasks section
The member dashboard SHALL list the user's assigned tasks that are not done, ordered by due date (soonest first, undated last), each linking to its task page and showing status and due date, with overdue tasks flagged.

#### Scenario: Open assigned tasks listed
- **WHEN** a developer with open assigned tasks views their dashboard
- **THEN** those tasks appear with status and due date, and done tasks do not

#### Scenario: No assigned tasks
- **WHEN** the user has no open assigned tasks
- **THEN** the section shows an empty state

### Requirement: Today's tasksheet status
The member dashboard SHALL show, for each team the user belongs to, today's tasksheet row status: fully filled (every task field has a value), partially filled (some but not all task fields), on leave, or not filled yet. Every status SHALL link to that team's sheet for today.

#### Scenario: Unfilled tasksheet prompts
- **WHEN** the user has not filled today's row for a team
- **THEN** the dashboard shows "not filled" for that team, linking to that team's sheet for today

#### Scenario: Partially filled until every field has a value
- **WHEN** the user's row for today has some task fields filled but not all
- **THEN** the dashboard shows it as partially filled, linking to the sheet to complete it

#### Scenario: Fully filled row
- **WHEN** every task field of today's row has a value
- **THEN** the dashboard shows it as filled

#### Scenario: On-leave row reflected
- **WHEN** today's row has a leave type
- **THEN** the dashboard shows the leave label, also linking to the sheet

### Requirement: Upcoming meetings section
The member dashboard SHALL list upcoming meeting-type events within the next 14 days where the user is an attendee or the creator, soonest first, each linking to the event.

#### Scenario: Upcoming meeting listed
- **WHEN** the user is an attendee of a meeting starting within 14 days
- **THEN** it appears on their dashboard linking to the event

#### Scenario: Unrelated meetings excluded
- **WHEN** a meeting has neither the user as attendee nor as creator
- **THEN** it does not appear on their dashboard
