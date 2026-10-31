## ADDED Requirements

### Requirement: Performance section access
The system SHALL restrict the entire Performance section — evaluation and analytics — to leads (admin, CTO, team lead). The system SHALL block developers, QA, and viewers from every Performance route server-side and SHALL NOT show the Performance navigation entry to them.

#### Scenario: Lead reaches the section
- **WHEN** an admin, CTO, or team lead opens the Performance section
- **THEN** it loads

#### Scenario: Developer is blocked
- **WHEN** a developer or QA user requests any Performance route directly
- **THEN** the system denies access and the navigation never offered the entry

### Requirement: Rating scale
The system SHALL score every competency on an integer 1–5 scale with fixed anchor labels: 1 Needs Improvement, 2 Below Expectations, 3 Meets Expectations, 4 Exceeds Expectations, 5 Outstanding. The system SHALL reject any score outside 1–5.

#### Scenario: Valid rating accepted
- **WHEN** a lead rates a competency 4
- **THEN** the score is stored with the anchor "Exceeds Expectations"

#### Scenario: Out-of-range rating rejected
- **WHEN** a rating of 0 or 6 is submitted
- **THEN** the system rejects it with a validation error

### Requirement: Evaluation by the assigned lead
The system SHALL allow a team's assigned team lead, or any admin or CTO, to record scores for the active developer and QA members of that team. The system SHALL prevent a team lead from scoring members of a team they do not lead, and SHALL prevent any non-lead from scoring at all. The evaluator of a score SHALL be recorded.

#### Scenario: Assigned lead scores their member
- **WHEN** the assigned team lead rates a developer on their team
- **THEN** the score is stored with the lead recorded as evaluator

#### Scenario: Lead cannot score another team
- **WHEN** a team lead attempts to score a member of a team they do not lead
- **THEN** the system forbids it

#### Scenario: Admin scores any team
- **WHEN** an admin or CTO rates a member of any team
- **THEN** the score is stored

#### Scenario: Non-member target rejected
- **WHEN** a score targets a user who is not an active developer or QA member of the team and has no existing score for it
- **THEN** the system rejects it

### Requirement: Daily and weekly cadence periods
The system SHALL record each score against a period determined by the competency's cadence: a single calendar date for daily competencies, and an ISO week (Monday–Sunday) for weekly competencies. The system SHALL store the period type and the period's start and end dates on the score.

#### Scenario: Daily score keyed to a date
- **WHEN** a daily competency is scored for a given day
- **THEN** the score's period is that single date

#### Scenario: Weekly score keyed to a week
- **WHEN** a weekly competency is scored within a week
- **THEN** the score's period is that week's Monday-to-Sunday span, regardless of which day in the week the rating was entered

#### Scenario: Future period rejected
- **WHEN** a score is submitted for a date after today or a week that has not started
- **THEN** the system rejects it

### Requirement: Upsert per member, competency, and period
The system SHALL store at most one score per (team, member, competency, period). Re-rating the same competency for the same member and period SHALL update the existing score rather than create a duplicate.

#### Scenario: First rating creates a score
- **WHEN** a lead rates a member's competency for a period the first time
- **THEN** a score is created

#### Scenario: Re-rating updates in place
- **WHEN** the lead changes that rating for the same member, competency, and period
- **THEN** the existing score is updated and no duplicate is created

### Requirement: Optional private note per score
The system SHALL allow the evaluator to attach an optional private note to a score. The system SHALL treat notes as lead-only and SHALL NOT expose them to developers, QA, or viewers.

#### Scenario: Note saved with score
- **WHEN** a lead adds a note while rating a competency
- **THEN** the note is stored alongside the score and shown only to leads

#### Scenario: Note never leaks to non-leads
- **WHEN** any Performance response is produced for a context that a non-lead could reach
- **THEN** it contains no note content

### Requirement: Members on leave are not penalized
The system SHALL treat a member marked absent (casual or sick leave) on the tasksheet for a period as not-expected-to-be-evaluated for that period, excluding them from evaluation-coverage expectations. The system SHALL still permit a score to be recorded for such a member if the lead chooses.

#### Scenario: Leave excuses the coverage gap
- **WHEN** a member is on leave for a day and has no score for a daily competency that day
- **THEN** the missing score does not count against evaluation coverage for that period

#### Scenario: Scoring on leave still allowed
- **WHEN** a lead records a score for a member who was on leave
- **THEN** the score is stored

### Requirement: Former members and deactivated users
The system SHALL preserve scores recorded for a member who later leaves the team or is deactivated, keeping them visible in that member's and the team's history via the stored team association. The system SHALL stop offering new evaluation rows for a member once they are no longer an active developer or QA member of the team.

#### Scenario: History survives departure
- **WHEN** a member with recorded scores leaves the team
- **THEN** their past scores remain visible in analytics for the periods they were scored

#### Scenario: No new rows after departure
- **WHEN** a member is no longer an active member of the team
- **THEN** the evaluation grid no longer lists them for new periods

### Requirement: Performance data excluded from the shared activity feed
The system SHALL NOT record performance scores or notes — neither values nor old→new diffs — in the shared activity feed that all authenticated users can read.

#### Scenario: Scoring does not appear in the public feed
- **WHEN** a lead records or changes a performance score
- **THEN** no entry describing the score or its note appears in the shared activity feed
