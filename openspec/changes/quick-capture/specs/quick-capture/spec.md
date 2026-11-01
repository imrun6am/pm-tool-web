## ADDED Requirements

### Requirement: Capture a task from any page
A signed-in user SHALL be able to open a quick-capture dialog from any page:
- by pressing `C` outside text fields;
- from the "New task" create action;
- from ⌘K.

Saving SHALL create a top-level task through the standard task rules, assigned to the user unless they pick someone else.

#### Scenario: Keyboard capture
- **WHEN** a developer presses `C` on the calendar, types "Update API docs", picks "Tomorrow" and presses Enter
- **THEN** a To Do task due tomorrow and assigned to the developer is created
- **AND** a confirmation toast appears without leaving the calendar

#### Scenario: Typing in a field
- **WHEN** the user presses `C` while typing in a note editor
- **THEN** the letter is typed and no dialog opens

### Requirement: Capture respects team scoping
The assignee and release choices SHALL be limited to what the user may use on the board. For a developer or QA, that means only their teammates and their own teams' ongoing releases. A submission outside those limits SHALL be rejected with a validation error.

#### Scenario: Developer picks an outside assignee
- **WHEN** a developer submits a quick task assigned to someone outside their teams
- **THEN** the request fails validation and no task is created

### Requirement: Full-page fallback
`GET /tasks/new` SHALL render the same form as a page. Submitting it without JavaScript SHALL redirect to the new task.

#### Scenario: Direct link
- **WHEN** a user opens /tasks/new and submits a title
- **THEN** they land on the new task's page with a success message

### Requirement: Task search in the command palette
When the palette query has at least two characters, it SHALL list up to 8 matching tasks the user may see, open tasks first, under a "Tasks" group. The results SHALL be navigable with the arrow keys and openable with Enter.

#### Scenario: Developer searches
- **WHEN** a developer types "login" in ⌘K
- **THEN** only their teams' (and their own) tasks with "login" in the title are listed

### Requirement: Search over the API
`GET /api/v1/search/tasks?q=` SHALL return the same tasks, in the same order, as the web search.

#### Scenario: Parity
- **WHEN** the same user searches the same text on the web and over the API
- **THEN** the task ids match in order

### Requirement: Shortcut sheet
Pressing `?` outside text fields SHALL open a sheet listing the available keyboard shortcuts.

#### Scenario: Discover shortcuts
- **WHEN** a user presses `?` on the board
- **THEN** a dialog lists ⌘K, C, ? and Esc
