## ADDED Requirements

### Requirement: Record standup attendance
The system SHALL record, for a member on a given day, whether they attended that day's standup. The value SHALL have three states: attended, did not attend, and not yet marked. Not yet marked SHALL be the state of every row for which no one has recorded attendance, including all rows that existed before this capability.

#### Scenario: Mark a member as having attended
- **WHEN** a lead marks a member as having attended that day's standup
- **THEN** the row records attendance and the sheet shows it

#### Scenario: Mark a member as absent from standup
- **WHEN** a lead marks a member as not having attended
- **THEN** the row records the absence, distinctly from a row nobody has marked

#### Scenario: Unmarked by default
- **WHEN** a day's sheet is opened and no attendance has been recorded
- **THEN** every row reads as not yet marked, rather than as absent

#### Scenario: Marking a member who has no row yet
- **WHEN** a lead marks attendance for a member who has not filled anything in that day
- **THEN** the system creates that member's row for the day carrying only the attendance, and the row still counts as unfilled

### Requirement: Only leads may verify standup attendance
The system SHALL allow only users in the leadership tier to set or change standup attendance. A member's own save of their tasksheet row SHALL NOT be able to set, change, or clear it, whether or not the field is submitted.

#### Scenario: A lead sets attendance
- **WHEN** a lead sets attendance on any member's row
- **THEN** the change is saved

#### Scenario: A member cannot set their own attendance
- **WHEN** a member attempts to set attendance on their own row
- **THEN** the system refuses the change

#### Scenario: A member's row save cannot forge attendance
- **WHEN** a member saves their tasksheet row with an attendance value included in the submission
- **THEN** the row's recorded attendance is unchanged

#### Scenario: A member cannot clear a recorded attendance
- **WHEN** a lead has recorded attendance and the member then saves their row
- **THEN** the recorded attendance survives the save

### Requirement: Verify attendance directly from the sheet
The system SHALL allow a lead to set a member's standup attendance directly from the tasksheet list, without opening or submitting that member's row. Doing so SHALL NOT alter any task content on the row.

#### Scenario: Toggle from the list
- **WHEN** a lead toggles the attendance control on a row in the list
- **THEN** the attendance is saved and the sheet reflects it

#### Scenario: Toggling preserves task content
- **WHEN** a lead toggles attendance on a row that already holds task content
- **THEN** that content is unchanged

### Requirement: Attendance does not apply to full-day leave
The system SHALL NOT record standup attendance for a row marked as a full-day leave, and SHALL present the leave state in place of the attendance control. A half-day leave row SHALL keep its attendance control.

#### Scenario: Full-day leave row
- **WHEN** a lead views a row marked casual or sick leave
- **THEN** no attendance control is offered for it

#### Scenario: Attempting to mark a full-day leave row
- **WHEN** attendance is submitted for a full-day leave row
- **THEN** the system refuses it

#### Scenario: A row becomes a full-day leave after being marked
- **WHEN** a row with recorded attendance is changed to a full-day leave
- **THEN** the recorded attendance is cleared along with the task fields

#### Scenario: Half-day leave keeps attendance
- **WHEN** a row is marked half-day leave
- **THEN** its attendance control remains available

### Requirement: Filter the daily team sheet
The daily team sheet SHALL support filtering its rows by standup attendance (attended / did not attend / not yet marked), by fill status (complete / partially filled / empty), by leave status, and by member. The filters SHALL combine, and SHALL be clearable in one action. Filtering SHALL NOT change which people belong on that day's sheet — only which of them are shown.

#### Scenario: Filter by attendance
- **WHEN** a lead filters the sheet by "did not attend"
- **THEN** only rows recorded as absent from standup are listed

#### Scenario: Filter by unmarked attendance
- **WHEN** a lead filters the sheet by "not yet marked"
- **THEN** only rows whose attendance nobody has recorded are listed, including members with no row yet

#### Scenario: Filter by fill status
- **WHEN** a lead filters the sheet by empty rows
- **THEN** only members who have filled nothing in for that day are listed

#### Scenario: Filter by leave and by member
- **WHEN** a lead filters by leave status, or by a specific member
- **THEN** only the matching rows are listed

#### Scenario: Filters combine and clear
- **WHEN** a lead applies several filters together and then clears them
- **THEN** the intersection is listed while applied, and the full sheet returns when cleared

### Requirement: Filter a member's tasksheet history
The per-user tasksheet history SHALL support filtering by standup attendance, fill status, and leave status, combinable with its existing team and date-range filters. A given filter SHALL mean the same thing on this page as it does on the daily sheet.

#### Scenario: Filter a history by attendance
- **WHEN** a viewer filters a member's history by "did not attend"
- **THEN** only that member's days recorded as absent from standup are listed

#### Scenario: Combine with the existing filters
- **WHEN** a viewer filters by fill status together with a team and a date range
- **THEN** only the days matching all of them are listed

### Requirement: Download a tasksheet PDF report
The system SHALL produce a downloadable PDF report of tasksheet rows for a single day, for a date range, or for one member over a range. The report SHALL reflect the filters in effect when it was requested.

#### Scenario: Report for a single day
- **WHEN** a lead downloads the report for a team on one day
- **THEN** a PDF of that day's rows is returned

#### Scenario: Report for a date range
- **WHEN** a lead downloads the report for a team across a date range
- **THEN** a PDF covering every day in the range is returned

#### Scenario: Report for one member
- **WHEN** a report is downloaded for a single member over a range
- **THEN** the PDF covers only that member's rows

#### Scenario: Report honours the active filters
- **WHEN** a report is downloaded while a filter is applied
- **THEN** the PDF contains the same rows the filtered list shows

### Requirement: Report access follows the tasksheet's existing rules
The system SHALL allow only leads to download a team-wide report. A member SHALL be able to download a report of their own rows only. The system SHALL NOT include the lead-only feedback column in any report obtainable by a user who is not a lead, including a member's report of their own rows.

#### Scenario: Lead downloads a team report
- **WHEN** a lead requests a team-wide report
- **THEN** the report is produced

#### Scenario: Member cannot download a team report
- **WHEN** a member requests a team-wide report
- **THEN** the system refuses it

#### Scenario: Member downloads their own report
- **WHEN** a member requests a report of their own rows
- **THEN** the report is produced

#### Scenario: Feedback is withheld from a member's own report
- **WHEN** a member downloads a report of their own rows and those rows carry lead feedback
- **THEN** the feedback does not appear in the report

#### Scenario: Feedback appears for a lead
- **WHEN** a lead downloads a report of rows carrying feedback
- **THEN** the feedback appears
