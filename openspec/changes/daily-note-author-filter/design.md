## Context

A `Note` is one person's dated entry with a visibility of `private`, `shared`, or `specific` (shared with named recipients). `Note::scopeVisibleTo()` is the single access rule: everyone's shared notes, the viewer's own, and specific notes the viewer is a recipient of. `NoteService::visibleTo()` layers the date filters on top of that scope and is the one query behind both the Blade list and `GET /v1/notes`.

The controller already passes a `$users` collection to the view, but it is the *recipient picker's* list — active users **excluding the viewer**. It is the wrong set for an author filter, where the viewer is a perfectly valid choice.

## Goals / Non-Goals

**Goals:**
- Filter the notes list to one author, composing with the existing date filters.
- Offer only author options that can return something.
- Keep the visibility rule the sole gate on what is readable.

**Non-Goals:**
- No multi-select, no negation, no body search.
- No change to visibility, recipients, or note authorization.

## Decisions

1. **The author filter is an additional constraint, never an alternative one.** It is applied to the builder *after* `scopeVisibleTo()`, so it can only ever narrow the viewer's existing set. Filtering by a colleague returns their shared notes and the specific ones they addressed to the viewer — never their private ones. This is the same shape as the meeting-note person filters, and it is the property worth a test rather than an assumption.

2. **The dropdown lists authors of visible notes, not all users.** Building it from `Note::visibleTo($viewer)` distinct authors has two benefits over listing every active user: no option returns an empty list, and the control never reveals that someone has notes the viewer cannot read. The viewer appears in it whenever they have written a note, which is the common case — so the filter doubles as "just mine" without a separate control.

3. **The options are built from *all* visible notes, ignoring the other filters.** If the author list narrowed to match the current date range, changing the range could strand the selected author outside the list and silently drop the filter. The two controls stay independent.

4. **Authors are loaded `withTrashed()`.** `Note::author()` already does, and a departed colleague's shared notes remain visible — so their name must remain selectable rather than the filter listing an id it cannot label.

5. **`author` is a user id, validated as an integer only.** No `exists:users,id` rule: an unknown or unreadable id should return an empty list, not a validation error that reveals whether that user exists.

## Risks / Trade-offs

- [A distinct-authors query on every list render] → One indexed lookup over the viewer's already-scoped notes, on a page that is paginated at 15; negligible.
- [Author options and note visibility could drift if the dropdown were built separately] → Both derive from the same `visibleTo` scope, so a change to the access rule moves them together.
- [A viewer with no visible notes sees an empty author dropdown] → Correct, and the list beneath it is empty for the same reason; the control renders with just the "Anyone" option.
