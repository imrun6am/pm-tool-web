## 1. Database & Model

- [x] 1.1 Migration: nullable `standup_attended` boolean on `tasksheet_entries`, indexed with `date` for the attendance filter — nullable so "not yet marked" stays distinct from "did not attend" (design decision 1)
- [x] 1.2 `TasksheetEntry`: cast `standup_attended` to a nullable boolean; `attendedStandup()`, `missedStandup()`, `isStandupUnmarked()` helpers so views never rely on truthiness or `??` against a three-valued field
- [x] 1.3 Keep `standup_attended` **out** of `$fillable` — the only write path is the authorized action in task 2.2 (design decision 3)
- [x] 1.4 Run the migration and verify existing rows read as not-yet-marked

## 2. Standup Verification

- [x] 2.1 `TasksheetEntryPolicy@verifyStandup`: leads only — deliberately not `update`, which also grants a member their own row
- [x] 2.2 `TasksheetService::setStandupAttendance()`: resolve-or-build the row via `resolveEntry()` so marking a member with no row creates it; refuse a non-lead actor; refuse a full-day leave row
- [x] 2.3 `TasksheetService::save()`: clear `standup_attended` alongside the task fields when a row becomes a full-day leave (design decision 4)
- [x] 2.4 Verify a member's row save cannot set, change, or clear attendance even when the field is submitted
- [x] 2.5 Route + controller action for the inline toggle, taking `(team_id, user_id, date)` — separate from the row upsert so a lead's click cannot clobber a member's in-progress content (design decision 2)

## 3. Filtering

- [x] 3.1 One shared filter-parsing helper reading attendance / fill status / leave / member from the request, used by both surfaces so they cannot disagree on what a value means (design decision 5)
- [x] 3.2 Daily sheet: apply the filters over the assembled row collection — a query cannot express "row not yet created", which the *empty* and *unmarked* filters must match
- [x] 3.3 Per-user history: apply attendance / fill status / leave as query filters on the existing Builder, composing with team and date range
- [x] 3.4 Fill-status filter maps onto the model's existing `isFullyFilled()` / `isPartiallyFilled()` / no-row predicates rather than re-deriving them (design decision 6)
- [x] 3.5 Filter bar on the daily sheet: attendance, fill status, leave, member + Clear all
- [x] 3.6 Filter bar on the per-user history page, alongside the existing team and date-range controls

## 4. PDF Reports

- [x] 4.1 Add `barryvdh/laravel-dompdf` via composer
- [x] 4.2 `TasksheetService`: report query covering team + range, team + single day, and member + range as one bounded query (design decision 9)
- [x] 4.3 Dedicated print Blade view — plain tables authored for dompdf, not the Tailwind screen layout
- [x] 4.4 Report route + controller action returning a download response, carrying the active filters through
- [x] 4.5 Authorization: team-wide is lead-only; a member may export only their own rows
- [x] 4.6 **Omit `feedback` from any report a non-lead can obtain, including a member's own** — decide inclusion from the *requesting user*, not the report's subject (design decision 8)
- [x] 4.7 Export action on both lists, carrying the current filters into the download

## 5. API & Docs

- [x] 5.1 `TasksheetEntryResource`: expose the attendance state (three-valued, not a bare boolean)
- [x] 5.2 API: standup toggle endpoint mirroring the web action, same policy
- [x] 5.3 API: report endpoint, or document that the report is web-only — decide and state it rather than leaving it implicit
- [x] 5.4 API index/user endpoints accept the new filters, matching the web surfaces
- [x] 5.5 `docs/api-v1.md`: attendance field, toggle endpoint, filters, report; note that feedback is withheld from non-lead reports

## 6. Tests & Verification

- [x] 6.1 Feature tests: lead marks attended / not attended / unmarked default; marking a member with no row creates one that still counts as unfilled
- [x] 6.2 Feature tests: a member cannot set attendance; a member's row save neither forges nor clears it; toggling preserves existing task content
- [x] 6.3 Feature tests: full-day leave refuses attendance and clears an existing value; half-day keeps the control
- [x] 6.4 Feature tests: each daily-sheet filter narrows correctly and they compose; "unmarked" and "empty" include members with no row
- [x] 6.5 Feature tests: the per-user history filters narrow correctly and compose with team + date range; a filter means the same on both surfaces
- [x] 6.6 Feature tests: PDF returns for a day, a range, and a member; a member cannot pull a team report; **a member's own report contains no feedback while a lead's does**
- [x] 6.7 Feature test: the report's rows match the filtered list's rows
- [x] 6.8 Run the full test suite and fix regressions
