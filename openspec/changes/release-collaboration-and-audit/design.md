## Context

Builds on the existing Release Planner (Laravel 13, Blade, SQLite, Breeze auth with `admin`/`viewer` roles; `Project`, `Team`, `Release`, `ReleasePhase`, `ReleaseDocument`). This change adds collaboration and auditing to the release page: description, tasks/subtasks, comments, off-days, and a rich activity log. Four decisions are already fixed by the user: comments on both releases and tasks; rich task fields; any authenticated user may collaborate on tasks/comments; off-days are per-release. A fifth, added mid-scope: an attributable history of all changes.

## Goals / Non-Goals

**Goals:**
- Release description; tasks with one level of subtasks (status, assignee, due date, optional phase link).
- Polymorphic comments on releases and tasks, attributed to authors, author/admin editable.
- Per-release off-days (with reason + weekend helper) feeding a working-day count and timeline markers.
- App-wide, attributable activity log with old→new values; global feed + per-release history.

**Non-Goals:**
- No task drag-ordering, boards, labels, checklists beyond one subtask level, or task attachments.
- No @mentions, comment reactions, or notifications/email.
- No external audit/activity package; no soft-deletes.
- Off-days do not alter overlap or phase validation — they are informational only.

## Decisions

**Collaboration vs structural permissions.**
Tasks and comments routes sit under the existing `auth` middleware (not `admin`), so viewers can participate. Structural editing (releases, projects, teams) and **off-day management** stay under `admin` — off-days are part of the plan the admin owns. Comment edit/delete is guarded by a `CommentPolicy` (author or admin). Task create/edit/delete is open to any authenticated user, matching the "everyone can collaborate" choice.

**Tasks: self-referencing single-level tree.**
`tasks` has a nullable `parent_id` (self FK, cascade). A task is a subtask iff `parent_id` is set. Nesting is capped at one level in both UI (no "add subtask" on a subtask) and server (reject a create whose parent already has a parent). `phase` is stored as a nullable slug (`development|qa|retest|release`) rather than a FK to a `release_phases` row — the four phases are canonical, so a slug is simpler and stable across phase-date edits. `status` is a slug with a fixed set and display colors. `assignee_id`/`created_by` are nullable FKs to users (nullOnDelete).

**Comments: polymorphic.**
A single `comments` table with `commentable_type`/`commentable_id` (morphs), `user_id`, and `body`. `Release` and `Task` both `morphMany` comments. One thread implementation serves both subjects; two thin routes (`releases/{release}/comments`, `tasks/{task}/comments`) create them, and a shared `comments/{comment}` route handles edit/delete via policy. Deleting a task deletes its comments (and its subtasks' comments) explicitly in the model's `deleting` hook, since polymorphic children have no DB-level cascade.

**Off-days: rows per date.**
`release_off_days` (release_id, date, reason, unique on release_id+date). Validation requires the date to fall inside the release window and be unique for the release. The "mark weekends" action iterates the window server-side and inserts missing Sat/Sun rows. Working days = window length − off-day count. The timeline draws off-days as thin muted ticks positioned by the same `Timeline` percent math already used for bars.

**Activity log: in-app via a `RecordsActivity` trait (no package).**
On Laravel 13 (very new), third-party audit packages may not yet declare support, so logging is implemented directly and kept small. An `activities` table stores `log_name`, `description`, `event`, polymorphic `subject`, `causer_id` (user), and a JSON `properties` bag holding `{old, attributes}` for updates (or a single `attributes` snapshot for create/delete). A `RecordsActivity` trait added to `Project`, `Team`, `Release`, `ReleasePhase`, `ReleaseOffDay`, `Task`, `Comment` hooks `created/updated/deleted`, diffs `getChanges()`/`getOriginal()` (excluding timestamps and a per-model ignore list), resolves the causer from `Auth::id()`, and writes one entry. Each model exposes an `activityTitle()` for readable descriptions ("updated release Checkout v2.4"). Bulk/seed writes can opt out via a static guard so seeding does not flood the log. Per-release history is assembled by querying activities whose subject is the release or any of its child records (tasks, phases, off-days, comments) — resolved through the release id — newest-first.

Alternative considered: spatie/laravel-activitylog — richer but risks a Laravel 13 version-constraint conflict; the custom trait is ~a file and fully under control.

**Rendering.**
Server-rendered Blade throughout, consistent with the app. Alpine (shipped with Breeze) toggles inline forms (add subtask, edit comment). The release page gains stacked panels: Description, Tasks, Off-days, Comments, and a collapsible History. A new task page mirrors this for a single task. A global `/activity` page paginates the feed.

## Risks / Trade-offs

- [Custom activity logging can miss events done via bulk queries] → The trait covers Eloquent model events; documented that mass `update()`/`delete()` and seeding bypass it (seeding intentionally opts out).
- [Polymorphic comments/activity have no DB cascade] → Explicit cleanup in `Task`/`Release` `deleting` hooks and nullable causer/assignee FKs (nullOnDelete) prevent orphans and FK errors.
- [Subtable JSON diffs could log noise] → Per-model ignore lists (timestamps, `position`) and logging only `getChanges()` keep entries meaningful.
- [Viewers now write tasks/comments] → Intentional; structural resources and off-days remain admin-gated, so the plan itself is still admin-controlled.
- [Activity table growth] → Acceptable at this scale; the feed paginates and per-release history is id-scoped. Pruning is out of scope.

## Migration Plan

1. Migrations: add `description` to `releases`; create `tasks`, `comments`, `release_off_days`, `activities`.
2. Models, `RecordsActivity` trait, `CommentPolicy`; wire relations on `Release`/`User`.
3. Controllers (`TaskController`, `CommentController`, `ReleaseOffDayController`, `ActivityController`), form requests, routes.
4. Views: release page panels, task page, activity page, nav link; extend release form with description.
5. Extend the seeder (opting out of activity logging) with demo tasks/subtasks, comments, and off-days; migrate, build, test, smoke-test.
Rollback: `migrate:rollback` the new migrations (or `migrate:fresh --seed`); no external consumers.

## Open Questions

- None blocking. Off-days are advisory by design; if they should later subtract from phase capacity or drive overlap, that is a follow-up change.
