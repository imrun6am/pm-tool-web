## ADDED Requirements

### Requirement: Unified form controls
All text inputs, selects, textareas and date/time inputs SHALL share one visual system. They SHALL have:
- the same height: 40px by default and 36px in compact filter bars;
- a soft border with a darker hover border;
- a visible brand-colored focus halo;
- a muted placeholder color.

They SHALL also have distinct visual states for disabled, read-only and invalid. A control SHALL appear invalid when it has `aria-invalid="true"`. Pages SHALL use the shared classes or components rather than restating raw utility strings.

#### Scenario: Focus state
- **WHEN** a user focuses any text input, select or textarea
- **THEN** the control shows the brand focus border and halo

#### Scenario: Invalid state
- **WHEN** a field has a validation error and carries `aria-invalid="true"`
- **THEN** the control shows a danger-colored border and halo

#### Scenario: No hand-rolled input styles
- **WHEN** the Blade views are searched for the raw string `rounded-lg border-slate-300 text-sm shadow-sm`
- **THEN** no matches are found

### Requirement: Consistent pickers
Date, datetime-local, time, color, checkbox, radio, file and multi-select controls SHALL match the unified control system.
- Date and time pickers SHALL stay native, for mobile and accessibility support, with a styled calendar indicator.
- The color picker SHALL offer preset swatches as well as a custom color, and SHALL submit the same field name and hex value as before.
- The multi-select SHALL keep submitting `name[]` values and keep its `data-selected` attribute. It SHALL be keyboard-reachable, and its control SHALL match the height and focus styling of the other fields.

#### Scenario: Color picker presets
- **WHEN** a user picks a preset swatch on the project form
- **THEN** the hidden or native `color` field holds that swatch's hex value and the swatch shows as selected

#### Scenario: Multi-select unchanged contract
- **WHEN** a form with a multi-select renders with pre-selected values
- **THEN** the markup contains `data-selected` listing those values, and submitting sends them as `name[]`

### Requirement: Cards, tables and badges
Cards SHALL use a single surface style and SHALL support optional header and footer regions. Data tables SHALL:
- sit inside a horizontally scrolling wrapper on narrow screens;
- use calm, sentence-case column headers;
- give rows a hover state.

Badges SHALL support named tones: `slate`, `brand`, `success`, `warning`, `danger`, `info`, `violet`. Each tone SHALL meet WCAG AA text contrast.

#### Scenario: Table on a narrow screen
- **WHEN** the users table renders at 390px wide
- **THEN** the table scrolls horizontally inside its card and the page itself has no horizontal scroll

### Requirement: Dropdowns and modals
Dropdown menus SHALL use the elevated popover style: rounded, ringed and shadowed, with padded items and hover and focus highlights. They SHALL close on outside click, on Escape, and when an item is chosen. Modals SHALL use the same surface style, with a dimmed and blurred backdrop.

#### Scenario: Dropdown closes on Escape
- **WHEN** a dropdown is open and the user presses Escape
- **THEN** the dropdown closes

### Requirement: Rich-text editor surface
The Trix editor SHALL appear as a single bordered composer: a flat toolbar of icon buttons, with group dividers and no per-button borders, sitting above the editing area. The composer SHALL show the same focus and invalid states as the other form controls.

#### Scenario: Composer focus
- **WHEN** a user clicks into a Trix editor
- **THEN** the whole composer, toolbar included, shows the brand focus border

### Requirement: Page header component
The system SHALL provide `x-page-header` with these parts:
- a title;
- an optional description;
- optional breadcrumbs, a list of label and URL pairs where the last item is the current page;
- an actions slot.

Breadcrumbs SHALL render inside `<nav aria-label="Breadcrumb">`, and the current page SHALL carry `aria-current="page"`.

#### Scenario: Breadcrumbs on a form page
- **WHEN** the "New project" page renders
- **THEN** the header shows the breadcrumb "Projects / New project" with "Projects" linking to the projects list

### Requirement: Filter bar component
The system SHALL provide `x-filter-bar`, a compact single-row toolbar wrapping a GET form. Its behaviour:
- Changing a select or date submits the form automatically.
- The search input submits on Enter, or after a short pause in typing.
- A visually hidden submit button SHALL be kept for keyboard and no-JS users.
- When any filter is active, the bar SHALL show a "Clear" link to the unfiltered URL.
- The bar SHALL optionally show a result count.

Query parameter names SHALL be unchanged from the current filters.

#### Scenario: Auto-apply on change
- **WHEN** a user changes the Type select in the meeting-notes filter bar
- **THEN** the page reloads with `?type=<value>` without pressing a button

#### Scenario: Clear link only when filtered
- **WHEN** the meeting-notes page loads with no filter parameters
- **THEN** no Clear link is shown
- **WHEN** it loads with `?type=standup`
- **THEN** a Clear link pointing to the unfiltered index is shown

### Requirement: Empty states and avatars
The system SHALL provide `x-empty-state` (an icon, a title, an optional description and an optional action) for every list that can be empty. It SHALL also provide `x-avatar`, which renders a person's initials on a background color derived from their user ID, so the same person always gets the same color.

#### Scenario: Stable avatar color
- **WHEN** the same user's avatar renders on two different pages
- **THEN** both avatars use the same background color class
