## ADDED Requirements

### Requirement: One standup per team and working day
The system SHALL keep at most one generated standup meeting note per team and date, of the `standup` type, and SHALL update that note rather than create another when it runs again.

#### Scenario: First run
- **WHEN** the job runs on a working day for an active team with something to report
- **THEN** one Standup note is created for that team and date

#### Scenario: Running again
- **WHEN** the job runs again that day, whether or not a tasksheet changed
- **THEN** there is still one note, and its listing matches the current tasksheets

#### Scenario: Nothing changed
- **WHEN** the job runs and the tasksheets haven't changed
- **THEN** nothing is written

### Requirement: Built from the tasksheets
A standup SHALL list, for each person on that day's sheet, what they logged on the previous working day and what they have for the day itself, and SHALL name who is on leave and who hasn't filled in the day. It SHALL NOT contain lead feedback, points, ticket counts or standup attendance.

#### Scenario: After a weekend
- **WHEN** the standup for Saturday is generated and Friday is the weekend
- **THEN** "did" comes from Thursday's rows

#### Scenario: Full-day leave
- **WHEN** a member has casual or sick leave that day
- **THEN** they are named as on leave, not as having not filled it in, and aren't an attendee

#### Scenario: Half-day leave
- **WHEN** a member has half-day leave and a plan
- **THEN** they are named as on half-day leave and their plan is listed

#### Scenario: Not filled in
- **WHEN** a member on the day's sheet has no row and no leave
- **THEN** they are named as not having filled it in

#### Scenario: Restricted fields
- **WHEN** a row has lead feedback or points
- **THEN** they don't appear in the note, its API payload or its search text

### Requirement: Hand-written content is kept
The generator SHALL NOT change a note's body or title, or attendees someone edited by hand. Generated content SHALL be stored apart from the body.

#### Scenario: Notes added by hand
- **WHEN** someone writes notes on the standup and the job runs again with changed tasksheets
- **THEN** the notes are unchanged and the listing is updated

#### Scenario: Attendees edited by hand
- **WHEN** someone changes the attendee list and the job runs again
- **THEN** the list stays as they left it

#### Scenario: Editing with no notes
- **WHEN** someone saves a generated standup with an empty body
- **THEN** the save succeeds

#### Scenario: Identifying fields
- **WHEN** someone submits a different type, date, release or visibility for a generated standup
- **THEN** those values are ignored

### Requirement: A deleted standup stays deleted
The scheduled run SHALL NOT recreate a standup someone deleted. An explicit request SHALL be able to create it again.

#### Scenario: Scheduled run after a delete
- **WHEN** a lead deletes today's standup and the job runs again
- **THEN** no note is created

#### Scenario: Asked for again
- **WHEN** someone who may generate it asks for that standup explicitly
- **THEN** a new note is created

### Requirement: Runs on working days without anyone clicking
The system SHALL generate standups on a schedule through each working day, using `WorkingWeek`, and SHALL skip teams with nothing to report and archived teams. It SHALL be possible to run it by hand for a date and for one team.

#### Scenario: Day off
- **WHEN** the day is a weekend day or an alternate day off
- **THEN** no standup is created, by the schedule or by hand

#### Scenario: Nothing to report
- **WHEN** a team has no leave and no text on the day, and no result, comment or tickets on the previous working day
- **THEN** no standup is created for it

### Requirement: Visibility matches the team's tasksheet
A generated standup SHALL be visible to exactly those who can see that team's tasksheet.

#### Scenario: Member of the team
- **WHEN** a developer or QA currently on the team opens it
- **THEN** they can read it, even if they're on leave that day and not an attendee

#### Scenario: Another team
- **WHEN** a developer or QA not on the team asks for it, on the web or the API
- **THEN** it isn't listed and opening it is refused, even if they were added as an attendee

#### Scenario: Leads and viewers
- **WHEN** a lead or viewer opens it
- **THEN** they can read it

#### Scenario: Who may edit
- **WHEN** a lead or a current team member edits it
- **THEN** the edit is allowed, and a viewer's or another team's member's is refused

### Requirement: Useful with AI off
The listing SHALL NOT depend on AI. When a finished standup digest exists for the previous working day and its feature is available, the standup SHALL also show that digest's summary and blockers, and SHALL NOT show its follow-ups.

#### Scenario: AI off or unconfigured
- **WHEN** the `standup_digest` feature is off or no API key is set
- **THEN** the standup is generated in full, without an AI section, and no AI call is made

#### Scenario: Digest failed
- **WHEN** the digest for the previous working day failed or hasn't finished
- **THEN** the standup is generated in full, without an AI section

#### Scenario: Digest ready
- **WHEN** a finished digest exists
- **THEN** the standup shows its summary and blockers, labelled with the day they cover

### Requirement: Generating is quiet
Generating or refreshing a standup SHALL NOT send notifications or write to the activity log, and SHALL NOT count towards people analytics.

#### Scenario: Attendees from the team
- **WHEN** the job adds the team as attendees
- **THEN** nobody is notified

#### Scenario: Analytics
- **WHEN** people analytics counts meeting notes written and attended
- **THEN** generated standups aren't counted

### Requirement: Web and API parity
The API SHALL return the generated listing on a meeting note and offer the same generate-or-refresh action as the web, under the same permissions.

#### Scenario: Reading
- **WHEN** a client reads a generated standup
- **THEN** the response has a `standup` block, `author` is null and `body` holds only what was written by hand

#### Scenario: Generating
- **WHEN** a team member calls `POST /standups`
- **THEN** the standup is generated or refreshed and returned

#### Scenario: Not allowed
- **WHEN** a viewer, or a developer on another team, calls it
- **THEN** the response is 403
