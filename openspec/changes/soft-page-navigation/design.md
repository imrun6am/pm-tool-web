## Context

PM Tool is a server-rendered Laravel 13 + Blade app. Interactivity comes from Alpine.js 3 (registered in `resources/js/app.js` and started once), Trix 2 and a few inline `<script>` blocks: the toaster and confirmer factories in `layouts/`, `releaseForm` in `releases/form`, and the board's drag-and-drop IIFE. Every navigation is a full document load. The shell (sidebar, top bar, ⌘K palette, toasts, confirm modal, quick-links drawer) is re-rendered on every page, and the sidebar's active item comes from `App\Support\Navigation` on the server.

Every web form handler responds with a redirect (Post/Redirect/Get). The only non-redirecting writes are two JSON `fetch` endpoints: `PATCH board/tasks/{task}` and `POST ai/enhance`. Downloads are plain GET links or GET forms: `kpi-reports/{report}/download`, `releases/{release}/documents/{document}` and `tasksheet/report.pdf`.

## Goals / Non-Goals

**Goals:**
- No blank flash between pages, a crossfade where the browser supports it, and a top progress bar on every visit and form submit.
- Zero server changes. Everything keeps working with JS disabled, falling back to normal links and forms.
- Every existing widget keeps working after a soft visit, with no leaked or doubled global listeners.

**Non-Goals:**
- Rewriting the app as a client-rendered SPA, or adding Turbo Frames/Streams for partial updates.
- Keeping state across visits, such as persistent toasts or a persistent sidebar scroll element. The shell is re-rendered from the server each time.
- Dark mode. The app has none today, so the progress bar uses the light theme's brand tokens only.

## Decisions

### 1. Turbo Drive, not htmx `hx-boost`, Swup or bare cross-document view transitions

