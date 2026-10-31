> Implemented and verified. 80 tests pass (8 new). Live smoke: releases list shows all with status,
> completed release renders Markdown notes + Reopen, completed releases are hidden from the
> dashboard, and completing an ongoing release works end-to-end.

## 1. Data & model

- [x] 1.1 Migration: add `completed_at`, `completed_by` (nullOnDelete), `completion_notes` to `releases`
- [x] 1.2 `Release`: fillable + `completed_at` cast; `isComplete()`; `ongoing`/`completed` scopes; `completedBy` relation; `renderedCompletionNotes()` via safe `Str::markdown()`

## 2. Hide from planning

- [x] 2.1 `DashboardController`: exclude completed from the timeline query and the per-team overlap query
- [x] 2.2 `OverlapChecker::conflictsFor()`: exclude completed releases

## 3. Actions & list

- [x] 3.1 `ReleaseController@complete` (notes, completed_by, completed_at) and `@reopen` — admin-gated
- [x] 3.2 `ReleaseController@index`: all releases with status + project/team/year filters (auth read)
- [x] 3.3 Routes: `GET releases` (index), `POST releases/{release}/complete`, `POST releases/{release}/reopen`

## 4. UI

- [x] 4.1 Releases index view: table with status badge + filters
- [x] 4.2 Release page: completion panel (mark-complete form when ongoing; badge + who/when + rendered notes + reopen when done)
- [x] 4.3 Nav: "Releases" link → releases.index

## 5. Seed, test, verify

- [x] 5.1 Seeder: mark one demo release complete with Markdown notes
- [x] 5.2 Feature tests: complete sets state + notes; non-admin blocked; completed hidden from dashboard + no overlap warning; reopen restores; index filters by status
- [x] 5.3 Migrate, build, run full suite, live smoke, then check off tasks and `openspec validate`
