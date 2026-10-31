## Why

The tool tracks *what* teams ship (releases, tasks, tasksheets) but nothing about *how each person is performing*. Team leads today judge their developers and QA informally — in their head or in a private spreadsheet — with no shared scale, no history, and no way to connect a rating to the objective work record the tasksheet already holds. There is no professional, auditable way for a lead to score a member on the competencies that matter for their role, and no analytics to answer "how is this person trending?" or "how is my team doing overall?" This change brings structured, real-world performance management in-app: role-aware competencies, a lead-only 1–5 rating flow on a daily and weekly cadence, and analytics that blend those ratings with the tasksheet and board data already captured.

## What Changes

- **New Performance section** (navbar entry), visible only to leads (admin, CTO, team lead). Developers, QA, and viewers never see it and are blocked server-side — performance data is treated as private HR data.
- **Competency catalog** — a seeded, admin-configurable set of scoring dimensions, each with a role scope (developer / QA / both), a category (Behavioral, Technical, Delivery, Growth), a cadence (**daily** or **weekly**), and a weight. Seeded defaults cover Code Quality, Problem Solving, Task Completion, Understanding & Requirements, Behavior & Professionalism, Communication & Collaboration, Learning Progress, Ownership & Discipline, plus QA-specific Test Thoroughness, Defect Detection, and Attention to Detail.
- **1–5 rating scale with anchor labels** (1 Needs Improvement → 5 Outstanding) used for every competency.
- **Evaluation flow** — the team's **assigned** team lead (or an admin/CTO) opens an evaluation grid for a team, a cadence, and a period (a date for daily competencies, an ISO week for weekly ones), and rates each active developer/QA member on the competencies for that cadence, with an optional private note per score. Saving is upsert per (team, member, competency, period) — re-rating updates, never duplicates.
- **Per-member analytics** — a scorecard showing a blended headline score (weighted average of ratings, out of 5 and as a percentage), a category radar/breakdown, per-competency latest score + trend, score history, and **separate objective panels pulled from existing data**: tasksheet work-points trend, ticket output, on-time fill %, attendance/leave, and board task completion + rework (recheck) rate.
- **Per-team analytics** — team average score, a member leaderboard, category averages, a team trend over time, **evaluation coverage** (how complete the lead's ratings are for the period), and team tasksheet output — with an at-a-glance "needs attention" surfacing of low or declining scorers.
- **Access scoping** — a team lead sees and evaluates only teams they are the assigned lead of; admins and CTOs see and evaluate all teams. Catalog management is admin/CTO only.
- **Privacy of ratings** — performance scores and notes are never written to the shared activity feed (which every authenticated user can read), mirroring how tasksheet `feedback` is kept lead-only.

## Capabilities

### New Capabilities
- `performance-competencies`: The competency catalog — role-scoped, category-tagged, cadence-tagged, weighted scoring dimensions; the seeded default set; and admin/CTO management (create, edit, reorder, activate/deactivate) with historical scores preserved when a competency changes.
- `performance-scoring`: Lead-only recording of 1–5 ratings on a daily/weekly cadence per (team, member, competency, period), with the anchored scale, optional private notes, upsert semantics, the access model (assigned lead / admin / CTO), period and validation rules, and the corner cases (leave, former members, deactivated competencies, future periods, privacy from the activity feed).
- `performance-analytics`: Per-member and per-team analytics that blend the weighted rating score with separate objective panels drawn from the tasksheet and board, including trends, category breakdowns, leaderboards, evaluation coverage, and empty/edge states — all lead-only.

### Modified Capabilities

<!-- None. Existing capabilities (teams, roles, tasksheet, tasks) are consumed as-is; no existing requirement changes. -->

## Impact

- **Database**: new `performance_competencies` table (catalog) and `performance_scores` table (`team_id`, `user_id`, `evaluator_id`, `competency_id`, `period_type`, `period_start`, `period_end`, `score`, `note`, unique per team/member/competency/period).
- **Backend**: `PerformanceCompetency` and `PerformanceScore` models; `PerformanceController` (analytics), `PerformanceScoreController` (evaluation grid + upsert), `PerformanceCompetencyController` (catalog CRUD); `PerformanceScoreRequest` + `PerformanceCompetencyRequest`; a `PerformanceScorePolicy` (assigned-lead / admin / CTO scoping); a `PerformanceAnalytics` service that computes member/team metrics and folds in tasksheet + board data; a `PerformanceCompetencySeeder` for the default catalog; small `User`/`Team` helpers (e.g. `User::leadsTeam(Team)`).
- **Routes / middleware**: a new `performance` route group behind a middleware that requires `isLead()`; catalog routes further gated to admin/CTO. New nav entry gated the same way.
- **UI**: `resources/views/performance/` (team overview, member scorecard, evaluation grid, competency management) using the app's Blade + Alpine + Tailwind conventions with inline-SVG/CSS charts (no new JS chart dependency).
- **No breaking changes**; existing teams, roles, tasksheet, and board features are reused unchanged. Performance data is additive and fully hidden from non-lead roles.
