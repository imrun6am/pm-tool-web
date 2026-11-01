## Why

Soft navigation (`soft-page-navigation`) removed the blank flash between pages, but every in-page action still re-renders the whole page: a create/update/delete form, a status toggle, a pin, or a delete behind the confirm modal. The body is swapped, scroll jumps to the top, and open UI (the quick-links drawer, an expanded row) resets. Users expect these actions to update only the part of the page they touched, in place.

## What Changes

- Add a reusable **live region**, `<x-live-region id="…">` (a Turbo Frame with `target="_top"`). Every non-GET form inside it submits in the background, and only that region is replaced with the server's fresh HTML. Links and GET filter forms inside it still navigate the whole page.
- **No controller changes.** Actions keep their Post/Redirect/Get responses (`back()->with('success', …)`). Turbo follows the redirect and extracts the matching region from the redirected page.
- **Toasts** from session flashes are carried in a `#flash-toasts` JSON block in the layout. When a region updates, `app.js` reads it from the response and raises the toasts.
- The **top progress bar** also runs for region submissions.
- If the redirected page doesn't contain the region (for example, deleting from a detail page redirects to the index), Turbo falls back to a whole-page soft visit.
- **Scroll is kept** on every other action. With `turbo-refresh-scroll=preserve`, any remaining form that redirects back to the same page keeps its position, and on a validation failure the first invalid field is scrolled into view.
- Converted first: notes (create, edit, delete), users (activate/deactivate, delete), performance competencies (activate/deactivate, delete), quick links in the drawer (add, edit, pin, delete), and the release tasks panel (add task/subtask, change status, delete).

## Capabilities

### New Capabilities
- `in-page-actions`: background form submissions that update only the affected region, with toasts, inline validation errors, progress feedback and a whole-page fallback.

### Modified Capabilities
<!-- None: server responses are unchanged. -->

## Impact

- **Frontend**: `resources/js/app.js` (region submit hook, toast bridge, progress bar, frame-missing fallback, Alpine teardown), `resources/css/app.css`, new `components/live-region.blade.php`, `layouts/toasts.blade.php`, `layouts/app.blade.php` (refresh-scroll meta), and the converted views: `notes/index`, `users/index`, `performance/competencies/index`, `partials/quick-links-drawer`, `releases/show`.
- **Backend**: none. Authorization and validation are unchanged and still server-side.
- **Tests**: feature tests that pin the region contract. Converted pages render their region, and a Turbo-style submission redirects back to the referring URL, where the region shows the new state and the flash toast.
