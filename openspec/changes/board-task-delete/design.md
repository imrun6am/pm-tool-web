## Context

The board (`BoardService::columns`) loads every top-level task matching the filters, with no pagination, and renders each as a card. Moves already run in the background: a `fetch` to a JSON endpoint from the page's inline script, followed by a DOM update and a toast. `TaskPolicy::delete` lets any user who can see a task delete it, through `DELETE tasks/{task}`. Whether that is right for Viewer is an open question, and it isn't settled here.

## Goals / Non-Goals

**Goals:**
- Admin-only single and bulk delete from the board, enforced on the server, on web and API.
- Select all that is honest about what it covers.
- In-place updates using the board's existing `fetch` approach.

**Non-Goals:**
- Changing who can use `DELETE tasks/{task}` (see Open Questions).
- Undo or soft delete. Tasks have no soft-delete column, and the confirmation says the delete is permanent.
- Bulk delete anywhere other than the board, or bulk actions other than delete.

## Decisions

**A new policy ability, not a change to `TaskPolicy::delete`.** `deleteFromBoard(User, ?Task)` is `isAdmin()`, and with a task it also requires a visible, top-level task. Tightening `delete` would change the task page and release panel for every role, and existing tests pin that behaviour. The optional-model form follows `TaskPolicy::create`, so the same ability answers "may this user use the feature?" for the view and the request, and "may they delete this task?" per record.

**Select all sends explicit ids; the server has no "delete all matching filters" mode.** The board isn't paginated, so the rendered cards are exactly the filtered set. Sending ids means the server deletes what the admin saw and confirmed. A filter-based mode would also delete cards someone added after the page loaded, and a filter mismatch between client and server could widen the delete. API clients get the same safety: read `GET /board?team_id=…`, then submit the ids.

**All or nothing.** Any bad id gives a 422 and deletes nothing, and the deletes run in one transaction. A stale board therefore tells the admin to reload instead of leaving a partial result to work out.

**Authorization order.** `BulkDeleteTasksRequest::authorize()` checks the role first, so a non-admin gets a 403 whatever they send and can't probe which ids exist. After validation, `passedValidation()` authorizes each loaded task.

**Per-row `exists` validation, capped at 1000 ids.** `ids.*` carries the `exists` rule, so each failure names its id. That is one query per id, which is acceptable because every id is then deleted anyway. The cap (`BoardService::MAX_BULK_DELETE`) bounds a single request's transaction; a board that large isn't expected.

**Deletes go through Eloquent, one model at a time.** The `deleting` hook removes subtasks and comments, and `RecordsActivity` writes the audit entries. A query-builder delete would skip both.

**Confirmation through the shared modal.** The `layouts/confirm` modal intercepts any form with `data-confirm`. Each card has a form with its message rendered by Blade; the bulk form's `data-confirm*` attributes are rewritten as the selection changes. The board's own submit listener runs on the confirmed re-submit, skips the first (already prevented) one, and calls `preventDefault()` before Turbo sees the event, then uses `fetch`.

**Selection state lives in the DOM.** The checkboxes are the source of truth and the counts are recomputed from them, so a Turbo restoration visit, which re-runs the inline script over a snapshot, needs no separate state.

**Collapsed Archive is out of select all.** It starts collapsed, so including it would delete cards the admin can't see. Select all counts only cards outside a collapsed column, read from the column's `data-collapsed` state because `x-show` only applies on the next frame. Collapsing the column unticks its cards, so a selection can't hold anything hidden. The bar reports how many were left out.

**Compact top section, shared column width.** Requested alongside this change, and the bulk bar would otherwise have added to the problem. The filters move into the page header's `actions` slot, and the summary and bulk controls share one slim row. `layouts/app.blade.php` tightens the header padding when `full-height` is set, which only the board uses, so no other page changes. The board also uses a narrower side gutter (`.app-container-tight`). On `lg` the columns are `flex-1` with a 12.5rem minimum instead of a fixed 19rem, so they fill wide screens and shrink on laptops. With a pointer, a card's Move menu and delete button float over the release chip on hover, so a narrow card still shows the release; on touch they stay in the row. The drag hint under the title is removed to save its line.

**Messages stay out of the service.** `BoardService::deleteCards` returns `{deleted_ids, subtasks_deleted}`. The web endpoint returns those fields as JSON and the page's script writes its toast; the API adds a `message`.

## Risks / Trade-offs

- [Admins lose work permanently, with no undo] → A confirmation stating the counts, and an activity entry holding a snapshot of each deleted task's attributes.
- [Notifications keep pointing at deleted tasks] → Existing behaviour for any task delete; `NotificationController` already handles a task that no longer exists.
- [Non-admins can still delete single tasks through `DELETE tasks/{task}`] → Called out in the proposal and raised with the product owner. Not a regression, but the admin-only rule covers only the board's new controls.
- [A large bulk delete holds a transaction for a while] → Capped at 1000 ids.

## Open Questions

- Should `DELETE tasks/{task}` (task page, release panel, API) also become admin-only, or at least exclude Viewer? This predates the change and is the product owner's call.
