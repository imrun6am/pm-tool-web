## ADDED Requirements

### Requirement: Activity analysis with drill-down
The activity page SHALL analyse a 7, 30 or 90-day window against the previous window of equal length:
- volume;
- people;
- deletions;
- breakdowns by event, record type, team and release;
- the most active people;
- a weekday × hour rhythm;
- task workflow moves and time in status.

It SHALL keep the feed, and chart elements SHALL link to the feed narrowed to that slice.

#### Scenario: Previous-window comparison
- **WHEN** three changes happened in the last 30 days and one in the 30 before
- **THEN** the events KPI shows 3 with a change of +2

#### Scenario: Drill-down keeps the chart
- **WHEN** a lead clicks the Task bar of the record-type breakdown
- **THEN** the feed shows only task changes, and the charts still describe the whole scope

#### Scenario: Developers and QA blocked
- **WHEN** a developer or QA user requests the page or `GET /api/v1/activities/analysis`
- **THEN** the response is 403
