## Context

`modern-sidebar-ui` delivered the app shell (sidebar, top bar, ⌘K). Page bodies still use the original component layer in `resources/css/app.css`:
- `.card` (116 uses), `.btn-*` (about 250), `.field-*` (about 140), `.table-base` (6), `.badge` (18);
- plus 32 copies of the raw input string `rounded-lg border-slate-300 text-sm shadow-sm focus:…`.

Pages are Blade with Alpine, Tailwind 3.4 and the `@tailwindcss/forms` plugin, and the editor is Trix. The tests assert on visible strings and some markup:
- `value="…" selected`, `data-selected`, and `name="event_id" value=…`;
- the text "No meeting notes match these filters", "Clear all filters", "Linked to event", "Write meeting note";
- notes ordered newest first.

## Goals / Non-Goals

**Goals:**
- One component system, so restyling a class updates every page.
- Calm, professional pages that follow the same anatomy: header, filters, content, empty state.
- Notes and Meetings rebuilt as the reference pages.
- Every create/edit form on one shared, sectioned, accessible layout.
- No behavior, route, parameter or permission changes, and no test edits.

**Non-Goals:**
- Dark mode.
- JavaScript date-picker libraries such as flatpickr.
- Changing what data a page shows.
- Redesigning the board's kanban interactions, the calendar grid, or the performance charts beyond the shared component restyle.
- Auth (guest) pages, which already match.

## Decisions

### 1. Restyle classes in place; add components only where markup repeats
The shared classes (`.card`, `.field-input`, `.table-base`, `.badge`, `.btn-*`) are restyled in place, which updates hundreds of usages without touching markup. New Blade components are added only where markup is repeated or structural: page header, filter bar, field, form section, form actions, empty state, avatar and color picker.

*Alternative considered:* converting everything to Blade components, such as `<x-input>`. That means a large churn across every page for the same visual result.

### 2. Control styling
`.field-input`, `.field-select` and `.field-textarea` use:
- `border-slate-300` at rest and `hover:border-slate-400`. **Measured:** the rest border is **1.48:1** against white, below the WCAG 1.4.11 target of 3:1 for component boundaries. It is the same border the app used before this change, so this is not a regression, and every field has a visible label. Reaching 3:1 needs about `slate-500`, which reads heavy (see Open Questions);
- `focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15`, giving a soft halo instead of a hard 1px ring;
- `placeholder:text-slate-400`;
- read-only: `read-only:bg-slate-50`; disabled: `disabled:bg-slate-50 disabled:text-slate-500`;
- invalid: `aria-[invalid=true]:border-danger-500 aria-[invalid=true]:ring-danger-500/15`.

A compact `.field-sm` (h-9) serves filter bars.

Native pickers:
- `[type=date|datetime-local|time]` gets `color-scheme: light`, and its `::-webkit-calendar-picker-indicator` drops to 50% opacity, rising to 100% on hover.
- `[type=color]` is wrapped by `x-color-picker`.
- `[type=file]` uses `file:` button styling.
- Checkbox and radio get `.field-check`: brand-colored and rounded, with a focus halo.

The 32 raw-string copies are replaced with the classes.

### 3. Filter bar (`x-filter-bar`)
```blade
<x-filter-bar :action="route('meeting-notes.index')" :active="$hasFilters" :count="$notes->total()" noun="note">
    <x-slot name="search">…input name=search…</x-slot>
    <select name="type" class="filter-select">…</select>
    …
</x-filter-bar>
```
- It renders a `<form method="GET">` laid out as a wrapping flex row. Search sits on the left with a magnifying-glass icon; filters follow as `.filter-select` and `.filter-date`, which are 36px tall, auto-width, and use the same border language as the fields.
- On the right sit the optional result count and, when `active`, a "Clear" link to `action`.
- Alpine `x-data="filterBar"` (in `app.js`):
  - `@change` on selects and dates calls `requestSubmit()`;
  - the search input submits on Enter natively, and also after a 400 ms pause in typing, but only once its value has changed;
  - an `sr-only` submit button keeps the form keyboard- and no-JS-operable.
- **Parameter names are untouched**, so controllers and tests are unaffected.
- The date-range group (`from`–`to`) is a joined pair of inputs with a muted "to" separator.

