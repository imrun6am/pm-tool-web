## ADDED Requirements

### Requirement: Developers and QA open on their own tasks
The board and task list SHALL show a Developer or QA user only the tasks assigned to them when the page is opened with no filters, with My tasks selected.

#### Scenario: Opening the page
- **WHEN** a Developer or QA user opens the board or All tasks with no filters
- **THEN** only tasks assigned to them are listed and My tasks is selected

#### Scenario: Sorting, grouping or paging
- **WHEN** they sort, group or page the default list
- **THEN** it stays on their own tasks

#### Scenario: Other roles
- **WHEN** a lead or viewer opens either page with no filters
- **THEN** every task they may see is listed

### Requirement: Everyone's tasks stay reachable
A Developer or QA user SHALL be able to switch to every task they are allowed to see, and team scoping SHALL still apply.

#### Scenario: Choosing Anyone
- **WHEN** they choose Anyone or turn My tasks off
- **THEN** every task visible to them is listed and My tasks is not selected

#### Scenario: Following a filtered link
- **WHEN** they open either page with a filter such as a release, team or label
- **THEN** the results are not narrowed to their own tasks

#### Scenario: Using the API
- **WHEN** a client lists tasks or the board through the API with no filters
- **THEN** every task visible to the caller is returned
