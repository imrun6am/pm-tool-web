## Context

Release Planner is a Laravel 13 + Blade + Alpine.js + Tailwind 3 app. Every authenticated page renders through `<x-app-layout>` (`layouts/app.blade.php`). That layout stacks four things vertically:

- a sticky top bar (`layouts/navigation.blade.php`, `h-16`);
- an optional `$header` sub-header;
- a flash area;
- `<main>`.

The menu is a 12-entry `@php` array in `navigation.blade.php`. Its role filters are inlined there too: `isLead()`, `canManageUsers()`, `hasLimitedAccess()`. On desktop the menu renders as text-only top-bar links. On mobile it renders as a hamburger dropdown with solid Heroicons v1 icons.

The layout has two opt-in modes that pages depend on:
- `full-height` puts the shell in the viewport and makes `<main>` scroll internally.
- `sticky-header` pins the sub-header at `top-16`. It is used by `releases/show`.

`activity/index` hard-codes `lg:h-[calc(100vh-9.5rem)]`, which assumes a 4rem top bar.

Tests assert on the menu markup:
- `DevQaAccessTest`: a developer's page must not contain "Projects" or "Activity".
- `PerformanceEvaluationTest`: `>Performance<` must appear for leads only.
- `QuickLinksTest`: the page must contain "Quick links".

The user's direction is to make the app look and behave like a **modern project-management tool** (Linear, Asana, Jira, ClickUp): a left sidebar with grouped sections, colored icons, a prominent Create action, and keyboard-first navigation.

## Goals / Non-Goals

**Goals:**
- A grouped, role-aware left sidebar with a distinct icon and accent color per item. It collapses to an icon rail on desktop and becomes an off-canvas drawer on mobile.
- A workspace header, a Create menu, and a ⌘K command palette, following modern PM-tool conventions.
- One source of truth for the menu (a PHP class) that the sidebar, mobile drawer, and command palette all read, so role rules cannot drift between them.
- A named, contrast-checked color-token layer for the shell.
- Zero change to URLs, permissions, controllers, or page bodies. Existing tests pass unchanged.

**Non-Goals:**
- Dark mode.
- Redesigning individual page bodies (dashboard widgets, tables, forms).
- Notification inbox or unread counts.
- User-pinned favorites in the sidebar.
- Record search in the command palette. It searches navigation and actions only.
- Workspace switching. The app is single-tenant, so the workspace header is branding only.

## Decisions

### 1. Navigation registry: `App\Support\Navigation`
A plain class with two methods:
- `groups(User $user): array` returns `[{key, label, items: [{key, label, route, patterns, icon, color}]}]`.
- `createActions(User $user): array` returns `[{label, route, icon}]`.

It holds the full catalogue and applies the existing role rules, which move verbatim out of the Blade file. It drops empty groups. A small `isActive(array $item): bool` helper wraps `request()->routeIs(...$patterns)`.

A view composer in `AppServiceProvider`, the same pattern the quick-links drawer already uses, shares `$navGroups` and `$createActions` with `layouts.sidebar` and `layouts.command-palette`.

*Why:* today the rules live in a Blade `@php` block, which can't be unit tested. With three consumers (sidebar, mobile drawer, palette), one shared array keeps them from drifting apart.

*Alternatives considered:*
- A `config/navigation.php` file can't express the role closures cleanly.
- A database-driven menu is overkill for a fixed product menu.

### 2. Group and item catalogue

| Group | Item | Route | Active patterns | Icon (outline) | Accent |
|---|---|---|---|---|---|
| Overview (no heading) | Dashboard | `dashboard` | `dashboard` | `squares` | brand (indigo-600) |
| Planning | Releases | `releases.index` | `releases.*` | `rocket` | violet-600 |
| Planning | Projects | `projects.index` | `projects.*` | `folder` | amber-700 |
| Planning | Teams | `teams.index` | `teams.*` | `user-group` | pink-600 |
| Work | Board | `board.index` | `board.*`, `tasks.*` | `view-columns` | sky-600 |
| Work | Tasksheet | `tasksheet.index` | `tasksheet.*` | `clipboard-check` | emerald-600 |
| Work | Calendar | `calendar.index` | `calendar.*`, `events.*` | `calendar` | rose-600 |
| Collaboration | Notes | `notes.index` | `notes.*` | `pencil-square` | orange-600 |
| Collaboration | Meetings | `meeting-notes.index` | `meeting-notes.*` | `chat-bubbles` | teal-600 |
| Collaboration | Activity | `activity.index` | `activity.*` | `clock` | cyan-600 |
| Administration | Performance | `performance.index` | `performance.*` | `chart-bar` | fuchsia-600 |
| Administration | Users | `users.index` | `users.*` | `identification` | slate-600 |