### 4. Field wiring (`x-field`)
```blade
<x-field label="Title" for="title" name="title" :optional="false" hint="…">
    <input id="title" name="title" class="field-input" @error('title') aria-invalid="true" aria-describedby="title-error" @enderror …>
</x-field>
```
The component renders the label (with an "Optional" pill when `optional`), the slot, the hint, and the `@error($name)` message with `id="{name}-error"`. Controls add the `aria-invalid` and `aria-describedby` attributes through the `@error` directive, so no markup magic is needed.

*Alternative considered:* having `x-field` inject the attributes into its slot. Blade can't change slot attributes, and a wrapper per control type would be heavier.

### 5. Form page frame
```
x-page-header (breadcrumbs, title, description)
<form class="mx-auto max-w-5xl space-y-6">          ← releases form: max-w-7xl two-column (existing)
   <div class="card"> x-form-section … x-form-section </div>
   x-form-actions  (sticky bottom-0, white/85 blur, border-t; Delete left, Cancel + Submit right)
</form>
```
- The existing `.form-section` (a three-column grid with title and description on the left) is kept and wrapped by `x-form-section`.
- The release edit page's delete form currently sits outside the main form. `x-form-actions` takes a `delete` slot that renders a separate `<form>` using the HTML `form` attribute, so the delete form doesn't nest inside the main one.

### 6. Notes feed
The controller is unchanged. The view groups the current page's notes with `$notes->getCollection()->groupBy(fn ($n) => $n->date->toDateString())`. The query already orders them newest first, and that order is preserved within and across groups. Headings read "Today", "Yesterday", or `D, M j`, with the year added when it isn't the current year.

The composer is a card with `x-data="{ open: @js($errors->hasAny(['body','date','visibility','recipients']) || old('body') !== null) }"`.
- Collapsed: an avatar plus a button reading "Write a note…".
- Expanded: the Trix composer, a date picker, the visibility select, recipients (only when visibility is "specific"), and Cancel / Add note.

Each note is a feed item: avatar, name, time and visibility badge, then the body. Edit and Delete show as quiet icon buttons that appear on hover, and are always visible on touch devices and on keyboard focus. The existing inline-edit behaviour stays.

### 7. Meetings list and detail
- **List:** rows in a single `card divide-y`. A row is an `<a>` that shows the type badge, the title and a one-line excerpt (`Str::limit(strip_tags(bodyPreviewHtml), 140)`), with the meta on the right: release chip, date, avatar and attendee count.
- **Detail:** a two-column layout. The main column holds the title, the meta and the prose body (`max-w-prose`), with comments below. A 20rem aside holds the details list and attendees. The "Write meeting note" text and links on other pages are untouched.

### 8. Trix theme
Overrides scoped to `trix-toolbar` and `trix-editor`:
- remove the borders on button groups;
- make buttons 32px icon targets with hover and active (`.trix-active`) backgrounds and group dividers;
- the toolbar and editor share one rounded border through a wrapper, `.composer`, built from the existing `trix-toolbar` + `trix-editor` siblings and driven by `:focus-within`.

Trix's own icons are kept, since they are background images.

### 9. Avatar color
A palette of 8 tones (brand, violet, sky, emerald, amber, rose, teal, fuchsia), each a `bg-*-100 text-*-700` pair, picked by `user_id % 8`. Literal class strings stay in the component so Tailwind keeps them.

## Risks / Trade-offs

- **The global restyle touches every page.**
  - *Mitigation:* keep sizes, so 40px controls stay 40px. Spot-check a screenshot of every page with the headless-Chrome script, and run the full test suite.
- **Auto-submitting filters may surprise users who change several filters in a row.**
  - *Mitigation:* each change is one fast GET, and this is the common pattern in project-management tools. Search is debounced.
- **Grouping notes by day across paginated pages** can split one day over two pages.
  - *Accepted:* the same heading simply repeats at the top of the next page.
- **Tests that assert on exact strings.**
  - *Mitigation:* keep the listed strings verbatim and run the suite after each page group.
- **Volume of page edits** (about 30 files).
  - *Mitigation:* finish the foundation and the reference pages (Notes, Meetings, projects form) first. The remaining forms and list pages then follow those references mechanically.

## Migration Plan

Front-end only. Deploying requires `npm run build`. There are no migrations. Rollback is reverting the commit.

## Open Questions

- **Field border contrast:** should the input border be darkened to `slate-500` (4.76:1) to meet WCAG 1.4.11 strictly, at the cost of a heavier look? It is currently 1.48:1, unchanged from before. Placeholder text (`slate-400`, 2.56:1) carries examples only; the labels carry the meaning.
- Should list pages remember the last-used filters per user? Proposed: no, as that needs persistence. It is a follow-up.
