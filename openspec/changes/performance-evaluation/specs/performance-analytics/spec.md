## ADDED Requirements

### Requirement: Member scorecard
The system SHALL provide a per-member scorecard, for leads only, showing a blended headline performance score for a selected period computed as the weighted average of the member's competency ratings (each competency contributing by its weight), expressed both out of 5 and as a percentage. The scorecard SHALL show a per-category breakdown, each competency's latest score with its short trend, and the private note attached to each score.

#### Scenario: Headline score is a weighted average
- **WHEN** a member has ratings across several competencies with different weights for the period
- **THEN** the headline score is their weight-weighted average, shown out of 5 and as a percentage

#### Scenario: Only rated competencies count
- **WHEN** some applicable competencies have no rating for the period
- **THEN** the headline score averages only the competencies that were rated, without treating missing ratings as zero

#### Scenario: Category breakdown shown
- **WHEN** the scorecard is viewed
- **THEN** each category's average rating is shown for the member

### Requirement: Member objective panels
The system SHALL show, beside the rating-based score and clearly separated from it, objective panels drawn from existing data for the member and period: tasksheet work-points trend, ticket output (count and points), on-time tasksheet fill rate, attendance and leave, and board task metrics (assigned, completed, and rework rate from tasks returned to Recheck). These panels SHALL NOT alter the headline rating score.

#### Scenario: Objective panels reflect tasksheet data
- **WHEN** the member has tasksheet entries in the period
- **THEN** the panels show their work points, tickets, on-time fill rate, and attendance for that period

#### Scenario: Objective panels are separate from the score
- **WHEN** objective metrics are strong or weak
- **THEN** the headline rating score is unchanged, since it derives only from lead ratings

### Requirement: Team overview
The system SHALL provide a per-team overview, for leads only, showing the team's average performance score for a period, a member leaderboard ranked by score, per-category team averages, and the team's tasksheet output for the period. A team lead SHALL see only teams they are the assigned lead of; admins and CTOs SHALL see all teams.

#### Scenario: Team average and leaderboard
- **WHEN** a lead opens a team's overview for a period
- **THEN** the team average score and a member ranking by score are shown

#### Scenario: Lead scoping to owned teams
- **WHEN** a team lead opens the overview
- **THEN** only teams they are the assigned lead of are available to them

#### Scenario: Admin sees all teams
- **WHEN** an admin opens the overview
- **THEN** every team is available

### Requirement: Evaluation coverage
The system SHALL show, per team and period, an evaluation-coverage measure: the proportion of expected member–competency ratings that have been recorded, where members on leave for the period are excluded from the expectation. The system SHALL surface which members still need ratings.

#### Scenario: Coverage reflects completeness
- **WHEN** a lead has rated some but not all applicable competencies for the team's members
- **THEN** the coverage measure reflects the recorded fraction and lists who is still unrated

#### Scenario: Leave lowers the expectation, not the coverage
- **WHEN** a member was on leave for the whole period
- **THEN** their unrated competencies are excluded from the expected total so coverage is not unfairly reduced

### Requirement: Trends over time
The system SHALL show performance trends over a series of periods: a member's overall score across recent periods, and a team's average score across recent periods.

#### Scenario: Member trend across weeks
- **WHEN** a member has scores across several weeks
- **THEN** the scorecard shows their overall score trend across those weeks

#### Scenario: Team trend across weeks
- **WHEN** a team has scores across several weeks
- **THEN** the overview shows the team average trend across those weeks

### Requirement: Needs-attention surfacing
The system SHALL surface members who need attention for a team and period, defined as those whose overall score is below a defined threshold or whose score has declined versus the prior comparable period.

#### Scenario: Low scorer flagged
- **WHEN** a member's overall score for the period is below the attention threshold
- **THEN** they are surfaced in the needs-attention list

#### Scenario: Declining scorer flagged
- **WHEN** a member's overall score dropped from the prior comparable period
- **THEN** they are surfaced as declining

### Requirement: Empty and edge states
The system SHALL render clear empty states rather than errors when data is absent: a member with no ratings for a period, a team with no developer or QA members, and a period with no scores. The system SHALL never divide by zero when computing averages, coverage, or percentages.

#### Scenario: Member not yet evaluated
- **WHEN** a member has no ratings for the selected period
- **THEN** the scorecard shows a "not yet evaluated" state instead of a computed score

#### Scenario: Team with no scorable members
- **WHEN** a team has no active developer or QA members
- **THEN** the overview shows an empty state

#### Scenario: No division by zero
- **WHEN** averages, coverage, or percentages are computed with no underlying scores
- **THEN** the system shows a neutral empty value rather than erroring
