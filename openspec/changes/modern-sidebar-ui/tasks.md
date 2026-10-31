## 1. Design Tokens & Icons

- [ ] 1.1 In `tailwind.config.js`, add the `sidebar` color token set (`DEFAULT`, `border`, `text`, `muted`, `hover`, `active`, `active-text`) and the `success`/`warning`/`danger`/`info` status aliases, which map to emerald/amber/rose/sky. Keep the existing `brand` scale.
- [ ] 1.2 Register a `sidebar-collapsed:` Tailwind variant (via `plugin(({ addVariant }) => …)`) targeting `html[data-sidebar=collapsed] &`
- [ ] 1.3 Add a `variant` prop (default `solid`) to `components/icon.blade.php`, plus a Heroicons v2 outline map (24×24, stroke 1.75). The map needs the nav glyphs: `squares`, `rocket`, `folder`, `user-group`, `view-columns`, `clipboard-check`, `calendar`, `pencil-square`, `chat-bubbles`, `clock`, `chart-bar`, `identification`. It also needs the shell glyphs: `bars-3`, `x-mark`, `magnifying-glass`, `chevron-double-left`, `plus`, `link`, `chevron-up-down`.
- [ ] 1.4 Confirm that existing solid `<x-icon>` call sites render unchanged (spot-check dashboard, releases, board)

## 2. Navigation Registry

- [ ] 2.1 Create `App\Support\Navigation` with the full group/item catalogue from design §2: key, label, route, active patterns, outline icon, and accent color class
- [ ] 2.2 Implement `groups(User $user)`. Move the role rules out of `navigation.blade.php` verbatim: limited access → the six work surfaces; Performance → `isLead()`; Users → `canManageUsers()`. Drop empty groups.
- [ ] 2.3 Implement `createActions(User $user)`: "New release" (only if `canManageReleases()`), "New event", "New meeting note"
- [ ] 2.4 Implement `isActive(array $item)` using `request()->routeIs(...$patterns)`
- [ ] 2.5 Add a view composer in `AppServiceProvider` sharing `$navGroups` and `$createActions` with `layouts.sidebar` and `layouts.command-palette`

## 3. Shell CSS

- [ ] 3.1 Add `@layer components` classes to `resources/css/app.css`: `.sidebar`, `.nav-group-label` (`slate-500` uppercase, 11px), `.nav-item` (compact 32–36px row, hover `sidebar-hover`), `.nav-item-active` (`brand-50` background, `brand-700` text, 2px left accent bar), `.nav-tooltip`, `.kbd`
- [ ] 3.2 Add collapsed-state rules: sidebar width `w-64` → `w-[4.5rem]`, content padding `lg:pl-64` → `lg:pl-[4.5rem]`, labels and headings `sr-only`, tooltips enabled, all via `sidebar-collapsed:`
- [ ] 3.3 Add `motion-reduce:transition-none` to every sidebar, drawer, and palette transition

## 4. Sidebar

- [ ] 4.1 Create `layouts/sidebar.blade.php` with three parts:
  - a workspace header: a gradient brand mark plus "Release Planner" linking to the dashboard, with a collapse toggle (`chevron-double-left`, rotates when collapsed, `aria-label` and `aria-pressed`);
  - a Create button using `<x-dropdown>`, listing `$createActions` with icons, which opens to the right in collapsed mode;
  - `<nav aria-label="Main">` as the only scrolling region. It renders each group as an `aria-labelledby` heading plus a `<ul>`. Overview has no visible heading.
- [ ] 4.2 Render each item as follows:
  - its outline icon tinted with the item's accent color (`aria-hidden`);
  - its label in a `<span>`, so `>Performance<` stays literal for the existing test;
  - `aria-current="page"` plus active classes when `Navigation::isActive()`;
  - a tooltip containing the label, shown on hover and focus only when collapsed.
- [ ] 4.3 Position the sidebar `fixed inset-y-0 left-0 z-50`. Below `lg` it is an off-canvas drawer (`-translate-x-full` ↔ `translate-x-0`) with a close button. At `lg` and up it is `translate-x-0`.
- [ ] 4.4 Close the mobile drawer when a nav link is clicked

## 5. App Shell & Top Bar

- [ ] 5.1 Register an `appShell` Alpine component in `resources/js/app.js`:
  - state: `mobileOpen`, `toggleCollapse()`;
  - collapse writes `data-sidebar` on `<html>` and `localStorage['rp.sidebar']`, both inside `try/catch`;
  - while the drawer is open, lock body scroll and close on Escape;
  - return focus to the menu button when the drawer closes.
