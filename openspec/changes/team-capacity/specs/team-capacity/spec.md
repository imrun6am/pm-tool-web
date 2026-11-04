## ADDED Requirements

### Requirement: Available time per week
The system SHALL show each active team member's available time for six weeks from the current one, leaving out configured weekend days, the team's release off-days, recorded leave and days already past. A half-day leave SHALL count as half a day.

#### Scenario: Leave and off-days
- **WHEN** a member has a sick day and a half day this week
- **THEN** their available time drops by a day and a half

#### Scenario: Midweek
- **WHEN** it's Thursday
- **THEN** this week counts only the days left

### Requirement: Load per week
Load SHALL be what's left of each open task's estimate, in the week it's due. Overdue work SHALL count this week. Tasks with no due date or one past the window SHALL go in a separate column. Tasks with no estimate SHALL be counted separately. A week SHALL be flagged when load exceeds available time.

#### Scenario: Time already logged
- **WHEN** a task estimated at 2 hours has 1 hour logged
- **THEN** it adds 1 hour of load

#### Scenario: No estimate
- **WHEN** a task has no estimate
- **THEN** it adds no load, and shows as not estimated

### Requirement: Leads only
Capacity SHALL be available to leads on the web and through the API. A team lead SHALL only reach teams they lead. Others SHALL get a 403.

#### Scenario: Developer
- **WHEN** a developer, QA or viewer requests capacity
- **THEN** the system answers 403
