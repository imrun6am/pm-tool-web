## ADDED Requirements

### Requirement: One definition of the weekend
The system SHALL take its weekend days from `PLANNING_WEEKEND_DAYS`, defaulting to Friday, and every working-day decision SHALL use it.

#### Scenario: Default
- **WHEN** nothing is configured
- **THEN** Friday is the weekend, and Saturday and Sunday are working days

#### Scenario: Two days
- **WHEN** the setting names Friday and Saturday
- **THEN** both are the weekend

#### Scenario: Every day named
- **WHEN** the setting names all seven days
- **THEN** it counts as no weekend, and nothing hangs looking for a working day

### Requirement: Alternate days off
The system SHALL treat the date in `PLANNING_ALTERNATE_OFF_DAY`, and every 14 days either side of it, as a day off, wherever it decides whether a day is a working day. An empty or unreadable setting SHALL mean no such days. The default SHALL be 2026-08-01, so every second Saturday is off.

#### Scenario: A Saturday off
- **WHEN** it's Saturday, November 7, 2026
- **THEN** it's a day off, no tasksheet is expected and streaks aren't broken

#### Scenario: A worked Saturday
- **WHEN** it's Saturday, November 14, 2026
- **THEN** it's a working day

#### Scenario: Before the date
- **WHEN** figures cover Saturday, July 18, 2026
- **THEN** it counts as a day off, and July 25 as worked

### Requirement: Tasksheet and attendance figures follow it
Expected tasksheet days, streaks, compliance and standup attendance SHALL count working days only.

#### Scenario: Friday
- **WHEN** someone has no tasksheet row on a Friday
- **THEN** it isn't a miss and doesn't break their streak

#### Scenario: Sunday
- **WHEN** someone has no tasksheet row on a past Sunday
- **THEN** it counts as a miss

### Requirement: Marking weekends
"Mark weekends" SHALL mark the configured days, on the release page, the release form and the API.

#### Scenario: Two-week window
- **WHEN** a lead marks weekends on a window with two Fridays and one Saturday off
- **THEN** those three days become off-days

### Requirement: Claude and scheduled work follow it
The schedule assistant SHALL receive the weekend and count working days from it. The standup digest SHALL run on working days only, for the previous working day.

#### Scenario: After the weekend
- **WHEN** the digest runs on Saturday morning
- **THEN** it covers Thursday

#### Scenario: On the weekend
- **WHEN** it's Friday morning
- **THEN** the digest doesn't run

#### Scenario: After two days off
- **WHEN** the digest runs on the Sunday after a Saturday off
- **THEN** it covers Thursday

### Requirement: Stored records are left alone
Changing the weekend SHALL NOT change stored off-days or tasksheet rows.

#### Scenario: Saturday off-day
- **WHEN** a release already has a Saturday off-day
- **THEN** it stays
