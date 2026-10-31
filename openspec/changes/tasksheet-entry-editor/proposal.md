## Why

Filling in or editing a tasksheet row expands an inline table row with four rich-text editors, a leave select and three number fields crammed into the table width. It is hard to use, looks dated, and loses the user's input on a validation error. The tasksheet is filled every day, so its entry experience needs to be professional and pleasant.

## What Changes

- **Edit drawer:** replace the inline edit row with a **slide-over drawer** per member row. The drawer has:
  - a vibrant header: member avatar, team, date, and a filled-fields badge;
  - **attendance as selectable cards**: Working, Casual leave, Sick leave, Half day leave. A full-day leave dims and inerts the task fields and explains that they won't be saved;
  - color-coded sections for the morning plan, day-end result, tickets and comment;
  - metric cards for work points, ticket count and ticket points;
  - a distinct lead-only feedback panel;
  - a sticky Save/Cancel footer.

  It closes on Cancel, Escape or a backdrop click, and locks page scroll while open.
- **Error handling:** after a failed save, the drawer reopens on the same row with the user's input and the error.
- **Display rows:**
  - member avatar;
  - status badges (Half day, late entry, leave, absent);
  - tinted work-point and ticket-point pills;
  - a primary "Fill in" button for empty rows and "Edit" for filled ones.
- **Fix: Trix theme.** The rich-text editor theme overrides were inside `@layer components`, so Tailwind purged them: Trix adds its `.trix-*` classes at runtime, and the content scanner never sees them. They now live outside the layer, so the flat toolbar finally applies across Notes, Meetings, forms and the tasksheet.
- **Unchanged:** routes, the upsert endpoint, field names, standup toggling and role rules. Members still never see feedback.

## Capabilities

### New Capabilities
- `tasksheet-entry-editor`: the drawer-based create/edit experience for tasksheet rows and the row presentation.

### Modified Capabilities

<!-- None (no archived specs). -->

## Impact

- **Views:** `tasksheet/_row.blade.php`.
- **CSS:** the Trix block moved outside `@layer` in `resources/css/app.css`.
- **Tests:** new feature tests for the drawer markup, reopening after an error, and feedback visibility. Existing tasksheet tests pass unchanged.
