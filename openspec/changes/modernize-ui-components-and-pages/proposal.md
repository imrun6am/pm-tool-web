## Why

The new sidebar shell (`modern-sidebar-ui`) made the chrome look like a modern project-management tool, but the pages inside it still look dated and cluttered.

- **Notes:** five unlabeled filter controls are packed into the page header, and an always-open editor dominates the page.
- **Meetings:** filters take up a two-row form card.
- **Forms:** they stretch single fields across 1,000+ px.
- **Components:** inputs are hand-rolled (32 copies of a raw input class string), date pickers are inconsistent, the rich-text toolbar looks dated, and badges, empty states and filter areas look different on every page.

Users asked for every component, every create/edit form, and every list page to look professional and consistent.

## What Changes

- **Refresh the component layer** in `resources/css/app.css`. This restyles every page that uses the shared classes at once.
  - Inputs and selects get soft borders, hover states, a brand focus halo, and invalid, disabled and read-only states.
  - Cards gain a `card-footer`.
  - Tables get calmer headers, comfortable rows and a scroll wrapper.
  - Badges get tone modifiers.
  - Checkboxes, radios and file inputs get matching styles.
  - Date and time pickers become consistent native pickers with a styled calendar icon.
  - The rich-text editor becomes one flat, modern composer surface.
- **Add reusable Blade components:**
  - `x-page-header`: breadcrumbs, title, description and actions.
  - `x-filter-bar`: a compact single-row toolbar that applies filters automatically, with a search box, an active-filter "Clear" link and a result count.
  - `x-empty-state`
  - `x-field`: label, optional/required marker, hint, error, and `aria-invalid` wiring.
  - `x-form-section`
  - `x-form-actions`: a sticky footer with Cancel, Submit and an optional Delete.
  - `x-avatar`: initials with a stable color per person.
  - `x-color-picker`: preset swatches plus a custom color.
- **Restyle existing components to match:** `x-dropdown`, `x-dropdown-link`, `x-modal`, `x-multi-select`, the Breeze form primitives (`x-text-input`, `x-primary-button`, `x-input-label`), the status and role badges, and the stat tile.
- **Redesign Notes:**
  - Filters move into a filter bar.
  - The composer shrinks to a "Write a note…" bar that expands when clicked, and stays open when validation fails.
  - Notes appear as a single-column feed grouped by day, with avatars and quieter actions.
- **Redesign Meetings:**
  - The index gets a filter bar and a clean list of rows: type, title with an excerpt, release, date, author, attendees and visibility.
  - The detail page becomes a document layout with a details side panel.
- **Modernize every create/edit form:** projects, teams, users, events, meeting notes, competencies, releases and profile.
  - Each form sits in a readable centered column with a page header and breadcrumbs.
  - Fields are grouped into titled sections, with consistent required/optional markers and inline errors.
  - A sticky action bar holds the buttons.
- **Move every list page's filters into `x-filter-bar`:** dashboard, releases, users, activity, performance, tasksheet, board. Their empty states switch to `x-empty-state`.
- Routes, query parameters, validation rules, permissions and stored data are all unchanged, as is every text and markup assertion the existing tests rely on.

## Capabilities

### New Capabilities
- `ui-components`: The shared component system. It covers form controls and pickers, cards, tables, badges, dropdowns, modals, the rich-text editor, avatars, empty states, and the page-header and filter-bar components, together with their states and accessibility.
- `crud-forms`: How every create/edit form is laid out and behaves: header and breadcrumbs, sections, field markers and errors, the sticky action bar, and keeping the user's input after a failed submit.
- `page-layouts`: How list and detail pages are built: header, filter bar, content and empty state. It includes the Notes feed and the Meetings list and detail layouts.

### Modified Capabilities

<!-- None. No archived specs exist in openspec/specs/. Behavior of notes, meeting notes, and filters is unchanged; only presentation changes. -->

## Impact

- **CSS:** `resources/css/app.css` (component layer), plus the Trix theme overrides.
- **New Blade components:** `components/page-header`, `filter-bar`, `empty-state`, `field`, `form-section`, `form-actions`, `avatar`, `color-picker`.
- **Restyled components:** `dropdown`, `dropdown-link`, `modal`, `multi-select`, `text-input`, `primary-button`, `secondary-button`, `danger-button`, `input-label`; the partials `stat-tile`, `status-badge`, `role-badge`, `comments`.
- **Pages:**
  - `notes/*`, `meeting-notes/*`
  - the form pages for projects, teams, users, events, releases, competencies and profile
  - the list pages for dashboard, releases, users, activity, performance, tasksheet and board
- **JS:** a small Alpine helper that submits the filter bar automatically (and debounces the search box).
- **Tests:** existing feature tests must keep passing unchanged. New feature tests cover the filter bar's clear/active state, notes grouped by day, the expanded composer on validation errors, and form field `aria-invalid` wiring.
- **Dependencies:** none added. The design stays on native pickers.
- **Depends on** `modern-sidebar-ui`, which is implemented.
