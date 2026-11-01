## Why

Every link click and form submit in PM Tool is a full browser reload. The page goes blank, the stylesheet and fonts are re-evaluated, Alpine boots from scratch, and nothing shows that the next page is loading. The app looks good when a page is sitting still, but moving between pages feels like an old website, not a modern app. A light soft-navigation layer can give it a React/SPA feel without leaving server-rendered Blade.

## What Changes

- Add **Hotwire Turbo Drive** (`@hotwired/turbo`, about 25 KB gzipped) to `resources/js/app.js`. Same-origin link clicks and form submissions are fetched in the background and the new `<body>` is swapped in. There is no full reload and no blank flash.
- Add a **thin top progress bar** (Turbo's built-in bar restyled with the brand indigo gradient). It runs on every visit and every form submission.
- Crossfade old and new pages with the **View Transitions API** (Turbo's `view-transition` meta). It is disabled under `prefers-reduced-motion`, and browsers without support simply swap.
- Make the shell's Alpine components **safe to tear down and rebuild** on every visit: `appShell`, `toaster`, `confirmer`, `settingsNav` and `aiEnhance` get `destroy()` hooks so their global listeners don't leak or double-bind.
- The ⌘K command palette navigates with `Turbo.visit`. Confirmed (`data-confirm`) forms re-submit through Turbo instead of doing a native reload.
- Mark file downloads (KPI reports, release documents, the tasksheet PDF) and logout with `data-turbo="false"`, so they keep native browser behaviour.
- Disable Turbo's snapshot cache (`turbo-cache-control: no-cache`). Back/forward always re-fetch fresh, Alpine-free HTML, and Turbo restores the scroll position.
- No server, route or API changes. No new build tooling.

## Capabilities

### New Capabilities
- `soft-navigation`: in-app page transitions. Navigation without full reloads, the top progress bar, crossfade with reduced-motion support, and preserved behaviour of downloads, auth, history, forms, flash/toast messages and interactive widgets.

### Modified Capabilities
<!-- None: existing page behaviour is unchanged; this layers on top of it. -->

## Impact

- **Dependencies**: adds the `@hotwired/turbo` npm package (^8.0).
- **Frontend**: `resources/js/app.js`, `resources/css/app.css`, `resources/views/layouts/app.blade.php`, `layouts/toasts.blade.php`, `layouts/confirm.blade.php`, `layouts/navigation.blade.php` (logout), and the download links in `kpi-reports/index`, `releases/show`, `tasksheet/index` and `tasksheet/user`.
- **Backend**: none. Every web form handler already redirects. The only non-redirecting writes (board `PATCH`, `ai/enhance`) are `fetch` JSON calls that Turbo doesn't touch.
- **Tests**: no PHP test changes are expected. Verification is done in the browser.
