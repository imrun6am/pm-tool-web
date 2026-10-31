## 1. Database & Models

- [x] 1.1 Create `performance_competencies` migration: `key` (unique), `name`, nullable `description`, `category` (string), `role_scope` (string), `cadence` (string), `weight` (unsigned smallint default 1), `active` (bool default true), `position` (int default 0), timestamps
- [x] 1.2 Create `performance_scores` migration: `team_id` + `user_id` FKs (cascade), `evaluator_id` FK (nullOnDelete), `competency_id` FK (restrict on delete), `period_type` (string), `period_start` + `period_end` (date), `score` (unsigned tinyint), nullable text `note`, timestamps; unique (`team_id`,`user_id`,`competency_id`,`period_start`); indexes (`team_id`,`period_start`) and (`user_id`,`period_start`)
- [x] 1.3 Create `PerformanceCompetency` model: fillable, bool `active` cast, `CATEGORIES`/`ROLE_SCOPES`/`CADENCES` const maps, `scores()` hasMany, `appliesToRole(string)` helper, `active()` + `forCadence()` + ordered scopes
- [x] 1.4 Create `PerformanceScore` model: fillable, casts (`period_start`/`period_end` date, `score` int), `SCALE` const map (1→5 anchor labels), `scoreLabel()`, `member()`/`team()`/`evaluator()`/`competency()` belongsTo relations; deliberately does NOT use `RecordsActivity`
- [x] 1.5 Add `User::leadsTeam(Team)` and `User::ledTeamIds()` helpers (assigned lead of the team); confirm `isLead()` covers admin/CTO/team_lead
- [x] 1.6 Run migrations and verify schema

## 2. Seeding

- [x] 2.1 Create `PerformanceCompetencySeeder` with the default catalog (Code Quality, Problem Solving, Task Completion, Understanding & Requirements, Behavior & Professionalism, Communication & Collaboration, Learning Progress, Ownership & Discipline, Test Thoroughness, Defect Detection, Attention to Detail), each with category, role scope, cadence, weight, position; use `updateOrCreate` on `key` (idempotent)
- [x] 2.2 Register the seeder in `DatabaseSeeder`; ensure at least one daily and one weekly competency, all four categories, and both roles are represented
- [x] 2.3 Run the seeder and verify the catalog

## 3. Authorization & Access

- [x] 3.1 Create `performance` route-middleware alias: abort 403 unless `User::isLead()`; register in the HTTP kernel/bootstrap alongside `full-access`/`manage-users`
- [x] 3.2 Create `PerformanceScorePolicy`: `evaluateTeam(User,Team)` and `viewTeam(User,Team)` → true for admin/CTO, or team lead when `leadsTeam`; `manageCatalog(User)` → admin/CTO only (or reuse `manage-users` middleware for catalog routes)
- [x] 3.3 Register the policy / gates

## 4. Validation & Requests

- [x] 4.1 Create `PerformanceScoreRequest`: `team_id`/`user_id`/`competency_id` required+exists; `score` required integer 1–5; `note` nullable max:2000; derive/validate period from the competency's cadence; reject future periods; ensure target is an active dev/QA member of the team (or has an existing score); authorize via `evaluateTeam`
- [x] 4.2 Create `PerformanceCompetencyRequest`: `name` required; `category` in CATEGORIES; `role_scope` in ROLE_SCOPES; `cadence` in CADENCES; `weight` integer min:1; `active` boolean; `position` integer min:0

## 5. Controllers & Routes

