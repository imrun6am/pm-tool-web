## ADDED Requirements

### Requirement: Overview section order
The lead/viewer dashboard SHALL render its sections in this order:
1. the page header;
2. the filter bar;
3. the KPI strip;
4. the release timeline;
5. the insights row.

The timeline SHALL start within the first 900px of the page when the filters match two or fewer groups.

#### Scenario: Timeline above the charts
- **WHEN** a lead opens the dashboard
- **THEN** the timeline card appears before the "Delivery progress", "Release load" and "Team workload" cards in the markup

### Requirement: Period-aware header
The header SHALL show a "Dashboard" title and a description containing the active period label (for example "Q4 2026") and its date range. The "New release" action SHALL appear only for users who can manage releases.

#### Scenario: Period in the header
- **WHEN** a lead opens the dashboard for 2026 Q4
- **THEN** the header description includes "Q4 2026" and the range "Oct 1 – Dec 31, 2026"

### Requirement: Compact KPI strip
The four headline metrics (active releases, completed, starting soon, scheduling conflicts) SHALL render as one card divided into equal cells. Each cell has a label, a value and a short context line. When there are conflicts, the conflicts cell SHALL use the warning tone and link to the timeline.

#### Scenario: Conflict cell links to the timeline
- **WHEN** the current view contains overlapping releases for the same team
- **THEN** the conflicts cell shows the count in the warning tone and links to `#timeline`

### Requirement: Timeline presentation
The timeline card SHALL have the id `timeline` and SHALL include:
- a header with the title, the number of visible releases, the phase legend and, when there are conflicts, a conflict notice;
- a month axis;
- one row per group, linking to the team or project;
- bars that keep their phase segments, links and conflict outline.

When today falls inside the visible range, the timeline SHALL draw a vertical "Today" marker at the same horizontal position the bars use for today's date. When no releases match, it SHALL show an empty state that offers "New release" to managers. Completed releases SHALL NOT appear.

#### Scenario: Today marker
- **WHEN** a lead views the current quarter
- **THEN** the timeline renders a "Today" marker positioned at today's offset within the range

#### Scenario: No today marker outside the range
- **WHEN** a lead views a past year
- **THEN** no "Today" marker is rendered

### Requirement: Insights row
Below the timeline, three equal-height cards SHALL show:
- **Delivery progress:** a donut chart of task statuses, the percentage done in its center, and a legend of each status's count;
- **Release load:** monthly bars, with the current month highlighted;
- **Team workload:** active releases per team, with bars in the team's color.

Each card SHALL show an empty message when it has no data.

#### Scenario: No tasks yet
- **WHEN** the ongoing releases in view have no tasks
- **THEN** the Delivery progress card shows "No tasks on the ongoing releases in this view yet." instead of the donut

### Requirement: Member "My day" summary
The developer/QA dashboard SHALL keep the title "My day". It SHALL show:
- a greeting with the date;
- a summary strip: open tasks, overdue tasks (in the danger tone when there are any), and meetings in the next 14 days;
- the open-task list, with release and due-date pills;
- today's tasksheet status per team, with its existing status wording;
- upcoming meetings with calendar-style date tiles.

#### Scenario: Overdue highlighted
- **WHEN** a developer has an open task whose due date has passed
- **THEN** the overdue count in the summary uses the danger tone and the task's due pill reads "Overdue"

### Requirement: Unchanged data contract
The dashboard SHALL be a presentation-only change. The controller, `DashboardService`, the variables passed to the views, query parameter names, and the quarter `<option value="N" selected>` markup SHALL stay as they are.

#### Scenario: Existing behaviour preserved
- **WHEN** the existing dashboard, release-completion, dev/QA-access and web/API-parity tests run
- **THEN** they pass without modification
