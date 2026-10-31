## ADDED Requirements

### Requirement: Drawer-based row editor
Each editable tasksheet row SHALL open its create/edit form in a slide-over drawer rather than an inline table row. The drawer SHALL:
- be labelled with the member's name;
- show the team and the date;
- post to the existing upsert route with the same field names;
- close on Cancel, Escape or a backdrop click;
- prevent the page behind it from scrolling while open.

Rows without an entry SHALL offer "Fill in". Rows with an entry SHALL offer "Edit".

#### Scenario: Fill in an empty row
- **WHEN** a lead clicks "Fill in" on a member's empty row and saves a plan and work points
- **THEN** the row shows the plan and the work points, and the button reads "Edit"

### Requirement: Attendance selection
The drawer SHALL present attendance as selectable options: Working plus each leave type. Choosing a full-day leave SHALL make the task fields non-interactive, with a notice that they won't be saved. Choosing half-day leave SHALL keep them editable.

#### Scenario: Full-day leave
- **WHEN** a user selects "Casual leave" in the drawer
- **THEN** the task fields become inert and a notice explains that a full-day leave clears the day's tasks

### Requirement: Reopen after validation errors
When a save fails validation, the page SHALL reopen the drawer for the same member and date, showing the submitted values and the first error.

#### Scenario: Invalid work points
- **WHEN** a row is saved with negative work points
- **THEN** the page re-renders with that row's drawer open, the error shown, and the entered plan text preserved

### Requirement: Feedback stays lead-only
The drawer's feedback panel SHALL render only for lead viewers. Members SHALL NOT see the word "Feedback" or any feedback content.

#### Scenario: Member drawer
- **WHEN** a developer opens their own row's drawer
- **THEN** the page contains no feedback panel and no "Feedback" text

### Requirement: Rich-text theme applies
The Trix editor theme (flat toolbar and shared composer border) SHALL be present in the compiled CSS. It SHALL NOT be purged by the build.

#### Scenario: Build contains the overrides
- **WHEN** assets are built
- **THEN** the compiled CSS contains the themed `trix-toolbar .trix-button-group` rule after Trix's default rule
