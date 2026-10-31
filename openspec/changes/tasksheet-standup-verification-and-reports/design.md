## Context

The tasksheet has two surfaces, built very differently — a distinction the filtering work has to respect:

- **The daily team sheet** (`/tasksheet`) is *one team × one day*. Its rows are not a query result: `TasksheetService::rowUsersFor()` builds a **Collection** of the people whose membership covered that date (developers and QA), unioned with anyone holding a saved row that day, while `entriesFor()` returns the saved rows keyed by user. A member with no row yet still gets a row on screen. So "filter the daily sheet" means filtering a collection of (user, maybe-entry) pairs, not adding `where` clauses.
- **The per-user history page** (`/tasksheet/users/{member}`) *is* a query — `history()` returns a Builder already filtered by team and date range.

Two existing rules constrain the design:

1. **`feedback` belongs to leads alone.** `TasksheetService::save()` applies it only when the actor `isLead()` *and* the key was actually submitted, so a member's save can neither forge nor blank it. Any new lead-only field must inherit that stance, and any new export must not become a way around it.
2. **History does not rewrite itself.** Past sheets list whoever the membership covered on that date, including people since removed or deleted.

`User::isLead()` is admin, CTO, tech lead, and team lead — the predicate already gating `feedback` and lead corrections.

## Goals / Non-Goals

**Goals:**
- Record standup attendance per member per day, settable only by leads, toggled inline from the sheet.
- Keep "not yet marked" distinguishable from "did not attend".
- Filter both lists by attendance, fill status, and leave; filter the daily sheet by member too.
- Export a day, a range, or one member as a PDF, without opening a hole in the `feedback` rule.

**Non-Goals:**
- No attendance analytics, rate, or streaks; no absentee notifications.
- No scheduled or emailed reports; no CSV/Excel.
- No change to who may fill a row, or to the leave semantics.

## Decisions

1. **`standup_attended` is a nullable boolean, not a boolean defaulting to false.** Three states are needed — attended, did not attend, **not yet marked** — and the filter depends on telling the third apart from the second. A `false` default would silently assert that every historical row is a no-show the moment the column is added. Existing rows therefore read as unmarked, which is true.

2. **The inline toggle is its own endpoint, not the row upsert.** The upsert is the *member's* form and carries every task field; routing a lead's attendance click through it risks clobbering a member's in-progress content, and would need the whole payload just to flip one flag. A dedicated endpoint takes `(team_id, user_id, date)` — the same triple the upsert uses — and reuses `TasksheetService::resolveEntry()` so that **marking attendance for a member who has no row yet creates that row**. This is the common case at standup time: the sheet is often marked before members have filled anything in.

3. **Lead-only enforced in the service, mirroring `feedback`.** A new `setStandupAttendance()` refuses a non-lead actor, and the field is excluded from the fillable set the member's `save()` path touches — so the *only* way it changes is through the authorized action. Authorization is a new `TasksheetEntryPolicy@verifyStandup` rather than reusing `update`, because `update` deliberately also grants a member their own row.

4. **Attendance is not applicable on a full-day leave row.** Casual/sick leave already clears the task fields; someone legitimately off work was not a standup no-show, and recording them as one would misread later. The toggle is refused for a full-day leave row and the sheet shows the leave state in place of the checkbox. Half-day leave keeps the checkbox — that member still works and still attends.

   Consequence handled deliberately: if a row is marked absent from standup and *then* set to full-day leave, the stored attendance is cleared as part of the same clearing rule that empties the task fields.

5. **Filters are applied where each list actually lives.** The per-user history gains query filters on its existing Builder. The daily sheet's filters run over the assembled collection, because its rows include members with no entry at all — a `where` clause cannot express "row not yet created", which is exactly what the *empty* fill-status and *unmarked* attendance filters must match. Both paths read their inputs through one shared filter-parsing helper so the two surfaces cannot disagree about what `attendance=unmarked` means.

6. **Fill status reuses the model's existing predicates.** `isFullyFilled()`, `isPartiallyFilled()` and the absence of a row already encode complete / partial / empty. The filter maps onto those rather than re-deriving "filled" from column counts, so there is one definition.

7. **PDF via `barryvdh/laravel-dompdf`, rendering a dedicated print Blade view.** Chosen over Browsershot (needs Node + headless Chrome on every machine and in CI) and over a print-friendly HTML page (not a one-click download). The print view is deliberately a *separate* template from the interactive sheet: the screen view is Trix-rendered HTML with inline forms, none of which belongs in a document.

8. **`feedback` is omitted from any PDF a non-lead can obtain — including a member's export of their own record.** This is the decision most easily got wrong. A member may export their own history, and `feedback` lives on their own rows; but it is the lead's private note *about* that member, and the screen already hides it from them. The export must not become the read path that the UI denies. The report therefore takes the *requesting user* into account, not the subject of the report.

9. **Report scopes are one route with three shapes.** `team + single day`, `team + range`, and `member + range` are the same query with different bounds, so they are one endpoint rather than three: a member filter narrows it to one person, and a single day is a range whose ends match. Team-wide export requires `isLead()`; a member may export only themselves — the same rule the per-user page already applies.

## Risks / Trade-offs

- [Marking attendance creates a row that did not exist] → Intended (decision 2), but it means a sheet can hold a row with attendance and no task content. The fill-status filter already models that as *empty*, and the row reads as unfilled, which is accurate.
- [A large date-range PDF could be slow or huge] → The report is bounded by the requested range and, for team-wide exports, one team. Worth a sanity cap on span if it proves a problem; not pre-emptively limited.
- [Two filter code paths (collection vs query) could drift] → Mitigated by decision 5's shared parsing helper and by testing the same filter against both surfaces.
- [dompdf renders a subset of CSS] → The print view is plain tables and text, authored against dompdf's capabilities rather than reusing the Tailwind screen layout.
- [Nullable boolean is three-valued and easy to misread in PHP] → `null` vs `false` must be compared strictly; helper methods on the model (`attendedStandup()` / `isStandupUnmarked()`) keep `??` and truthiness out of the views.
