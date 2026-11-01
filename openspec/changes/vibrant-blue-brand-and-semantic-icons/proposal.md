## Why

The user asked for three things. In their words:

> enhance the design of sidebar and a symentic color in all icon, change the default color to a eyecatchy vibrant color of blue shade

Today the brand is a muted indigo, and icon colors are picked ad hoc on each page. The same domain (Releases, Board, Calendar…) shows up in different hues on different screens, and status icons don't consistently mean the same thing.

## What Changes

- **Brand scale becomes a vibrant "electric" blue.** `brand-600` is `#0b63f6`: 5.1:1 on white and 4.7:1 on `brand-50`, so it's AA for text and buttons. `brand-700` (`#0a50d0`) is used for hover and active text.
  - The indigo-tinted button shadows, the sidebar active tokens, and the `info` alias follow it.
  - A few hard-coded indigo hex/classes in shared chrome follow it too: the brand mark, charts' default color, and `.prose` links.
- **One semantic palette, `App\Support\Palette`:**
  - **Tones:** each has `text`, `soft` (tinted tile), `solid` and `ring` classes, written out in full for Tailwind.
  - **Semantic aliases:** `success` → emerald, `warning` → amber, `danger` → rose, `info` → brand blue, `neutral` → slate.
  - **Domain hues:** dashboard → brand, releases → violet, projects → amber, teams → pink, board/tasks → sky, tasksheet → emerald, calendar → rose, notes → orange, meetings → teal, activity → cyan, performance → fuchsia, KPI → indigo, users → slate, notifications → brand, AI → purple.
- **Applied through shared components, not page by page:**
  - `Navigation` items get a `tone`. Their `color` comes from the palette, so the sidebar, ⌘K and Create menu agree.
  - `x-page-header` shows the active section's icon in its domain hue. It is looked up from `Navigation`, and pages can override or turn it off.
  - `x-stat`, `partials/stat-tile`, `x-action-btn` and `x-empty-state` resolve `tone` through the palette and accept the semantic aliases. `x-empty-state` defaults to the current section's hue.
- **Sidebar redesign:**
  - **Header:** a vibrant gradient brand mark with the workspace subtitle.
  - **Nav items:** each icon sits in a soft tile of its domain hue.
  - **Active item:** a brand-tinted row with an accent bar, and the icon tile turns solid.
  - **Group headings:** a hairline rule and clearer hover and focus-visible states.
  - **Footer:** a shortcuts hint (⌘K, C, ?) and the collapse control. The collapsed rail and mobile drawer keep working.
  - Role filtering and active-route patterns are unchanged.

- **Modern shell and motion.** The user asked:

  > sidebar and header design need to be modern show transition effect on menu change

  So the shell gets:
  - **Sliding highlight:** the active item's highlight is its own `nav-pill` element. On a soft navigation it morphs from the old item to the new one, using Turbo's existing view transition with `view-transition-name: nav-pill`.
  - **Smooth hover:** hover eases the row and lifts the icon tile.
  - **Collapse:** labels fade and narrow as the rail collapses, and stay in the accessibility tree.
  - **Header:** the top bar is a frosted layer, and the page header becomes a band that fades into the canvas with a faint glow in the section's hue.
  - **Page changes:** the header and content fade out and rise in. Same-page refreshes (`html[data-vt=refresh]`) don't animate, and nothing animates under reduced motion.
  - **No new library:** this builds on Turbo's view transitions.

## Impact

- **Config:** `tailwind.config.js` (brand scale, shadows, sidebar tokens, `info`, content path `app/Support/*.php`).
- **Support:** new `app/Support/Palette.php`; `app/Support/Navigation.php` (`tone` per item, `color` from the palette, `activeItem()`).
- **Views:** `layouts/sidebar`, `components/{page-header,stat,action-btn,empty-state}`, `partials/stat-tile`.
- **CSS:** `resources/css/app.css` (sidebar and nav classes, brand mark, view-transition choreography, `.app-page-header`).
- **Layout and JS:** `layouts/app` (`app-page-header`/`app-content` hooks), `layouts/navigation` (search pill), `resources/js/app.js` (`data-vt` refresh flag in `turbo:before-render`).
- **Tests:** `tests/Unit/PaletteTest.php`; navigation tests still pass unchanged.