- [x] 5.1 Create `PerformanceScoreController@evaluate`: resolve team (viewer's led teams first / all for admin-CTO), cadence tab (daily/weekly), and period (date for daily, ISO week for weekly, Monday-anchored); build member×competency matrix (active dev/QA members ∪ already-scored users; competencies filtered by role); load existing scores keyed by (user,competency); flag leave via tasksheet; compute coverage
- [x] 5.2 Create `PerformanceScoreController@upsert`: authorize `evaluateTeam`; normalize period from cadence; `updateOrCreate` on the unique key; store `evaluator_id`; support saving multiple cells in one request (per-row); redirect back with flash
- [x] 5.3 Create `PerformanceController@index` (team overview): team + period + cadence resolution scoped by policy; delegate metrics to `PerformanceAnalytics`
- [x] 5.4 Create `PerformanceController@show` (member scorecard): authorize the viewer may see a team the member belongs to; delegate to `PerformanceAnalytics`
- [x] 5.5 Create `PerformanceCompetencyController` (index/create/store/edit/update/destroy + reorder/toggle): admin/CTO only; block hard delete when scores exist (offer deactivate)
- [x] 5.6 Register routes: `performance` group (index, members.show, evaluate, scores.upsert) behind the `performance` middleware; `performance/competencies` resource behind `manage-users`; add "Performance" to `$nav` gated by `isLead()` and excluded from the limited-role allow-list

## 6. Analytics Service

- [x] 6.1 Create `PerformanceAnalytics` service: `member(User,Team,period)` → weighted headline (out of 5 + %) over rated competencies only (renormalized, no divide-by-zero), per-category averages, per-competency latest score + trend, score history
- [x] 6.2 `member` objective panels: tasksheet work-points trend + totals, ticket count/points, on-time fill rate (via `wasFilledLate()`), attendance/leave; board metrics (assigned, completed via `DONE_STATUSES`, rework rate from `recheck`)
- [x] 6.3 `team(Team,period)` → team average, member leaderboard, per-category team averages, team trend across recent periods, evaluation coverage (leave-excluded denominator + unrated list), needs-attention (below threshold or declining), team tasksheet output
- [x] 6.4 Period helpers: normalize daily/weekly periods (Monday-anchored weeks), build a recent-periods series for trends

## 7. Views & Design

- [x] 7.1 Evaluation grid `performance/evaluate.blade.php`: team select (led teams first) + cadence tabs (Daily/Weekly) + period navigation (date picker / week prev-next, tasksheet pattern); member×competency matrix; segmented 1–5 selector (color-graded, keyboard accessible) + expandable per-score note; per-row + grid coverage meter; leave badges; empty states
- [x] 7.2 Team overview `performance/index.blade.php`: KPI cards (team avg /5, coverage %, top performer, needs-attention count); member leaderboard with score bars + category chips; per-category team-average bars; team-trend inline-SVG line/sparkline; tasksheet output strip; needs-attention list; empty states
- [x] 7.3 Member scorecard `performance/show.blade.php`: overall score ring/gauge (/5 + %), category radar (inline SVG), per-competency list (latest score, anchor label, mini trend, note), score-history timeline, objective panels clearly partitioned as "context, not scored"; not-yet-evaluated empty state
- [x] 7.4 Competency management `performance/competencies/*.blade.php`: catalog table (category/role/cadence/weight/active), create/edit forms, reorder + activate/deactivate, delete guarded when scores exist
- [x] 7.5 Shared: a single 1–5 → color scale + anchor-label helper reused across grid and analytics; a score-badge partial; apply `frontend-design`/`dataviz` guidance within the app's Tailwind system

## 8. Tests

- [x] 8.1 Access tests: dev/QA/viewer blocked from every Performance route (403) and the nav entry absent from their layout; admin/CTO/team-lead allowed
- [x] 8.2 Scoping tests: a team lead can evaluate/view only their assigned team; forbidden on another team; admin/CTO unrestricted
- [x] 8.3 Scoring tests: create then re-rate updates (no duplicate) on the unique key; score out of 1–5 rejected; future period rejected; non-member target rejected; evaluator recorded
- [x] 8.4 Cadence/period tests: daily score keyed to the date; weekly score normalized to the Monday–Sunday week regardless of entry weekday
- [x] 8.5 Privacy tests: performance score/note never appears in the shared activity feed; non-lead response bodies contain no score/note content
- [x] 8.6 Analytics tests: weighted headline over rated competencies only (missing ≠ zero, no divide-by-zero); category averages; coverage excludes on-leave members; needs-attention flags low/declining; empty states for unevaluated member / memberless team
- [x] 8.7 Catalog tests: admin creates/edits/deactivates; team lead forbidden from catalog; deactivated competency drops off new grids but history persists; invalid weight rejected; delete blocked when scores exist
- [x] 8.8 Objective-panel tests: tasksheet totals/on-time/attendance and board completion/rework computed correctly for a member/period
- [x] 8.9 Run the full test suite (`composer test`) and fix regressions; run `./vendor/bin/pint` on new files
