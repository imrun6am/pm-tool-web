> Implemented and verified. 49 tests pass (7 new collaboration/audit feature tests);
> the running app was smoke-tested: admin created tasks/off-days/comments (all logged to the
> activity feed), viewers added tasks + comments but were blocked (403) from off-days.

## 1. Database

- [x] 1.1 Migration: add nullable `description` (text) to `releases`
- [x] 1.2 Migration: create `tasks` (release_id, parent_id self-FK, title, description, status, assignee_id, created_by, due_date, phase, position) with cascade on release_id and parent_id
- [x] 1.3 Migration: create `comments` (polymorphic commentable, user_id, body)
- [x] 1.4 Migration: create `release_off_days` (release_id, date, reason) unique on (release_id, date)
- [x] 1.5 Migration: create `activities` (log_name, description, event, polymorphic subject, causer_id, release_id, properties json)

## 2. Models, trait, policy

- [x] 2.1 `RecordsActivity` trait: log created/updated/deleted with causer + old→new properties, ignore-list, seed opt-out
- [x] 2.2 `Activity` model (subject morphTo, causer belongsTo) + `Task`, `Comment`, `ReleaseOffDay` models with relations and `activityTitle()`
- [x] 2.3 Add `RecordsActivity` to Project, Team, Release, ReleasePhase, ReleaseOffDay, Task, Comment
- [x] 2.4 Extend `Release` (description fillable; rootTasks, offDays, comments relations; workingDays/offDayCount helpers) and `User` (assignedTasks, comments)
- [x] 2.5 `Task` deleting hook: remove its comments and subtasks' comments; one-level nesting guard
- [x] 2.6 `CommentPolicy` (update/delete = author or admin); auto-discovered

## 3. Requests, controllers, routes

- [x] 3.1 Add `description` to `ReleaseRequest` and the release form
- [x] 3.2 `TaskRequest` + `TaskController` (store/storeSubtask/show/update/updateStatus/destroy) with one-level guard
- [x] 3.3 `CommentRequest` + `CommentController` (store for release & task; update/destroy via policy)
- [x] 3.4 `ReleaseOffDayRequest` (date within window, unique) + `ReleaseOffDayController` (store, destroy, markWeekends) — admin-gated
- [x] 3.5 `ActivityController` (global index, paginated, filterable)
- [x] 3.6 Routes: task + comment routes under `auth`; off-day routes under `admin`; `/activity` under `auth`

## 4. Views

- [x] 4.1 Release form: description textarea
- [x] 4.2 Release show: description block; working-days summary; off-days panel (admin add/remove + mark-weekends); off-day ticks on the release timeline
- [x] 4.3 Release show: tasks panel (list with subtasks, status badges, assignee, due, phase, subtask progress; inline add task/subtask; quick status change)
- [x] 4.4 Task detail page: fields (editable), subtasks, comment thread
- [x] 4.5 Reusable comments-thread partial (post, edit-own, delete author/admin) used on release and task
- [x] 4.6 Release show: collapsible History panel (release-scoped activity)
- [x] 4.7 Global Activity page + nav link; show causer, description, old→new for updates

## 5. Seed, test, verify

- [x] 5.1 Seeder: demo tasks/subtasks, comments, and off-days (activity logging opted out during seed)
- [x] 5.2 Feature tests: task + subtask create/one-level guard; comment create + author/admin delete + forbidden path; off-day within-window + weekend helper; activity recorded with causer + old→new
- [x] 5.3 Migrate, `npm run build`, run full test suite (49 pass), live smoke test (admin + viewer), then check off tasks and `openspec validate`

## 6. Revision: team-wise assignee options

- [x] 6.1 Task page and release tasks-panel assignee selects list only the release team's active members (plus current assignees no longer on the team)
- [x] 6.2 `TaskRequest` rejects assignees outside the release's team (unchanged existing assignee allowed)
- [x] 6.3 Feature tests: options limited to team, non-member rejected, former-member assignee preserved