- [ ] 5.2 Add an inline pre-paint `<script>` in the `<head>` of `layouts/app.blade.php` that reads `rp.sidebar` inside `try/catch` and sets `data-sidebar="collapsed"`
- [ ] 5.3 Restructure `layouts/app.blade.php` into sidebar + mobile backdrop + a content column (`lg:pl-64`) containing the top bar, `$header`, flash, and `<main>`. Move the `full-height` classes onto the content column and keep `sticky-header` at `top-16`.
- [ ] 5.4 Rewrite `layouts/navigation.blade.php` as a slim `h-16` utility bar with:
  - a mobile menu button (`bars-3`, `aria-expanded`, `aria-controls`);
  - a command-palette search trigger showing "Search or jump to…" with a `⌘K` `.kbd` hint;
  - a Quick links icon button (`aria-label="Quick links"`) dispatching `toggle-quick-links`;
  - the existing user dropdown, unchanged in content.

  Remove the inline `$nav` array and the old mobile menu.
- [ ] 5.5 Remove the floating right-edge handle from `partials/quick-links-drawer.blade.php`

## 6. Command Palette

- [ ] 6.1 Create `layouts/command-palette.blade.php`: a modal with backdrop, a search input (`role="combobox"`), and a results list (`role="listbox"`, grouped, icon per entry). Seed the entries from `@js(...)` using only this user's `$navGroups` and `$createActions`.
- [ ] 6.2 Implement case-insensitive filtering on label + group, ↑/↓ selection with `aria-activedescendant`, Enter → `window.location.assign`, Escape → close, and an empty state with "No results"
- [ ] 6.3 Add a global `⌘K`/`Ctrl+K` handler. It must ignore the shortcut when the target is inside `trix-editor` or `[contenteditable]`, or when the event is `defaultPrevented`. Also open the palette on the `open-command-palette` window event from the top-bar trigger.
- [ ] 6.4 Include the palette in `layouts/app.blade.php`

## 7. Tests

- [ ] 7.1 Unit tests for `Navigation`:
  - a lead gets 5 groups in order with all 12 items;
  - a viewer gets 4 groups (no Administration);
  - a developer and a QA user each get exactly Dashboard/Board/Tasksheet/Calendar/Notes/Meetings, with the Planning and Administration groups dropped;
  - every item's icon is unique;
  - `createActions` includes "New release" only for release managers.
- [ ] 7.2 Feature test: on `board.index` the Board link has `aria-current="page"`; on `events.show` Calendar is active; on `profile.edit` no item has `aria-current`
- [ ] 7.3 Feature test: a developer's page markup contains none of "Projects", "Teams", "Releases", "Activity", "Performance", or "Users", including inside the command-palette JSON, and contains no "New release"
- [ ] 7.4 Feature test: a lead page contains the "Planning" and "Administration" headings, the Create menu with "New release", and the Quick links button, and contains no floating quick-links handle
- [ ] 7.5 Run `php artisan test`. Existing `DevQaAccessTest`, `PerformanceEvaluationTest`, `QuickLinksTest`, and the rest all pass.

## 8. Visual & Accessibility Verification

- [ ] 8.1 Run `npm run build` with no Tailwind or Vite errors
- [ ] 8.2 Run a contrast check: every nav label is ≥4.5:1 and every accent icon is ≥3:1 against the white, `slate-100` hover, and `brand-50` active backgrounds. Adjust shades if any fail.
- [ ] 8.3 Check in the browser at desktop width:
  - expanded and collapsed states, with collapse persisting across navigation and no flash of the expanded layout on load;
  - tooltips in the collapsed state;
  - the Create dropdown not clipped in either state.
- [ ] 8.4 Check at mobile width:
  - the drawer opens and closes via the button, backdrop, Escape, and following a link;
  - body scroll is locked while open;
  - focus returns to the menu button.
- [ ] 8.5 Check the command palette: `⌘K` opens it, typing "cal" + Enter navigates to Calendar, and `⌘K` inside a Trix editor still inserts a link
- [ ] 8.6 Regression-check the layout-sensitive pages:
  - `releases.show` (sticky sub-header);
  - `activity.index` (viewport-height panes);
  - `releases.create` (`lg:top-6` sticky aside);
  - `board.index` (wide columns, both sidebar states).
- [ ] 8.7 Keyboard pass: Tab reaches every sidebar item in group order with a visible focus ring, and reduced-motion mode disables the transitions
