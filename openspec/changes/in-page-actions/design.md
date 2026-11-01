## Context

`soft-page-navigation` added Turbo Drive. Every form is already submitted with `fetch` and the redirected page swaps `<body>` without a reload. Each action still repaints the whole page, though: scroll resets and client UI state outside the action is lost. Web controllers consistently answer writes with `back()->with('success', …)` (or a `redirect()->route(…)`). The desktop API returns JSON from separate controllers. Validation errors render inline via `x-field` / `@error` / `@invalid`, and success, error and warning flashes become toasts (`layouts/toasts`).

## Goals / Non-Goals

**Goals:**
- Create, update, delete and toggle actions update only the affected DOM, without a reload, using the same library as navigation.
- Keep thin controllers, the service layer, server-side authorization and inline validation exactly as they are.
- Provide one documented pattern that any page can adopt with markup alone.

**Non-Goals:**
- Real-time broadcasting to other users (Turbo Stream broadcasts or websockets).
- Optimistic UI. The region shows the server's result after the round trip.
- Converting every page in this change. The rest follow the pattern, and until then they keep whole-page updates with scroll preserved.

## Decisions

### 1. Turbo Frames as the default, Streams only when needed, no htmx

| | Turbo Frames (chosen) | Turbo Streams | htmx (`hx-boost` + `hx-target`/`hx-swap`) |
|---|---|---|---|
| Controller change | None. The redirect is followed and the matching `<turbo-frame>` is cut from the page. | A branch per action (`if wantsTurboStream`) returning stream templates. | A partial or `HX-Request` branch per action, or `hx-select` on the full page. |
| Blade change | Wrap the region once. | Extract a partial per target and add stream templates. | `hx-*` attributes on each form, plus target ids. |
| Second library | No | No | Yes. It would duplicate Turbo Drive's navigation, history and progress handling, and both would intercept the same forms. |
| Multi-region updates | One region per action (pick a region big enough). | Any number of targets. | Out-of-band swaps. |

Frames give region updates with zero server change, because the response contract (PRG) stays the same. Streams remain the escape hatch for an action that must touch several disjoint regions or is too expensive to re-render as a page. Nothing in the first batch needs that, so no stream helper is added yet. htmx would mean running two navigation libraries side by side.

### 2. `<x-live-region>` plus a submit hook, not per-form attributes

`<x-live-region id="users">` renders `<turbo-frame id="users" target="_top" data-live-region class="block">`. With `target="_top"`, links and GET forms (filters, pagination) keep navigating the whole page and updating the URL. A capture-phase `submit` listener in `app.js` sets `data-turbo-frame` to the region's id on any non-GET form inside it that doesn't set one itself, so that only the region updates. A form can opt out with `data-turbo-frame="_top"`. This keeps conversions to a single wrapper per page.

### 3. Toasts travel in the page, not in a stream

The layout renders the flash toasts both as the toaster's initial data and as `<script type="application/json" id="flash-toasts">`. On `turbo:frame-render`, `app.js` parses that block from the fetched HTML and dispatches a `toast` event for each entry. Controllers keep calling `->with('success', …)`.

### 4. Progress, teardown, fallback

- `turbo:submit-start` for a region submission shows Turbo's progress bar. It completes on `turbo:frame-render`, on `turbo:frame-missing`, or when a submission fails.
- `turbo:before-frame-render` wraps the render to `Alpine.destroyTree()` the outgoing region content first. Alpine only tears down removed nodes it marked, and a region outside any `x-data` root would otherwise leak its components.
- `turbo:frame-missing` → `preventDefault()` + `visit(response)`, so an action that redirects elsewhere renders as a whole-page soft visit instead of Turbo's "Content missing".
- `.live-region[aria-busy="true"]` dims slightly while a submission is in flight.

### 5. Scroll on whole-page actions

`<meta name="turbo-refresh-scroll" content="preserve">` makes any same-URL redirect (a Turbo "page refresh") keep scroll. The refresh method stays `replace`. Morphing was rejected because idiomorph would revert attributes that Alpine owns (`style="display:none"` from `x-show`, bound classes) and blank Trix editors. After such a render, if the page has `[aria-invalid="true"]`, the first invalid field is scrolled into view, so errors on long forms aren't hidden below the fold. Turbo also focuses the incoming page's `[autofocus]` element after a render. On a refresh that would scroll away (the profile form autofocuses its name field), so `turbo:before-render` removes `autofocus` from the new body when `session.view.isPageRefresh(visit)`.

## Risks / Trade-offs

- [Region-level updates re-render the whole page on the server] → This is acceptable for an internal tool, since the same query cost applies as today. A `Turbo-Frame` request header is available to skip the layout later if needed.
- [Something affected by an action sits outside its region, such as a header count] → Size the region to everything the action can change, or fall back to Streams for that action.
- [Focus is lost when the submitting button is replaced] → The region re-renders under the cursor, and keyboard users land on `<body>`. A follow-up could restore focus by element id.
- [A converted form's redirect target lacks the region] → Covered by the frame-missing fallback.

## Migration Plan

Frontend markup and JS only. To roll back a page, remove its `<x-live-region>` wrapper, and its forms go back to whole-page updates.

## Open Questions

- None blocking.
