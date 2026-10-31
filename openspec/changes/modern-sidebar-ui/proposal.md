## Why

The app has grown to 12 top-level sections, all crammed into one horizontal top bar. On anything narrower than a wide monitor the bar overflows or collapses into a hamburger. The items have no grouping, so planning, day-to-day work, and admin pages sit side by side with nothing to tell them apart. Icons only appear in the mobile menu, and Performance reuses the Activity icon, so visual cues don't help with navigation either. A grouped sidebar with distinct icons and a consistent color system gives the menu room to grow and makes the app feel like a modern product.

## What Changes

- Replace the horizontal top-bar navigation with a **persistent left sidebar** on desktop (`lg+`). It is organized into labelled groups:
  - **Overview**: Dashboard
  - **Planning**: Releases, Projects, Teams
  - **Work**: Board, Tasksheet, Calendar
  - **Collaboration**: Notes, Meetings, Activity
  - **Administration**: Performance, Users
- Give every menu item a **distinct icon** with its **own accent color**, so you can spot items by shape and color. Performance gets its own icon instead of reusing Activity's.
- Show a clear **active state**: a highlighted row, an accent indicator bar, and `aria-current="page"`. The active state also matches child routes, for example `events.*` under Calendar and `performance.competencies.*` under Performance.
- Let users **collapse the desktop sidebar** to an icon-only rail, with tooltips. The choice is saved per browser.
- On mobile, replace the dropdown hamburger menu with an **off-canvas sidebar drawer** that has a backdrop. Escape, the backdrop, or the close button closes it.
- Follow the layout conventions of **modern project-management tools** such as Linear, Asana, Jira, and ClickUp:
  - a light, neutral sidebar beside a white content area;
  - a workspace header at the top of the sidebar;
  - a primary **Create** button with role-scoped shortcuts: New release (release managers only), New event, and New meeting note;
  - compact rows with colored icons.
- Add a **⌘K / Ctrl+K command palette** for keyboard-first navigation. It jumps to any page or Create action the user can access.
- Slim the top bar down to a **utility bar** with:
  - the mobile menu button;
  - the command-palette search trigger;
  - a **Quick links** button, which replaces the floating right-edge handle;
  - the user menu.
- Introduce a **semantic color system** in the Tailwind theme: brand, sidebar surface, and the success, warning, danger, and info states. Also add a single **navigation registry** that defines every menu group, item, icon, color, and role rule in one place.
- Refresh the **brand mark** and app-shell surfaces (sidebar, top bar, page sub-header) so they use the new tokens.
- Keep **role visibility exactly as it is today**:
  - Developers and QA see only Dashboard, Board, Calendar, Notes, Meetings, and Tasksheet.
  - Performance and Users stay leads-only.
  - Groups with no visible items are hidden.

## Capabilities

### New Capabilities
- `app-navigation`: The application shell's navigation. It covers:
  - grouped sidebar structure and order
  - role-scoped item visibility
  - active-state rules
  - per-item icon and color identity
  - the sidebar workspace header and Create menu
  - the command palette
  - desktop collapse with a saved preference
  - the mobile off-canvas drawer
  - top utility-bar actions
  - accessibility of the navigation chrome

### Modified Capabilities

<!-- None. No archived specs exist in openspec/specs/. Role visibility rules from user-management-and-roles, dev-qa-restricted-access, and performance-evaluation are preserved unchanged and restated in app-navigation. -->

## Impact

- **Views**:
  - `resources/views/layouts/app.blade.php`: shell restructured into sidebar plus content column.
  - `resources/views/layouts/navigation.blade.php`: rewritten as the top utility bar.
  - New `resources/views/layouts/sidebar.blade.php` and `resources/views/layouts/command-palette.blade.php`.
  - `partials/quick-links-drawer.blade.php`: floating handle removed; it is opened from the top bar instead.
  - `activity/index.blade.php`: viewport-height calculation checked against the new shell.
- **Icons**: `components/icon.blade.php` gains an `outline` variant (Heroicons v2, 24px stroke) plus the new glyphs the sidebar needs. Existing solid icons and their call sites are unchanged.
- **Backend**: new `App\Support\Navigation` class that builds the role-filtered, grouped menu. It replaces the inline `@php` array in `navigation.blade.php`. Routes, controllers, and authorization are unchanged.
- **Styling**: `tailwind.config.js` gets semantic color tokens. `resources/css/app.css` gets sidebar and nav component classes.
- **Tests**:
  - Existing nav assertions (`DevQaAccessTest`, `PerformanceEvaluationTest`, `QuickLinksTest`) must keep passing.
  - New feature tests for grouping, active state, and role scoping.
  - Unit tests for `Navigation`.
- **Dependencies**: none added. Alpine.js handles collapse and drawer state.
- **No breaking changes**. URLs, permissions, and page content stay the same, and only the surrounding chrome changes.
