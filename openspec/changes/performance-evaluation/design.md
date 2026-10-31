## Context

The app already captures a rich work record: `teams` with member pivots and an assigned `team_lead_id`; string roles on users (`admin`, `cto`, `team_lead`, `developer`, `qa`, `viewer`) with helpers including `isLead()`; a daily `tasksheet_entries` grid (work points, ticket count/points, leave, lead-only `feedback`); and `tasks` on a board with a status flow that includes `recheck` (rework) and `done`/`archive`. What it lacks is any structured judgement of *people*. Leads evaluate informally with no shared scale and no history.

This change adds a real-world, competency-based performance system layered on that existing data. It follows the established server-rendered Blade + Alpine + Tailwind pattern, thin controllers backed by services (`OverlapChecker`, `Timeline`, `HtmlSanitizer`), policy-based authorization, and the tasksheet's precedent for keeping a sensitive column (`feedback`) strictly lead-only. Charts in this app are hand-built with CSS bars and inline SVG (see the dashboard and the tasksheet productivity strip) — no JS charting library — and this feature keeps that constraint.

Three product decisions were fixed up front: **visibility is leads-only** (developers/QA never see performance data), the **scale is 1–5 with anchor labels**, and the score model is a **blended headline (weighted ratings) plus separate objective panels** sourced from tasksheet/board data.

## Goals / Non-Goals

**Goals:**
- A configurable competency catalog (seeded defaults) that is role-aware, category-tagged, and cadence-aware (daily vs weekly).
- A lead-only evaluation flow: the assigned team lead (or admin/CTO) rates active dev/QA members 1–5 per competency per period, with optional private notes and upsert semantics.
- Per-member and per-team analytics that blend a weighted rating score with separate objective panels drawn from existing tasksheet/board data, plus trends, coverage, and needs-attention.
- Strict server-side access control and privacy: no leakage to non-leads, no leakage into the shared activity feed.
- Reuse existing conventions (roles, policies, `isLead()`, day/period navigation, inline Alpine editing, CSS/SVG charts).

**Non-Goals:**
- No self-assessment or 360/peer review in v1 (lead → member only).
- No member-facing view of their own scores in v1 (leads-only was chosen).
- No monthly/quarterly formal review cycles, goals/OKRs, or PIP workflow — the cadence is daily/weekly ratings; longer roll-ups are read from the analytics, not separate objects.
- No notifications/reminders to evaluate in v1 (coverage surfacing substitutes).
- No folding objective metrics into the headline number (kept side-by-side by decision).
- No export/PDF of reviews in v1.

## Decisions

### 1. Two tables: a catalog and a scores ledger
`performance_competencies` (the catalog) and `performance_scores` (the ledger).

**`performance_competencies`**: `id`, `key` (unique slug), `name`, `description` (nullable), `category` (`behavioral|technical|delivery|growth`), `role_scope` (`developer|qa|both`), `cadence` (`daily|weekly`), `weight` (unsigned smallint, default 1, `>= 1`), `active` (bool, default true), `position` (int), timestamps.