Role rules carry over unchanged:
- `hasLimitedAccess()` keeps only `dashboard`, `board`, `tasksheet`, `calendar`, `notes`, and `meetings`.
- Performance requires `isLead()`.
- Users requires `canManageUsers()`.

Create actions:
- "New release" (`releases.create`) when the user `canManageReleases()`;
- "New event" (`events.create`) for all users;
- "New meeting note" (`meeting-notes.create`) for all users.

Both `EventPolicy` and `MeetingNotePolicy` allow creation for every authenticated user.

The group heading label "Activity" is never used, because `DevQaAccessTest` asserts that a developer's page does not contain that word.

*Accent shades:* the `-600` shade is the default, except amber, which uses `-700`. Every icon meets 3:1 contrast against white, against the `slate-100` hover background, and against the `brand-50` active background. Amber-600 measures about 2.9:1 on `slate-100`, which is why amber goes one step darker. Yellow is deliberately not used.

### 3. Shell layout: fixed sidebar + content column
```
<body>
  <div x-data="appShell" class="min-h-screen">
    <aside sidebar  fixed inset-y-0 left-0  w-64 | w-[4.5rem] (collapsed)   lg:translate-x-0
                    mobile: -translate-x-full ↔ translate-x-0 + backdrop>
      header (brand + collapse toggle)
      Create button (dropdown)
      <nav aria-label="Main"> groups… </nav>   ← only this region scrolls
    </aside>
    <div content  lg:pl-64 | lg:pl-[4.5rem]>
      top bar  sticky top-0 h-16
      $header  (sticky top-16 when sticky-header)
      flash
      <main>
    </div>
  </div>
```

- The top bar stays `h-16`. This keeps `sticky top-16` and `activity`'s `calc(100vh-9.5rem)` correct with no page edits.
- `full-height` moves its classes (`lg:h-screen lg:overflow-hidden`, flex column) onto the content column.
- One `sidebar.blade.php` serves both desktop and mobile; only its positioning classes change by breakpoint. This avoids duplicating markup, unlike the current separate mobile menu.

*Alternative considered:* a CSS grid shell (`grid-cols-[auto_1fr]`). It is simpler for the desktop case, but a mobile overlay still needs fixed positioning, and sticky children inside grid cells behave less predictably with `full-height`.

### 4. Collapse state without a flash of the wrong layout
The collapsed preference is stored in `localStorage['rp.sidebar']`. Before paint, a tiny inline `<script>` in `<head>` reads it inside `try/catch` and sets `data-sidebar="collapsed"` on `<html>`.

The width and padding rules key off `html[data-sidebar=collapsed]` in CSS, using `lg:` variants plus a custom `sidebar-collapsed:` Tailwind variant registered through `addVariant`. They do not use Alpine's `:class`. Alpine's `appShell.toggleCollapse()` flips the attribute and writes the storage key, also inside `try/catch`.

*Why:* if the state lived only in Alpine, every page load would render expanded and then snap to collapsed after hydration.

### 5. Icons: an `outline` variant in `<x-icon>`
Add `variant` (default `solid`) to `components/icon.blade.php`, with a second map of Heroicons v2 outline paths:
- 24×24 viewBox;
- `fill="none"`, `stroke="currentColor"`, `stroke-width="1.75"`.

The map covers the 12 nav glyphs plus the shell glyphs:
- `bars-3`, `x-mark`, `magnifying-glass`;
- `chevron-double-left`, `plus`, `link`, `chevron-up-down`.

The existing solid icons and their roughly 37 call sites are untouched.

*Why:* outline icons at 20px are the visual language of Linear, Jira, and ClickUp sidebars. Keeping solid as the default avoids touching every page.

*Alternative considered:* the `blade-ui-kit/blade-heroicons` package. It is a new Composer dependency and a second icon API alongside `<x-icon>`, and the app already has a working inline pattern.

### 6. Color tokens (`tailwind.config.js`)
- Keep the `brand` scale (indigo).
- Add `sidebar`: `{ DEFAULT: '#ffffff', border: slate-200, text: slate-600, muted: slate-500, hover: slate-100, active: brand-50, 'active-text': brand-700 }`.
- Add status aliases `success` (emerald), `warning` (amber), `danger` (rose), and `info` (sky), each a full 50–900 scale referencing Tailwind's palette.