| Option | Blank flash | Progress bar | Fit with Blade + Alpine |
|---|---|---|---|
| **Turbo Drive** | Gone (body swap) | Built in, after a configurable delay | Alpine's MutationObserver re-inits the swapped body. Built-in scroll restoration, head merging (CSRF meta), script activation, `view-transition` meta. Made for the same "HTML over the wire" model as Trix. |
| htmx `hx-boost` | Gone | Needs a hand-rolled bar | Works, but history restore re-uses its own DOM snapshots (same Alpine problem as Turbo's cache), the head merge needs an extension, and form-redirect handling is weaker. |
| Swup | Gone | Plugin | Needs container markup on every page and plugins for head, scripts, forms and scroll. More moving parts for the same result. |
| `@view-transition { navigation: auto }` + NProgress | Chrome/Safari already hold paint, so the flash is mostly gone there | Bar only during TTFB, frozen once the new document commits | Least risk (still full loads), but no SPA feel: Alpine and fonts reboot on every click, and Firefox gets nothing. |

Turbo gives the most "app-like" result for the least custom code, and its failure modes are well understood.

### 2. Snapshot cache off, prefetch on

Turbo's snapshot cache clones the live, Alpine-initialized DOM. Restoring that snapshot would re-run Alpine over already-rendered `x-for` output, which leads to duplicated rows and stale bindings. Adapters exist, but they are fragile. `<meta name="turbo-cache-control" content="no-cache">` makes back/forward re-fetch clean HTML. Turbo still restores the scroll position from history state. Turbo 8's hover prefetch stays enabled (it is GET-only, and every GET route is read-only), so most clicks render almost instantly anyway.

### 3. Progress bar = Turbo's bar, restyled

`Turbo.config.drive.progressBarDelay = 0`, so the bar shows on every visit, as asked. `.turbo-progress-bar` is restyled in `app.css`: 2.5px tall, a `brand-500 → brand-400` gradient with a soft glow, and a z-index above the sticky top bar, the toasts and the modals. A custom Alpine bar would duplicate code that Turbo already drives from the real visit and submit lifecycle.

### 4. View transitions via Turbo's meta, scoped by CSS

`<meta name="view-transition" content="same-origin">` makes Turbo wrap renders in `document.startViewTransition`. The default root crossfade is shortened to about 150 ms. The sidebar and top bar get their own `view-transition-name`s, so they stay put instead of fading. Under `prefers-reduced-motion: reduce`, all `::view-transition-*` animations are disabled. Turbo also skips transitions when the browser lacks the API.

### 5. Alpine components own their global listeners

Turbo swaps `<body>`. Alpine's MutationObserver initializes the new body's components, but it only tears down removed nodes it marked itself (`_x_marker`), and `<body>` is never marked. So `app.js` keeps the outgoing body on `turbo:before-render` and calls `Alpine.destroyTree()` on it after `turbo:render`. That runs every `destroy()` hook and every `x-on:*.window` / `.outside` cleanup. Anything a component attaches to `window`, `document` or a `MediaQueryList` by hand must be removed in `destroy()`. The handle for removing it (a closure over an `AbortController`) must live in a field **declared in the component's data**: Alpine writes an undeclared `this.x = …` from a nested component to the outermost scope (`appShell`), where sibling instances overwrite each other.
- `appShell`: the desktop media-query listener. It also clears `html.overflow-hidden`, so a drawer that is open during navigation can't leave the page scroll-locked.
- `toaster`: the `window` `toast` listener. Without it, old detached toasters keep receiving events.
- `confirmer`: the `document` capture-phase `submit` listener. Without it, each visit adds another listener.
- `settingsNav`: the `IntersectionObserver` and the `window` scroll listener.
- `aiEnhance`: the `document` `trix-initialize` / `trix-change` listeners.

Inline `<script>` blocks in the body are re-executed by Turbo before Alpine's (async) observer runs. `window.toaster`, `window.confirmer` and `releaseForm` are therefore defined in time, and the board IIFE binds to the new board elements.

### 6. Confirmed forms re-submit through Turbo

`confirmer` currently calls `HTMLFormElement.prototype.submit`, which bypasses every `submit` listener and does a full reload. On confirm it now marks the form as confirmed and calls `requestSubmit()`. Its own capture listener lets the marked form through once, and Turbo handles the submit like any other. If `requestSubmit` is missing, it falls back to the old native submit.

### 7. Opt-outs are explicit markup

`data-turbo="false"` goes on the three download entry points and on the logout form. Logout should be a hard reload: it drops all in-memory state and starts the login page with a fresh session token. Turbo already ignores cross-origin links, `target="_blank"`, `download` links and `.pdf` URLs. The explicit attribute documents intent and also stops hover prefetch of download URLs.

### 8. Smooth scrolling is suspended during renders

`html { scroll-behavior: smooth }` would make Turbo's jump to the top, or its scroll restore, animate visibly. The `turbo:before-render` handler sets `scroll-behavior: auto` inline, and `turbo:load` clears it on the next frame.

## Risks / Trade-offs

- [A page adds a component with a global listener and no `destroy()`] → It leaks across visits. Mitigation: the pattern is documented in `app.js`, next to the existing components.
- [A future POST handler returns HTML with status 200 instead of redirecting] → Turbo refuses to render it. Mitigation: all current handlers redirect, and Laravel's validation redirects back. A new handler like that should redirect, or its form should carry `data-turbo="false"`.
- [Hover prefetch can serve a page up to 10 s old if a background `fetch` (board drag) changed data in between] → This is only a minor staleness risk on an internal tool. Prefetch can be turned off with `<meta name="turbo-prefetch" content="false">` if it ever matters.
- [Toasts showing during navigation disappear with the old body] → This is acceptable: flashes for the new page arrive with it.
- [Full-height pages scroll an inner `<main>`, and Turbo only restores window scroll] → The inner scroll resets to the top on back. This is the same as today's full reload.

## Migration Plan

This is a frontend-only change. Deploy with `npm run build`. To roll back, remove the Turbo import, or add `<meta name="turbo-visit-control" content="reload">` to the layout, and every navigation goes back to a full load.

## Open Questions

- None blocking. If the team adds dark mode later, the progress bar colors should move to CSS variables.
