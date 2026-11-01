## ADDED Requirements

### Requirement: Every dropdown can be searched
Every single-choice dropdown SHALL open a list that can be filtered by typing. A search box SHALL appear when the dropdown has more than 6 options, and smaller lists SHALL support type-ahead. When nothing matches, the list SHALL say "No matches".

#### Scenario: Picking an assignee
- **WHEN** a lead opens the Assignee dropdown and types "dev"
- **THEN** only people whose names contain "dev" are listed, and Enter picks the highlighted one

#### Scenario: Short list
- **WHEN** a user opens a 3-option dropdown and types "m"
- **THEN** the first option starting with "m" is highlighted, with no search box shown

### Requirement: Forms and bindings behave as before
Choosing an option SHALL update the underlying form field and fire its normal change events. As a result:
- forms post the same values;
- filter bars apply immediately;
- Alpine `x-model` bindings update;
- pre-selected values (including after a validation error) show on load;
- invalid fields keep their error styling.

#### Scenario: Release form team
- **WHEN** a lead picks a different team on the release form
- **THEN** the form's team-dependent member list updates as it did with the native select

#### Scenario: Filter bar
- **WHEN** a user picks a project in the dashboard filters
- **THEN** the dashboard reloads filtered by that project

### Requirement: Accessible and keyboard-operable
The dropdown SHALL:
- be fully operable by keyboard: arrows, Enter, Escape, Tab, Home/End;
- expose combobox and listbox semantics with the active option announced;
- keep its label;
- never let a disabled option be chosen.

#### Scenario: Escape
- **WHEN** a user presses Escape in an open dropdown
- **THEN** it closes without changing the value, and focus returns to the dropdown

### Requirement: Works everywhere a dropdown appears
Dropdowns SHALL be enhanced in all of these places, and enhanced only once:
- pages reached by soft navigation;
- in-page region updates;
- dialogs loaded on demand;
- lists rendered by Alpine.

An open list SHALL NOT be clipped by its container. On small screens it SHALL open as a bottom sheet.

#### Scenario: Quick capture dialog
- **WHEN** a user presses C and opens the Release dropdown in the dialog
- **THEN** the searchable list opens above the dialog, and choosing keeps the dialog open
