## ADDED Requirements

### Requirement: Team performance depth
The lead-only overview SHALL show:
- the team average against last week;
- per-competency team trends with their weekly change;
- the week's 1–5 rating spread;
- each member against the team average;
- an objective record per member over the last 4 weeks, which never counts archived tasks.

#### Scenario: Archived work excluded
- **WHEN** a member's finished task is later archived
- **THEN** it counts toward neither their completed tasks nor overdue work

### Requirement: Member performance depth
The member scorecard SHALL show:
- change against last week;
- strengths and growth areas from the member's own competency averages;
- a per-competency weekly history;
- the objective record against the previous 4 weeks;
- lead notes.

#### Scenario: Strengths and growth areas
- **WHEN** a member averages 4.5 on Code Quality and 2.5 on Communication
- **THEN** Code Quality is listed as a strength and Communication as a growth area

### Requirement: Access unchanged
Performance analysis SHALL remain reachable only by leads, with team leads limited to the teams they lead. Developers, QA and viewers SHALL receive 403 on every performance page and endpoint.

#### Scenario: Viewer blocked
- **WHEN** a viewer opens the performance overview
- **THEN** the response is 403
