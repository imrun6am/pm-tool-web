## Context

Events are created at `events/create` and edited at `events/{event}/edit`. Both are full pages that wrap `events/form.blade.php` in settings-style `x-form-section`s. The calendar's "New event" button, its clickable day cells and the ⌘K "New event" action all go to the create page. The app already has one slide-over, the quick-links drawer, and it now navigates with Turbo Drive (soft-page-navigation).

## Goals / Non-Goals

**Goals:** create and edit without leaving the page; one drawer partial for both modes; server-side validation and old input, just as they work today; working deep links.

**Non-Goals:** AJAX/JSON submission, Turbo Frames, or editing from the calendar grid itself (event chips still open the event page). The API is not touched.

## Decisions

1. **Server-rendered drawer, normal form POST.** The drawer (`events/_drawer.blade.php`) is rendered into the calendar page (create) and into the event page (edit, only when `@can('update')`). The form posts as it does today, and Turbo turns the redirect into a soft navigation. A Turbo Frame or JSON flow was considered. It would need a frame-aware create/edit endpoint, plus special handling to get validation errors back into the frame. A normal POST keeps `EventRequest`, `old()` and the error rendering exactly as they are.
2. **Reopen via a hidden `_drawer` marker.** The form carries `_drawer=event`. Laravel's validation redirect goes `back()` to the page the drawer lives on, and the drawer opens itself when `$errors->any() && old('_drawer') === 'event'`. A `new`/`edit` query flag opens it for deep links. This is the same pattern the quick-links drawer uses with its session flag.
3. **One Alpine component, event-driven.** The drawer listens for `open-event-drawer` on window, with an optional `detail.date`. "New event", day cells and Edit dispatch it, and each keeps its `href` as a fallback. The component only uses `x-on:*.window` listeners, which Alpine removes when Turbo swaps the body, so nothing leaks across visits.
4. **Stacked form layout.** `events/form.blade.php` is now only used by the drawer, so it drops the outer card and the side-by-side `x-form-section` layout for stacked sections, keeping the same fields and names.
5. **Redirects.** `create` → `calendar.index` (month of `date`, `new=<date>`). `edit` → authorize → `events.show` with `edit=1`. `store` → the calendar month of the new event. The standalone pages are deleted rather than kept as unreachable duplicates.

## Risks / Trade-offs

- [The calendar now loads the release and active-user lists on every visit] → These are small queries the create page already ran, and the drawer needs them.
- [The page-level validation summary under the header also shows while the drawer is open] → It is accurate and short, and the fields carry inline errors in the drawer.
- [Bookmarks to `/events/create` change behaviour] → They still work: they redirect into the drawer.