Group headings use `slate-500`, not `slate-400`. That works out to 4.76:1 contrast against white versus 2.56:1, so headings meet the 4.5:1 text requirement.

New shell CSS lives in `@layer components`: `.sidebar`, `.nav-group-label`, `.nav-item`, `.nav-item-active`, `.nav-tooltip`, and `.kbd`. This follows the existing `.btn-*` and `.card` conventions.

Status aliases are introduced but existing badges are *not* migrated in this change. Migrating them is a follow-up (see Open Questions).

### 7. Command palette
`layouts/command-palette.blade.php` is an Alpine component. Its entries are rendered server-side as `@js($entries)`, built from `Navigation` for the current user only, so hidden items never reach the browser.

Behaviour:
- Opens on `⌘K` or `Ctrl+K` (a `keydown.window` handler), on the top-bar trigger, and on a `open-command-palette` window event.
- Filters with a case-insensitive substring match on `label + group`.
- ↑ and ↓ move the selection, Enter calls `window.location.assign(url)`, and Escape closes.
- The input has `role="combobox"` and the results list has `role="listbox"`, with `aria-activedescendant` tracking the selection.

**Trix conflict:** Trix binds ⌘K to "insert link". The global handler ignores the shortcut when `event.target` is inside `trix-editor` or any `[contenteditable]`, and when the event was already `defaultPrevented`.

### 8. Create menu
The Create menu reuses the existing `<x-dropdown>` component, aligned left and positioned under the button. The sidebar header and Create button sit outside the scrolling `<nav>` region, so the dropdown is not clipped. In collapsed mode, the dropdown opens to the right of the rail.

### 9. Quick links
The floating right-edge handle in `partials/quick-links-drawer.blade.php` is removed. The top bar gets an icon button labelled "Quick links" that dispatches the existing `toggle-quick-links` window event. The drawer itself is unchanged.

### 10. Motion and accessibility
- Transitions use `transition-[width,transform] duration-200` plus `motion-reduce:transition-none`.
- The mobile drawer locks body scroll while open (`overflow-hidden` on `<html>`), returns focus to the menu button on close, and closes on Escape, on a backdrop click, or when a link is followed.
- Tooltips on collapsed items are CSS-only (`group-hover` and `group-focus-visible`) and are shown only under `sidebar-collapsed:`.
- Labels stay in the DOM as `sr-only` when collapsed.

## Risks / Trade-offs

- **Text assertions in tests can match new chrome.**
  - *Risk:* the word "Activity" in a tooltip, palette JSON, or group label would break `DevQaAccessTest`.
  - *Mitigation:* every string comes from the role-filtered registry, and no group is named "Activity". Run the full suite as part of the tasks.
- **⌘K conflicts with Trix or browser shortcuts.**
  - *Mitigation:* skip the shortcut inside editors (Decision 7). Firefox's ⌘K address-bar binding is overridden only via `preventDefault` on app pages, which is accepted practice in comparable tools.
- **A fixed sidebar reduces content width by 256px.**
  - *Risk:* the board and the release timeline are wide.
  - *Mitigation:* the collapsible 72px rail, with the preference saved.
- **The pre-paint inline script.**
  - *Risk:* a tiny inline script must stay CSP-compatible if CSP is added later.
  - *Mitigation:* it's 3 lines and can move to a nonce'd script.
- **The dropdown inside a fixed sidebar** may be clipped by `overflow` ancestors.
  - *Mitigation:* the Create button sits outside the scrolling region (Decision 8). Verify visually in both states.
- **Pages with their own `sticky`/viewport math** (`releases/show`, `activity/index`, `releases/form` `lg:top-6`) might misalign.
  - *Mitigation:* the top-bar height stays 4rem. Verify these pages in the browser.

## Migration Plan

- Front-end only. Deploying requires `npm run build`.
- There are no migrations, config, or environment changes.
- Rollback is reverting the commit and rebuilding assets.
- The localStorage key `rp.sidebar` is harmless if left behind.

## Open Questions

- Should existing status badges (`partials/status-badge`, `role-badge`) migrate to the new `success`/`warning`/`danger`/`info` aliases now, or in a follow-up? Proposed: follow-up, to keep this change about the shell.
- Should Board and Tasksheet show count badges, such as "my open tasks", the way PM tools show inbox counts? Deferred. It needs per-request queries and a caching decision.
- Should the user menu move to the sidebar footer (Linear/ClickUp style) or stay top-right (Jira/Asana style)? Proposed: top-right, which keeps the sidebar focused on navigation and matches the existing dropdown.
