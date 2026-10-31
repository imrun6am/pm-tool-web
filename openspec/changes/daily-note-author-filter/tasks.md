## 1. Query

- [x] 1.1 `NoteService::visibleTo()`: accept an `author` filter applied **after** `scopeVisibleTo()`, so it can only narrow the viewer's set (design decision 1)
- [x] 1.2 `NoteService::authorsVisibleTo()`: distinct authors of the viewer's visible notes, `withTrashed()`, ordered by name — built from all visible notes, ignoring the other filters (decisions 2–4)
- [x] 1.3 `normalizeFilters()` (or the callers) carries `author` through as a nullable int

## 2. Web

- [x] 2.1 `NoteController@index`: validate `author` as a nullable integer only — no `exists` rule, so an unknown id yields an empty list rather than confirming the user exists (decision 5)
- [x] 2.2 Pass the author options and the selected author to the view; keep the recipient picker's `$users` separate — it excludes the viewer and is the wrong set here
- [x] 2.3 Author select in the notes filter bar, included in the existing Clear action

## 3. API & Docs

- [x] 3.1 `Api\V1\NoteController@index`: accept and pass `author`
- [x] 3.2 `docs/api-v1.md`: document the `author` param and that it narrows within visibility

## 4. Tests & Verification

- [x] 4.1 Feature tests: filtering by an author narrows the list; filtering by yourself includes your private notes; author composes with day and range filters
- [x] 4.2 Feature test: **filtering by a colleague never surfaces their private notes**, while their shared and viewer-addressed notes are listed
- [x] 4.3 Feature test: an author with nothing visible yields an empty list, not an error
- [x] 4.4 Feature tests: the author options list only authors of visible notes, includes the viewer, and keeps a soft-deleted author
- [x] 4.5 API test: `GET /v1/notes?author=` matches the web list
- [x] 4.6 Run the full test suite and fix regressions
