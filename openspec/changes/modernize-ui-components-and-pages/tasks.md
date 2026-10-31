## 1. Component Layer (CSS)

- [x] 1.1 Restyle `.field-input`, `.field-select` and `.field-textarea`: hover border, focus halo (`ring-4 ring-brand-500/15`), placeholder, and read-only, disabled and `aria-invalid` states. Add `.field-sm`.
- [x] 1.2 Style the native pickers (date, datetime-local, time indicator, `color-scheme`), the `file:` button, and `.field-check` for checkbox and radio
- [x] 1.3 Add `.card-footer`, `.table-wrap`, the refreshed `.table-base` (sentence-case headers, comfortable rows) and the badge tone modifiers (`badge-slate`, `brand`, `success`, `warning`, `danger`, `info`, `violet`)
- [x] 1.4 Add the filter-bar classes (`.filter-bar`, `.filter-select`, `.filter-date`, `.filter-search`) and the `.composer` wrapper
- [x] 1.5 Add the Trix theme overrides: flat toolbar, 32px icon buttons, group dividers, a single composer border with `:focus-within` and an invalid state

## 2. Blade Components

- [x] 2.1 `x-page-header`: breadcrumbs (`nav[aria-label=Breadcrumb]`, `aria-current` on the last crumb), title, description, actions slot
- [x] 2.2 `x-filter-bar` plus the Alpine `filterBar` helper in `app.js`: auto-submit on change, debounced search, `sr-only` submit, Clear link when `active`, optional count
- [x] 2.3 `x-empty-state` (icon, title, description, action slot) and `x-avatar` (initials, color stable per user ID, sizes)
- [x] 2.4 `x-field` (label, Optional pill, hint, error with `id="{name}-error"`), `x-form-section` and `x-form-actions` (sticky; delete, cancel and submit slots)
- [x] 2.5 `x-color-picker`: preset swatches plus a native custom input, submitting the same field name
- [x] 2.6 Restyle `x-dropdown`, `x-dropdown-link`, `x-modal`, `x-multi-select` (keeping `data-selected` and `name[]`) and the Breeze primitives (`text-input`, `primary-button`, `secondary-button`, `danger-button`, `input-label`) to the new system
- [x] 2.7 Restyle the `stat-tile`, `status-badge`, `role-badge` and `comments` partials to the new badge, card and avatar styles

## 3. Notes (reference page)

- [x] 3.1 Rebuild the `notes/index` header with `x-page-header`. Move the author, day and range filters into `x-filter-bar`, keeping the parameter names.
- [x] 3.2 Build the collapsible composer: "Write a note…" bar, then the expanded editor with date, visibility, recipients and actions. It starts open on validation errors or old input.
- [x] 3.3 Build the day-grouped single-column feed ("Today", "Yesterday", `D, M j`), keeping newest-first order
- [x] 3.4 Rebuild `notes/_card` as a feed item: avatar, name, time, visibility badge, body, recipients, quiet hover actions, and the inline edit form restyled
- [x] 3.5 Add `x-empty-state` for the unfiltered and filtered empty cases

## 4. Meeting Notes (reference pages)

- [x] 4.1 `meeting-notes/index`: page header with the "New meeting note" action, a filter bar (search, type, release, author, attendee, date range), row list and empty states. Keep the "No meeting notes match these filters" and "Clear all filters" text.
- [x] 4.2 `meeting-notes/show`: document layout with breadcrumbs, title and meta, prose body, a details aside (release, visibility, linked event, attendees with avatars) and comments
- [x] 4.3 `meeting-notes/form`, `create` and `edit`: sectioned Details, Access and Notes form in the standard frame. Keep the "Linked to event" text and the hidden `event_id`.

## 5. CRUD Forms

- [x] 5.1 Projects create and edit (reference form, using `x-color-picker`)
- [x] 5.2 Teams create and edit (using `x-color-picker`)
- [x] 5.3 Users create and edit
- [x] 5.4 Events create and edit
- [x] 5.5 Performance competencies create and edit
- [x] 5.6 Releases create and edit: standard header and breadcrumbs plus a sticky action bar. Keep the existing sections and the "At a glance" aside.
- [x] 5.7 Profile page: three sections in the standard frame (profile info, password, delete account)

## 6. List Pages

- [x] 6.1 Releases index: page header, filter bar, table in `.table-wrap`, empty state
- [x] 6.2 Users index: compact stats, the filter bar and table above the "Users by role" breakdown, empty state
- [x] 6.3 Activity, performance and tasksheet (index and user) filters converted to `x-filter-bar`
- [x] 6.4 Board and dashboard filters converted to `x-filter-bar`
- [x] 6.5 Projects and teams index plus their show pages: page header and empty states
- [x] 6.6 Remove every remaining raw `rounded-lg border-slate-300 text-sm shadow-sm` input string

## 7. Tests

- [x] 7.1 Feature test: the notes feed groups entries under "Today" and "Yesterday" headings in newest-first order
- [x] 7.2 Feature test: after a validation error the notes composer renders expanded, with the body error
- [x] 7.3 Feature test: the filter bar shows Clear only when a filter is active (meeting notes, releases)
- [x] 7.4 Feature test: a project submitted without a name renders `aria-invalid="true"` with `aria-describedby="name-error"`, and the error element exists
- [x] 7.5 Feature test: form pages render breadcrumbs, and the submit labels read "Create team" and "Save changes"
- [x] 7.6 Run `php artisan test`: the full suite passes with no edits to existing tests

## 8. Verification

- [x] 8.1 Run `npm run build` cleanly, and confirm a Blade search finds no raw input strings
- [x] 8.2 Take headless-Chrome screenshots of every list, form and detail page at 1440 and 390 widths, and review them for layout regressions and horizontal overflow
- [x] 8.3 Contrast check: field borders ≥3:1, badge tones ≥4.5:1, placeholder text ≥4.5:1 where it carries meaning. *Result: all badge, avatar and role tones pass (≥5.2:1). The Optional pill was fixed (now slate-600). The field border is 1.48:1, unchanged from before, and logged as an open question in design.md.*
- [x] 8.4 Interaction check: filter auto-submit and debounced search, composer expand and collapse, the sticky action bar on the release form, color-picker presets, and multi-select keyboard use