**`performance_scores`**: `id`, `team_id` (FK, cascade), `user_id` (evaluatee FK, cascade), `evaluator_id` (FK, `nullOnDelete` — keep the score if the lead's account is later removed), `competency_id` (FK, restrict/`cascade` — see decision 8), `period_type` (`daily|weekly`), `period_start` (date), `period_end` (date), `score` (unsigned tinyint 1–5), `note` (nullable text), timestamps. Unique index on (`team_id`, `user_id`, `competency_id`, `period_start`). Secondary indexes: (`team_id`, `period_start`), (`user_id`, `period_start`).

Rationale: a fixed, typed catalog with a normalized score ledger mirrors the tasksheet's "one typed table, upsert per natural key" choice and keeps analytics as plain aggregate queries. Alternative — hardcoding competencies as a PHP const map (like `Task::STATUSES`) — rejected because the user wants a real-world, tunable framework; admins/CTOs must add/retire dimensions without a deploy. Alternative — EAV/JSON blob of scores — rejected: the shape is fixed and we need indexed aggregation.

### 2. Scores are team-scoped (per (team, member, competency, period))
A member on two teams is rated independently by each team's lead; `team_id` is stored on every score (not derived through the user) so history survives membership changes, exactly like `TasksheetEntry.team_id`. Analytics default to the team in view; a member's cross-team totals are out of scope for v1 (most members are on one team). This avoids the conflict of two leads fighting over one "Code Quality" cell.

### 3. Cadence drives the period; periods are normalized on the row
Each competency is `daily` or `weekly`. The score stores `period_type` + `period_start` + `period_end`:
- **daily** → `period_start == period_end ==` the calendar date.
- **weekly** → `period_start ==` Monday (`Carbon::startOfWeek(CarbonInterface::MONDAY)`), `period_end ==` Sunday of that week.

`period_type` is denormalized onto the score so a later change to a competency's cadence never reinterprets old rows. Weekly ratings normalize to the containing week regardless of which weekday they were entered. Future periods are rejected (`period_start > today`, or a week that has not started). Rationale: normalizing at write time makes every analytics query a simple `where period_type=? and period_start between ?..?` without per-row cadence logic. Alternative — store only a single `date` and infer the week at query time — rejected: brittle across drivers and timezones, and re-deriving weeks in SQL is ugly. Weeks are Monday-anchored explicitly (not locale-dependent) for determinism.

### 4. Access model: a `performance` middleware + a policy that scopes leads to owned teams
- Route middleware alias `performance` aborts 403 unless `User::isLead()` (admin, CTO, team_lead). It gates the whole section and the nav entry. Developers/QA/viewers are blocked server-side, never merely hidden.
- `PerformanceScorePolicy` refines *which team*: `evaluateTeam(User, Team)` and `viewTeam(User, Team)` return true for admin/CTO always, and for a team lead only when `team.team_lead_id === user.id`. A new `User::leadsTeam(Team)` / `User::ledTeamIds()` helper backs this. Catalog management is gated tighter, to admin/CTO, reusing the existing `manage-users` middleware (which is admin+CTO) — a good fit since catalog is org-level config.

Rationale: `isLead()` already means exactly "admin, CTO, team lead", matching the requested access set; the only extra rule is scoping a team lead to the team(s) they actually lead, which is a policy concern, not a role concern. Alternative — let any team lead see any team (as the tasksheet does) — rejected: performance data is sensitive HR data; least privilege applies. The tasksheet's broader access is fine for operational plans but not for evaluations.

### 5. Privacy: never in the shared activity feed, notes lead-only
Unlike most models, `PerformanceScore` does **not** use `RecordsActivity`: the activity feed is readable by every authenticated user (`full-access` group), so logging scores there would leak HR data — the same reason `TasksheetEntry` puts `feedback` in `activityExtraIgnored()`. The score row itself is the audit trail: it records `evaluator_id` and `updated_at`, sufficient for "who last rated this". Notes and scores are only ever rendered inside lead-gated Blade branches and returned from lead-gated routes, so they cannot reach a non-lead response.

### 6. Blended headline score = weight-weighted average of *rated* competencies, renormalized
For a member and period, `overall = Σ(score_i · weight_i) / Σ(weight_i)` over only the competencies that actually have a score — missing ratings are **not** treated as zero (that would punish a member for the lead's incomplete evaluation). Shown as `overall` out of 5 and `round(overall/5·100)`%. Category averages use the same renormalization within each category. Objective panels (tasksheet/board) are computed and displayed **separately** and never feed the headline number (per the composite decision). Rationale: renormalizing keeps the score honest and stable as coverage grows; using *current* competency weights (not a weight snapshot per score) is accepted for simplicity — weights change rarely and analytics are a live read, not a signed record. Trade-off noted below.

### 7. Objective panels read existing data, computed in a `PerformanceAnalytics` service
A dedicated `PerformanceAnalytics` service (like `OverlapChecker`) computes, for a member+period from existing tables:
- **Tasksheet**: `SUM(work_points)`, `SUM(ticket_count)`, `SUM(ticket_points)`, a per-day work-points trend (reuse the tasksheet's 14-day pattern), on-time fill rate (entries not `wasFilledLate()` ÷ working days), attendance/leave counts (`leave_type`).
- **Board/tasks**: assigned count, completed (`DONE_STATUSES`), and **rework rate** — share of the member's tasks that passed through `recheck`. (v1 approximates rework via current/most-recent status = `recheck`, or a count of recheck transitions if activity data is readily queryable; the simplest correct signal is used and documented in tasks.)
Team panels aggregate the same across members. Keeping this in a service keeps controllers thin and makes the metrics unit-testable in isolation.

### 8. Competency lifecycle: deactivate, don't delete; guard destructive deletes
Retiring a competency sets `active = false` — it drops off new evaluation grids but its scores remain (analytics/history still resolve `competency_id`). Hard deletion is only offered when a competency has **no** scores; if scores exist, the UI offers deactivate instead (and the FK uses `restrict`/`cascade` deliberately — we prefer preventing deletion of a scored competency). This preserves historical integrity, matching how the app archives (soft) teams/projects rather than deleting live data.

### 9. Evaluation grid UI: team + cadence + period → member×competency matrix
One screen: pick a team (leads see only owned teams; admin/CTO all), a cadence tab (**Daily** / **Weekly**), and a period (date picker for daily; week picker/prev-next for weekly — mirroring the tasksheet's day navigation). Rows are active dev/QA members ∪ anyone already scored that period; columns are the active competencies for that cadence filtered to each member's role. Each cell is a compact segmented 1–5 selector (color-graded, keyboard-navigable) plus an expandable note field; the row saves via one Alpine-driven PUT (mirroring the tasksheet row form). Already-scored cells show their value; unscored cells show a subtle empty state. A per-row and per-grid **coverage meter** shows how complete the evaluation is. Members on leave for the period are badged and excluded from the coverage denominator.

### 10. Analytics UI: modern, self-contained charts
Two views, both lead-only, following `frontend-design`/`dataviz` guidance while staying within the app's Tailwind system and CSS/SVG chart approach:
- **Team overview**: KPI cards (team average /5, evaluation coverage %, top performer, needs-attention count); a **member leaderboard** with per-member score bars and category chips; **per-category team averages** (horizontal bars); a **team trend** sparkline/line (average per recent week) as inline SVG; a **tasksheet output** strip (work points, tickets) reusing the existing chart idiom; a needs-attention list.
- **Member scorecard**: an **overall score ring/gauge** (out of 5 + %), a **category radar** (inline SVG polygon — the classic real-world competency shape), a **per-competency list** with latest score, anchor label, mini trend, and note; a **score-history timeline**; and the **objective panels** (work-points trend, tickets, on-time %, attendance, board completion + rework rate) clearly partitioned from the rating score with a "context, not scored" label. Score-to-color uses a single 1–5 → hue scale defined once and reused everywhere for a coherent visual system.

### 11. Routes and navigation
A `performance` group (middleware `performance`):
- `GET performance` → team overview (`performance.index`, `?team=`, `?period=`, `?cadence=`).
- `GET performance/members/{user}` → member scorecard (`performance.members.show`, scoped by policy to a team the viewer may see).
- `GET performance/evaluate` → evaluation grid (`performance.evaluate`).
- `PUT performance/scores` → upsert (`performance.scores.upsert`).
- Catalog CRUD `performance/competencies` (resource) nested under `manage-users` (admin/CTO).
Nav entry "Performance" added to `$nav` and included in neither the limited-role allow-list nor shown unless `isLead()`.

### 12. Rating scale as a const map on the model
`PerformanceScore::SCALE = [1=>'Needs Improvement', 2=>'Below Expectations', 3=>'Meets Expectations', 4=>'Exceeds Expectations', 5=>'Outstanding']`, mirroring `Task::STATUSES` / `TasksheetEntry::LEAVE_TYPES`. Categories, cadences, and role scopes likewise as const maps on `PerformanceCompetency`. This keeps labels/validation in one place and drives both the form selectors and analytics labels.

## Risks / Trade-offs

- **[Weights change → historical headline scores shift]** → Accepted for v1: analytics are a live read using current weights; weights change rarely and the effect is small and monotonic. If signed, point-in-time reviews are needed later, snapshot the weight onto the score row — a purely additive change.
- **[Rework-rate signal is approximate]** → The board stores current status, not full transition history for every task; v1 derives rework from `recheck` presence and documents the approximation. Activity records can refine it later without changing the score model.
- **[Subjective inflation / rater drift]** → Anchor labels on every rating and the coverage meter reduce (not eliminate) drift; team-average and category views make an outlier rater visible. Calibration across leads is a process concern, out of scope for tooling in v1.
- **[Leaking performance data via shared partials/JSON]** → Mitigated exactly as the tasksheet does: no `RecordsActivity`, all rendering behind `isLead()`/policy gates, upsert redirects (no JSON echo of scores), and tests asserting a non-lead response body contains no score/note content and no Performance nav entry.
- **[A member on multiple teams shows partial data on one team's view]** → By design scores are team-scoped; the member scorecard states the team context. Cross-team aggregation is a documented non-goal.
- **[Coverage pressure could push leads to rate quickly/unfairly]** → Coverage is shown as information, never enforced or deadlined in v1; leave excuses gaps so the number stays fair.
- **[Division by zero / empty periods]** → Every average/percentage guards an empty denominator and renders a neutral "not yet evaluated"/empty state (explicit spec requirement and tests).
- **[Future/backdated periods]** → Future periods rejected in validation; backdating within reason is allowed (a lead may catch up on last week), consistent with the tasksheet's backfill stance, and `updated_at`/`evaluator_id` record who set it and when.

## Migration Plan

1. Ship the two migrations (`performance_competencies`, `performance_scores`) — purely additive, no changes to existing tables.
2. Run `PerformanceCompetencySeeder` to populate the default catalog (idempotent `updateOrCreate` on `key`, so re-running is safe and re-seeding after edits won't clobber admin changes to unrelated rows).
3. Register the `performance` middleware alias, routes, policy, and nav entry.
4. No backfill of scores (there is no prior data); analytics simply show empty states until leads begin rating.
5. Rollback: drop the two tables and remove the routes/nav/middleware — no other feature depends on them, so removal is clean and non-breaking.

## Open Questions

- Should weekly cadence align to the release/tasksheet working week if that ever differs from Monday–Sunday? v1 fixes Monday–Sunday; revisit only if teams operate on a different week.
- Should admins be able to set per-team competency subsets (e.g. a QA-only team hides dev competencies beyond role filtering)? v1 relies on role-scope filtering per member; per-team catalogs are a possible later refinement.
- Longer-horizon roll-ups (monthly/quarterly review export) — deferred; the data model already supports aggregating across periods when needed.
