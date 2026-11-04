## ADDED Requirements

### Requirement: Open tasks can go into the plan
A person editing a tasksheet row SHALL be able to add that row owner's open tasks to the plan. Tasks already in the saved plan SHALL be disabled. Members SHALL only see their own tasks.

#### Scenario: Pick a task
- **WHEN** a member chooses an open task in the row editor
- **THEN** a line with its key and title is added to the plan, and saved with the row

#### Scenario: Someone else's tasks
- **WHEN** a member opens the sheet
- **THEN** no teammate's open tasks are listed

### Requirement: Rows show logged hours
Each tasksheet row SHALL show the time its person logged on tasks that day, on the web and through the API. Viewers SHALL NOT receive it.

#### Scenario: Viewer
- **WHEN** a viewer reads the sheet
- **THEN** the hours are left out
