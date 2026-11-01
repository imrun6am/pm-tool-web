## Why

The user asked:

> all the dropdown content should be searchable

The app has 89 native `<select>`s across 33 views: projects, teams, releases, assignees, members, statuses, filters and more. Long lists such as people and releases mean scrolling, and native selects can't filter.

## What Changes

- **One progressive enhancement, `resources/js/searchable-select.js`,** upgrades every native single `<select>` in place. No view rewrites.
  - The real select stays in the form as the source of truth. It is hidden, and a button styled with the select's own classes takes its place.
  - The button opens a listbox panel. A type-to-filter search box appears when a select has more than 6 options; smaller lists keep type-ahead.
  - Choosing sets the select's value and fires `input` + `change`, so `x-model`, filter bars (submit on change) and `@change` handlers behave as before. `old()` values, `selected` attributes and `aria-invalid` (`@invalid`) carry over.
  - **Keyboard and ARIA:**
    - ↑/↓, PageUp/PageDown, Home/End, Enter, Esc, Tab, Alt+↓, and type-ahead;
    - typing on the closed control starts a search;
    - `combobox` → `listbox`/`option` roles with `aria-activedescendant`;
    - labels stay associated.
  - **Panel details:**
    - option groups render as headings, and disabled options can't be chosen;
    - a "No matches" empty state, with the matched text highlighted;
    - native required-field validation shows under the button.
  - **Robust:**
    - enhanced on load, after Turbo renders, and for selects added later (Alpine `x-for`/`x-if`, the fetched quick-capture form, in-page regions), through one observer;
    - never enhanced twice, and cleaned up when removed;
    - value changes made by scripts (`x-model`, `.value =`) update the button.
  - **Panel placement:** rendered in `<body>` with fixed positioning, so cards with `overflow-hidden` and table scrollers can't clip it. Interactions inside it don't close parent popovers. It becomes a bottom sheet under 640px.
  - **Opt out** with `data-native` on a select or an ancestor. Multiple selects and the board card's tiny "Move" select stay native.
- **`x-multi-select`** already had a search box. It's unchanged apart from inheriting the panel styles.
- Reduced motion turns off the panel animation.

- **Polish.** The user asked:

  > enhance all the dropdown design

  So every dropdown now shares one look:
  - **Recognisable picks:** people show an initials avatar (the same tone as `x-avatar`), and teams and projects show their colour dot. Dots come from a small `#ss-swatches` JSON in the layout; any option can set `data-color`.
  - **Clearing:** optional fields with an empty choice get a "×" in the field (Delete/Backspace from the keyboard).
  - **Highlight:** hover and keyboard focus use the brand blue (`#eef5ff`/`#0a50d0`).
  - **Motion:** the panel fades in and out, and the bottom sheet slides. All of it is off under reduced motion.
  - **Custom menus match:** `x-dropdown` (account and Create menus) gains ↑/↓ navigation, and it, `x-dropdown-link`, the notification bell, the tasksheet export popover and `x-multi-select` use the shared `.menu-panel`, `.menu-item` and `.menu-label` tokens, with the same search box as the selects.

## Impact

- **JS:** new `resources/js/searchable-select.js`, plus one import line in `resources/js/app.js`.
- **CSS:** `resources/css/app.css` (`.ss-*`).
- **Views:** `layouts/select-swatches` (new, included in `layouts/app`, fed by a composer in `EngagementServiceProvider`). Shared menu styling in `components/{dropdown,dropdown-link,multi-select}`, `layouts/{sidebar,notification-bell}` and `tasksheet/index` (one class swap).
- **Tests:** a feature test that representative pages still render their native selects with names and `selected` options, since the server contract is unchanged. Behaviour is checked in the browser.
