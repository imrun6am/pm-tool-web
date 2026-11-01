# Release Planner — REST API v1

A versioned, token-authenticated REST API covering every feature of the web app, built so a
cross-platform **desktop client** can serve all seven roles with exactly the access each role has
in the browser.

- **Base URL**: `/api/v1`
- **Auth**: Sanctum personal access tokens (`Authorization: Bearer <token>`)
- **Format**: JSON in, JSON out — no sessions, no CSRF
- **Spec**: [`openspec/changes/desktop-rest-api-v1/`](../openspec/changes/desktop-rest-api-v1/)

---

## Conventions

### Envelope

A single resource is wrapped in `data`:

```json
{ "data": { "id": 1, "name": "Checkout v2.5" }, "message": "Release created." }
```

A collection adds `meta` and `links`:

```json
{
  "data": [ ... ],
  "meta":  { "current_page": 1, "per_page": 25, "total": 7, "last_page": 1 },
  "links": { "first": "...", "last": "...", "prev": null, "next": null }
}
```

Fixed-size sub-collections (a release's four phases, the board's columns, the metadata
enumerations) are returned whole and are **not** paginated.

### Pagination

`?page=2&per_page=50`. `per_page` defaults to **25** and is capped at **100** — a client cannot
request the whole table. The live values are published at `GET /meta` under `pagination`.

### Dates

- Date-only fields (`start_date`, `due_date`, `date`, `period_start`) → `YYYY-MM-DD`
- Timestamps (`created_at`, `starts_at`, `completed_at`) → ISO-8601

Date-only fields deliberately carry no time, so a client west of UTC never renders the day before
the one the planner typed.

### Errors

| Status | Meaning                                            |
| ------ | -------------------------------------------------- |
| 401    | No valid token (or the account was deactivated)    |
| 403    | Authenticated, but the role may not do this        |
| 404    | Not found, or not visible to the caller            |
| 410    | Retired endpoint — the body names its replacement  |
| 422    | Validation failed, or a domain rule refused        |
| 429    | Throttled — see the `Retry-After` header           |

```json
{
  "message": "The name field is required.",
  "errors": { "name": ["The name field is required."] }
}
```

### Throttling

- **General**: 120 requests/minute per token (per IP when unauthenticated)
- **Login**: 5/minute per email + IP, plus 20/minute per IP

### Warnings

Some operations succeed *and* have something to say. The release overlap rule is the main one: a
save that double-books a team is **never blocked**, it returns the conflict beside `data`.

```json
{
  "data": { "id": 8, "name": "Smoke Test Release" },
  "warning": {
    "type": "team_overlap",
    "message": "Heads up: team Team Alpha is already booked during this window by …",
    "conflicts": [ { "id": 1, "name": "Checkout v2.4" } ]
  },
  "message": "Release “Smoke Test Release” created."
}
```

The `warning` key is **absent** when there is no conflict.

---

## Getting started

### 1. Log in

```http
POST /api/v1/auth/login
Content-Type: application/json

{ "email": "admin@example.com", "password": "password", "device_name": "Desktop — macOS" }
```

```json
{
  "data": {
    "token": "1|T5jY21XW…",
    "token_type": "Bearer",
    "user": { "id": 1, "name": "Admin User", "role": "admin", "permissions": { … } }
  }
}
```

The plain-text token is shown **once**. Store it in the OS keychain, not in plain config.

### 2. Send it on every request

```http
Authorization: Bearer 1|T5jY21XW…
Accept: application/json
```

### 3. Build the UI from the server, not from assumptions

Two calls at start-up remove the need for the client to hard-code anything:

- `GET /me` → the user, their **effective permission flags**, their teams, the teams they lead.
  Render navigation from these.
- `GET /meta` → every domain enumeration (roles, task statuses **with their colors**, release
  phases with colors, event types, visibilities, leave types, competency categories/scopes/
  cadences, the 1–5 scale, upload limits). Render pickers from these.

A status or role added server-side then reaches every installed client on its next request,
without a client release.

> **The permission flags decide what to _show_. The server still enforces what you may _do_.**
> Every restriction is checked server-side; a hidden button is not a permission check.

---

## Endpoints

### Auth & account

| Method   | Path                       | Notes                                        |
| -------- | -------------------------- | -------------------------------------------- |
| `POST`   | `/auth/login`              | Public. Throttled. Returns a token           |
| `POST`   | `/auth/logout`             | Revokes **this** device only                 |
| `POST`   | `/auth/logout-all`         | Revokes every device                         |
| `GET`    | `/auth/tokens`             | The caller's own signed-in devices           |
| `DELETE` | `/auth/tokens/{token}`     | Revoke one device                            |
| `GET`    | `/me`                      | User + permissions + teams + led teams       |
| `PUT`    | `/me`                      | Update own name/email                        |
| `PUT`    | `/me/password`             | Requires `current_password`; signs out others|
| `GET`    | `/meta`                    | All domain enumerations                      |
| `GET`    | `/dashboard`               | Planning timeline, or personal member view   |

There is **no registration endpoint** — accounts are created only under `/users`.

**Planning dashboard** — every other role gets `mode: "planning"` (timeline, `analytics`) plus a
`delivery` object tailored to the caller. It follows the same `year`/`quarter`/`project_id`/`team_id`
filters, and its KPIs compare the **last 28 days** with the 28 before. Archived tasks are never counted.

| `delivery.audience` | Who          | Scope                                                       |
| ------------------- | ------------ | ----------------------------------------------------------- |
| `org`               | Admin, CTO, Tech Lead | all teams (or `team_id`)                           |
| `team`              | Team Lead    | the teams they lead (`team_ids`), unless `team_id` is given |
| `viewer`            | Viewer       | all teams; `people`, `attention`, `compliance` are `null`   |

| `delivery` key    | Contents                                                                    |
| ----------------- | --------------------------------------------------------------------------- |
| `scope_label`, `team_ids`, `window` | What's in view; `window` has `days`, `start`, `end`, `previous_start`, `previous_end` |
| `kpis`            | Each `{current, previous, change}`: `throughput`, `cycle_days`, `on_time_rate`, `rework_rate` (share of finished work that went through Recheck) |
| `backlog`         | Open work: `open`, `overdue`, `due_soon`, `in_review`, `recheck`, `unassigned` |
| `aging`           | Open tasks by age since creation: `[{label, count}]`                       |
| `weekly`          | 12 × `{week_start, count, is_current, is_best}` completions               |
| `team_load`       | `[{id, name, color, open, overdue}]` (`id` null = no team)                 |
| `people`          | Up to 8 `{user, open, in_progress, in_review, overdue}` — not for viewers  |
| `attention`       | `overdue` and `in_review` task lists (6 each) — not for viewers            |
| `releases`        | Ongoing releases in view: `{release, total, done, pct, elapsed_pct, late_phases, flags, at_risk}`, at-risk first |
| `at_risk_count`   | Releases with any flag: double-booked, a phase ended with open work, done share trailing time used by 25+ points, or past end date |
| `compliance`      | Last 10 working days: `fill_rate`, `standup_rate`, counts, `today_attended`, and per-team `teams[]` — not for viewers |
| `mine`            | The caller's own `open`, `overdue`, `due_soon`                             |

**Member dashboard** — for developers and QA, `GET /dashboard` returns `mode: "member"`: `my_tasks`,
`overdue_count`, `tasksheet_today`, `upcoming_meetings`, and an `analytics` object holding that
member's own numbers only (never a colleague's, never lead feedback or scores). Optional
`?period=week|month|quarter` (default `month`; anything else is 422) picks the span; every KPI
compares it with the **same elapsed span** of the previous period (Nov 1–5 vs Oct 1–5).

| `analytics` key   | Contents                                                                  |
| ----------------- | ------------------------------------------------------------------------- |
| `period`          | `key`, `label`, `start`, `end`, `previous_start`, `previous_end`          |
| `today`           | `open`, `overdue`, `due_soon` (today + 7 days), `in_progress`, `in_review` |
| `focus`           | Up to 6 `{reason, task}` — `overdue`, then `due_today`, then `in_progress` |
| `kpis`            | Each `{current, previous, change}`: `completed`, `on_time_rate`, `cycle_days`, `lead_days`, `tickets`, `ticket_points`, `work_points`, `comments`, `meetings`, `standup_attended`, `standup_marked`, `standup_rate`, `days_logged`, `days_expected`, `tasksheet_rate` |
| `weekly`          | 12 × `{week_start, count, is_current, is_best}`, oldest first             |
| `best_week`       | All-time `{week_start, count}` (`week_start` null with no completions)    |
| `pace`            | `this_week`, `goal` (ceil of the previous 8 weeks' average; null with no history), `pct` |
| `status_counts`   | Each counted status → count, plus `task_total` (archived tasks are never counted) |
| `heatmap`         | `total`, `active_days`, `days[]` of `{date, count, level 0–4, tasks, comments, tasksheet_logged}` for the last 26 weeks |
| `streak`          | `current`, `best`, `active_today` — weekdays; weekends and full-day leave never break it |
| `lead_time_trend` | 12 × `{week_start, days}`                                                 |
| `recent`          | Up to 5 `{completed_at, task}`, newest first                              |
| `on_team`, `has_history` | Booleans for empty states                                          |

Completion and start times are read from the audit trail (first move to In Progress, last move into
Done). Archived tasks appear in no count, rate, trend, streak or best. Rates and averages are `null`, not 0, when there is nothing to divide by — render
"no data yet".

### Workspace

| Method   | Path                                | Access           |
| -------- | ----------------------------------- | ---------------- |
| `GET`    | `/projects`, `/projects/{id}`       | full-access      |
| `POST`   | `/projects`                         | lead             |
| `PUT`    | `/projects/{id}`                    | lead             |
| `DELETE` | `/projects/{id}`                    | lead — 422 if it has releases; archive instead |
| `POST`   | `/projects/{id}/archive`, `/restore`| lead             |
| `GET`    | `/teams`, `/teams/{id}`             | full-access — detail includes conflicting release ids |
| `POST`   | `/teams`                            | lead             |
| `PUT`    | `/teams/{id}`, `/teams/{id}/lead`   | lead             |
| `DELETE` | `/teams/{id}`                       | lead — 422 if it owns releases |
| `POST`   | `/teams/{id}/archive`, `/restore`   | lead             |
| `GET`    | `/teams/{id}/members`               | full-access — members + assignable users |
| `POST`   | `/teams/{id}/members`               | manage-releases  |
| `DELETE` | `/teams/{id}/members/{user}`        | manage-releases — soft leave, history preserved |
| `GET`    | `/users`, `/users/{id}`, `/users/stats` | manage-users |
| `POST`   | `/users`                            | manage-users     |
| `PUT`    | `/users/{id}`                       | manage-users — omit `password` to keep it |
| `POST`   | `/users/{id}/toggle-active`         | manage-users — also revokes their tokens |
| `DELETE` | `/users/{id}`                       | manage-users — soft delete |

Deleting or demoting the **last active administrator**, or deactivating **yourself**, is refused
with 422.

### Releases

| Method   | Path                                          | Access           |
| -------- | --------------------------------------------- | ---------------- |
| `GET`    | `/releases`                                   | full-access — `?status=active\|completed&project_id=&team_id=&year=&search=` |
| `GET`    | `/releases/{id}`                              | **any** authenticated user |
| `POST`   | `/releases`                                   | manage-releases  |
| `PUT`    | `/releases/{id}`                              | manage-releases  |
| `DELETE` | `/releases/{id}`                              | manage-releases  |
| `POST`   | `/releases/{id}/complete`, `/reopen`          | manage-releases  |
| `GET`    | `/releases/{id}/conflicts`                    | any              |
| `GET`    | `/releases/{id}/phases`                       | any — always 4, unpaginated |
| `GET`    | `/releases/{id}/off-days`                     | any              |
| `POST`   | `/releases/{id}/off-days`                     | manage-releases  |
| `POST`   | `/releases/{id}/off-days/weekends`            | manage-releases  |
| `DELETE` | `/releases/{id}/off-days/{offDay}`            | manage-releases  |
| `GET`    | `/releases/{id}/documents`                    | any              |
| `POST`   | `/releases/{id}/documents`                    | contributors (not viewers) — `multipart/form-data`, field `document` |
| `GET`    | `/releases/{id}/documents/{doc}`              | any — streams the file |
| `DELETE` | `/releases/{id}/documents/{doc}`              | manage-releases  |
| `GET`    | `/releases/{id}/tasks`                        | any              |
| `POST`   | `/releases/{id}/tasks`                        | any — always linked to this release |
| `GET`    | `/releases/{id}/comments`                     | any              |
| `POST`   | `/releases/{id}/comments`                     | any              |

Creating or updating a release takes the four phases as a nested object:

```json
{
  "project_id": 1, "team_id": 1, "name": "Billing v2",
  "year": 2026, "quarter": 3,
  "start_date": "2026-07-01", "end_date": "2026-07-31",
  "phases": {
    "development": { "start": "2026-07-01", "end": "2026-07-10" },
    "qa":          { "start": "2026-07-11", "end": "2026-07-18" },
    "retest":      { "start": "2026-07-19", "end": "2026-07-25" },
    "release":     { "start": "2026-07-26", "end": "2026-07-31" }
  },
  "members":  [3, 4],
  "off_days": [ { "date": "2026-07-04", "reason": "Holiday" } ]
}
```

Every phase window must sit **inside** the release window, and every member must belong to the
**owning team** — both are 422s naming the offending field.

Documents are served only through the authenticated download route; the stored path is never
serialized, so there is no public URL to leak.

### Collaboration — open to every authenticated user

| Method            | Path                                                     |
| ----------------- | -------------------------------------------------------- |
| `GET`             | `/tasks` — `?release_id=&team_id=&assignee_id=&status=&phase=&overdue=1&include_subtasks=1` |
| `POST`            | `/tasks` — top-level task; `release_id`, `team_id` optional |
| `GET/PUT/DELETE`  | `/tasks/{id}`                                            |
| `PATCH`           | `/tasks/{id}/status`                                     |
| `POST`            | `/tasks/{id}/subtasks` — one level only; 422 on a subtask |
| `GET/POST`        | `/tasks/{id}/comments`                                   |
| `PUT/DELETE`      | `/comments/{id}` — author or lead                        |
| `GET`             | `/board` — every status column, even when empty; `?release_id=&team_id=&assignee_id=` |
| `POST`            | `/board/tasks` — `{ title, status, release_id?, team_id? }` |
| `PATCH`           | `/board/tasks/{id}` — `{ status, ordered_ids[] }`, atomic |
| `GET`             | `/events` — `?year=&month=` or `?from=&to=`, `?show=all\|mine\|shared`; returns `events_by_date`. Others' private events are never listed |
| `POST`            | `/events` — `visibility`: `shared` (default) or `private` (creator + attendees only) |
| `GET/PUT/DELETE`  | `/events/{id}` — 403 on someone else's private event; **creator only** may edit; `DELETE` also by an admin on a shared event |
| `GET/POST`        | `/notes` — visibility-scoped; `?author=&date=&from=&to=` |
| `GET/PUT/DELETE`  | `/notes/{id}` — **author only**, leads included          |
| `GET/POST`        | `/meeting-notes` — filters below                         |
| `GET/PUT/DELETE`  | `/meeting-notes/{id}` — author edits; author or lead deletes |
| `GET/POST`        | `/quick-links` — returns `{ mine, shared }`              |
| `PUT/DELETE`      | `/quick-links/{id}` — **author only**                    |
| `POST`            | `/quick-links/{id}/pin` — toggles; **anyone who can see it** |

**Tasks without a release.** A release is optional, and `release_id` and `release` are `null`
on an unlinked task.

- **Creating:** `POST /tasks` and `POST /board/tasks` take an optional `release_id`.
- **Linking:** `PUT /tasks/{id}` links a task with `release_id: <id>` and unlinks it with
  `release_id: null`. Leaving the key out keeps the current link.
- **Subtasks:** a subtask always has its parent's release; its own `release_id` is ignored, and it
  follows the parent when the parent's release changes.
- **Filtering:** `?release_id=none` on `GET /tasks` and `GET /board` selects unlinked tasks.
  `?team_id=` works the same way (`none` selects tasks with no team), and all filters combine.
- **Assignees:** on a linked task, the assignee must be a member of the release's team. On an
  unlinked task, any user may be assigned.
- **Deleting a release:** its tasks stay, unlinked.

**Task teams.** Every task carries an optional `team_id`, and `team` is a team summary when
loaded.

- **Linked tasks:** the team is always the release's team. A `team_id` you send is ignored, and
  the team follows when the release moves to another team.
- **Unlinked tasks:** creating one sets the `team_id` you send. Without one, the team defaults to
  the assignee's team when they belong to exactly one, otherwise it is `null`. `PUT` changes the
  team only when `team_id` is sent.
- **Unlinking:** the task keeps the team it had.
- **Subtasks:** a subtask always has its parent's team.

**Developers and QA are kept to their own teams.** These rules apply to everyone whose role is
`developer` or `qa`.

- **What they see:** tasks of their current teams, tasks assigned to them, and teamless tasks they
  created.
  - `GET /tasks`, `GET /board`, `GET /releases/{id}/tasks` and `tasks_count` on `GET /releases/{id}`
    leave out everything else, whatever the filters ask for.
  - Any other task is **403** on `GET/PUT/DELETE /tasks/{id}`, `PATCH /tasks/{id}/status`,
    `POST /tasks/{id}/subtasks`, `GET/POST /tasks/{id}/comments` and `PATCH /board/tasks/{id}`.
  - A board reorder only renumbers cards they can see.
- **Creating:** they don't choose a team. `POST /tasks` and `POST /board/tasks` set it on the server,
  ignoring any `team_id` sent.
  - On exactly one team: that team.
  - On several teams: `team_id` is required unless `release_id` is given, and must be one of
    their teams (422 otherwise).
  - On no team: the task has no team. It's a personal task, visible only to its creator, its
    assignee and leads.
- **Releases:** `release_id` must be one of their teams' releases (422).
  `POST /releases/{id}/tasks` on another team's release is 403.
- **Assignees:** they may assign only themselves or a current teammate (422).
- **Editing:** keeping a task's current release, team or assignee is always allowed, so a task lent
  to them from another team can still be edited.
- **Leads and viewers** see every task and may set any team.

**Beyond tasks**, Developers and QA see other teams' data nowhere else either.

- **People:** "people they can see" means themselves, their current teammates, and the assigned
  leads of their teams. Every person they pick (event attendees, meeting-note attendees, note
  recipients) must be one of them (422). People already on a record may be kept.
- **Releases:** only their own teams' releases. Every `/releases/{id}/…` route open to all users
  returns **403** for another team's release: show, conflicts, phases, off-days, documents and
  document download/upload, tasks, and comments. Linking a release to an event, meeting note,
  quick link or task must use one of their teams' releases (422).
- **Tasksheet:** `GET /tasksheet` offers only their own teams. A `team_id` for another team falls
  back to their own.
- **Events:** `GET /events` lists only:
  - events they created or attend;
  - events linked to one of their teams' releases;
  - unlinked events with no attendees, created by a teammate or by a lead/viewer
    (organisation-wide entries such as holidays).

  An unlinked event with attendees is only for its creator and attendees. Any other
  `GET /events/{id}` is **403**.
- **Meeting notes:** the same rule applies to everyone-visibility notes. Attendees-only notes are
  unchanged. Any other `GET /meeting-notes/{id}` is **403**.
- **Notes:** shared notes appear only from teammates. Any other `GET /notes/{id}` is **404**.


Rich-text bodies (notes, meeting notes, tasksheet fields) are sanitized on write; the value you
read back is safe to render. A body with no visible text is rejected as empty.

**Note filters.** `GET /notes` takes `author` (a user id) alongside `date` or `from`/`to`. `author`
narrows **within** what you may already see — it returns that person's shared notes and the ones
addressed to you, never their private ones, and an id whose notes you cannot see returns an empty
page rather than an error.

**Quick-link pinning and drawer scoping** — `POST /quick-links/{id}/pin` toggles the *calling user's*
pin and returns the resulting `{ id, is_pinned }`. A pin is per-viewer: pinning a link someone else
shared changes nothing for them, and pinning never grants edit or delete rights (those stay with the
author). `is_pinned` on a quick link is always the requesting user's own.

`GET /quick-links` is the drawer's listing, and is narrower than "every link you may see":

- Ordered **pinned → private → shared**, newest first within each band.
- A link attached to a **completed** release is omitted. It is not deleted — reopening the release
  brings it back with its pin intact, and the link stays listed on that release's details page
  (`GET /releases/{id}`) the whole time. Links attached to no release are never affected.

**Meeting note filters** — `GET /meeting-notes` accepts any combination of:

| Param      | Meaning                                                          |
| ---------- | ---------------------------------------------------------------- |
| `release`  | `general` for notes with no release, or a release id             |
| `type`     | a meeting type — values come from `meeting_note_types` in `/meta` |
| `author`   | user id — notes that person **wrote**                            |
| `attendee` | user id — notes that person **attended**                         |
| `search`   | free text, matched against the title and the body                |
| `from`/`to`| meeting-date range; a reversed span is swapped, not rejected     |

Filters compose, and all of them apply *after* visibility scoping — filtering by a colleague never
reveals an attendees-only note you may not read. Results are paginated like every other list.

A meeting note carries a `type` (`type_label` and `type_color` come along for rendering). The type
list is server-defined: read it from `meeting_note_types` at `GET /meta` rather than hard-coding it,
so a type added server-side reaches your client without a release. A note whose stored type has
since been retired still returns a readable `type_label`.

### Tasksheet

| Method | Path                              | Notes                                        |
| ------ | --------------------------------- | -------------------------------------------- |
| `GET`  | `/tasksheet?team_id=&date=`       | Rows + 14-day output trend                   |
| `PUT`  | `/tasksheet/entries`              | Upsert one member's row for one date         |
| `GET`  | `/tasksheet/users/{member}`       | That member or a lead only                   |
| `POST` | `/tasksheet/standup`              | **Leads only** — set standup attendance      |
| `POST` | `/tasksheet/exports`              | Queue a PDF export → `202`; team-wide is lead-only |
| `GET`  | `/tasksheet/exports`              | Your 25 most recent exports + capacity       |
| `GET`  | `/tasksheet/exports/status?ids=`  | Status of several of your exports            |
| `GET`  | `/tasksheet/exports/{id}`         | One export — poll this                       |
| `GET`  | `/tasksheet/exports/{id}/download`| The PDF, once `ready`                        |
| `POST` | `/tasksheet/exports/{id}/retry`   | Re-queue a `failed` export → `202`           |
| `DELETE` | `/tasksheet/exports/{id}`       | Cancel (if running) and delete               |
| `GET`  | `/tasksheet/report`               | **Retired** — `410 Gone`, see below          |

- Rows include people whose membership **covered that date** — even if they have since left, been
  deactivated, or been deleted. History does not rewrite itself.
- A **full-day** leave (`casual`/`sick`) clears the task fields; **`half_day`** keeps them.
- `feedback` is the lead's private note: **omitted entirely** from a non-lead's payload, and
  ignored on a non-lead's write.

**Standup attendance.** `standup_attended` is **three-valued**: `true` attended, `false` did not
attend, `null` **not yet marked**. Treat `null` as unknown, never as absent. Set it with
`POST /tasksheet/standup` (`{ team_id, user_id, date, attended }`) — **leads only**, and marking a
member who has no row yet creates that row. Sending `attended` empty clears it back to `null`. A
full-day leave row rejects the write and reports `accepts_standup_attendance: false`; the flag is
also cleared automatically if a row becomes a full-day leave.

**Tasksheet filters** — `GET /tasksheet` and `GET /tasksheet/users/{member}` both accept:

| Param        | Values                                                       |
| ------------ | ------------------------------------------------------------ |
| `attendance` | `attended` · `missed` · `unmarked`                           |
| `fill`       | `complete` · `partial` · `empty`                             |
| `leave`      | `working` · `any` · a leave type (`casual`/`sick`/`half_day`) |
| `member`     | user id — `GET /tasksheet` only                              |

On `GET /tasksheet` these filter the day's **rows**, which include members with no saved row at all —
so `fill=empty` and `attendance=unmarked` are how you find people who have not filled anything in.

**PDF exports.** Tasksheet PDFs are built in the background, a bounded chunk of rows per queued
job, so no request ever renders a large PDF. The API and the web exports page share one service,
one policy and one set of records.

1. **Create** — `POST /tasksheet/exports` with `team`, `member`, and either `date` (one day) or
   `from`/`to`, plus any of the `attendance` / `fill` / `leave` filters above. A reversed `from`/`to`
   is swapped, not rejected. A team-wide (or other-member) export requires a lead; a member may
   send `member={their own id}` only, anything else is `403`. **`feedback` is included only when a
   lead asks** — never in a member's own export. Answers **`202 Accepted`** with the export and a
   `Location` header pointing at `GET /tasksheet/exports/{id}`.
2. **Poll** — `GET /tasksheet/exports/{id}` every ~2 s while `status` is `pending` or
   `processing`; stop once it is `ready` or `failed`. For several at once use
   `GET /tasksheet/exports/status?ids=4,7` (or `ids[]=4&ids[]=7`, at most 50).
3. **Download** — follow `download_url` (`null` until `ready`). Returns a PDF body, not JSON.

```json
{
  "data": {
    "id": 7,
    "status": "processing",
    "status_label": "Generating",
    "progress": 40,
    "chunks_done": 2,
    "chunks_total": 4,
    "rows_total": 280,
    "merging": false,
    "error": null,
    "download_url": null,
    "team_id": 3, "member_id": null,
    "date_from": "2026-08-01", "date_to": "2026-08-31",
    "filters": { "attendance": "missed" },
    "filter_labels": ["Did not attend standup"],
    "includes_feedback": true,
    "requested_by": 1,
    "team": { "id": 3, "name": "Core", "color": "#0891b2", "is_archived": false },
    "member": null,
    "created_at": "2026-11-01T09:40:00+00:00", "started_at": "…", "completed_at": null
  },
  "message": "Export queued."
}
```

- `status` is `pending` → `processing` → `ready` | `failed`. `progress` (0–100) counts the final
  merge as a step, so it reaches 100 only when the file exists; `merging: true` means every part is
  rendered and only the merge remains. A `failed` export carries `error`. On a server running the
  `sync` queue the `202` itself may already say `ready`.
- `filters` is always an object (`{}` when none). Status, progress and `download_url` are the same
  values the web exports page shows.
- `GET /tasksheet/exports` returns the list whole (not paginated) with
  `meta: { has_capacity, max_running }`. Exports are pruned after 14 days.
- **Exports are private to whoever created them** — leads and admins included. Someone else's
  export is `403` on show, download, retry and delete, and is silently left out of the list and
  `status`. Download also re-checks the rule, so a lead who has since been demoted loses team-wide
  files and any file carrying feedback.
- `422` when you already have `max_running` exports in progress (create or retry), or when you
  retry an export that has not failed. `404` when you download one that is not `ready` yet.

**Retired: `GET /tasksheet/report`.** It used to render the PDF inside the request, which a wide
range could not survive. It now answers **`410 Gone`** and does nothing else — it neither renders
nor queues anything:

```http
HTTP/1.1 410 Gone
Link: <https://…/api/v1/tasksheet/exports>; rel="successor-version"

{ "message": "This endpoint has been retired. …", "successor": { "method": "POST", "url": "https://…/api/v1/tasksheet/exports" } }
```

### Performance — leads only

| Method   | Path                                             | Access                    |
| -------- | ------------------------------------------------ | ------------------------- |
| `GET`    | `/performance/teams`                             | lead — teams you may evaluate |
| `GET`    | `/performance/overview?team_id=&week=`           | lead, team-scoped         |
| `GET`    | `/performance/members/{user}?team_id=&week=`     | lead, team-scoped         |
| `GET`    | `/performance/evaluate?team_id=&cadence=&date=`  | lead — the grid           |
| `PUT`    | `/performance/scores`                            | lead — upsert ratings     |
| `GET/POST/PUT/DELETE` | `/performance/competencies[/{id}]`  | **manage-competencies** (admin, CTO, tech lead) |
| `POST`   | `/performance/competencies/{id}/toggle`          | manage-competencies       |

A team lead is scoped to the teams they lead; asking for another team is 403, never a silent
substitution. Scores are 1–5, keyed on (team, member, competency, period) — re-rating updates.
Blank cells are skipped, not stored as zero. Future periods are rejected. A competency with
recorded scores can only be **deactivated**, not deleted.

### Insights

| Method | Path                  | Access                                                  |
| ------ | --------------------- | ------------------------------------------------------- |
| `GET`  | `/activities`         | full-access — `?causer_id=&release_id=&event=&subject_type=&from=&to=` |
| `GET`  | `/activities/stats`   | full-access                                             |

`updated` entries carry a `changes` object of old → new per field. Performance scores never
appear in this feed.

### Engagement — open to every authenticated user

The caller's own inbox, preferences and getting-started checklist, plus task search. Nothing here
reaches another user's data.

| Method | Path                               | Notes |
| ------ | ---------------------------------- | ----- |
| `GET`  | `/notifications`                   | Newest first, paginated; `?unread=1`. `meta.unread_count` is always the total unread |
| `POST` | `/notifications/{id}/read`         | Marks one read; someone else's id is a `404` |
| `POST` | `/notifications/read-all`          | `message` says how many were unread |
| `GET`  | `/search/tasks?q=`                 | Up to 8 `TaskSummary` rows the caller may see, open first; fewer than 2 characters returns `[]` |
| `PUT`  | `/me/preferences`                  | Partial: `{ "celebrations": bool }`; returns every preference |
| `GET`  | `/onboarding`                      | `{ visible, steps: [{ key, label, description, done }] }` |
| `POST` | `/onboarding/dismiss`              | Hides the checklist on every device |

A notification:

```json
{
  "id": "9b1f…", "kind": "assigned",
  "message": "Lee Lead assigned you “Fix login bug”", "excerpt": null,
  "task": { "id": 42, "title": "Fix login bug" },
  "actor": { "id": 3, "name": "Lee Lead" },
  "is_read": false, "read_at": null, "created_at": "2026-11-01T09:30:00+00:00"
}
```

`kind` is `assigned`, `commented` (with an `excerpt`), `due_tomorrow` or `overdue`. Nobody is notified
about their own action, and only people who may see the task are notified. Reminders come from the
daily `notifications:due-reminders` command (08:00), once per task and kind.

`GET /me` now includes `preferences` (every known key, defaults filled in).

**Celebrations.** When the caller moves a task from an open status to `done` (for example
`PATCH /tasks/{id}/status`), the response carries an `X-Celebrate` header: URL-encoded JSON
`{ "message": "…", "all_clear": bool }`. It is absent when the user turned `celebrations` off. Show
it however suits the client; the web app shows a small toast.

---

## Role matrix

| Capability                          | Admin | CTO | Tech Lead | Team Lead | Developer | QA | Viewer |
| ----------------------------------- | :---: | :-: | :-------: | :-------: | :-------: | :-: | :----: |
| Projects / teams / releases — read  |  ✅   | ✅  |    ✅     |    ✅     |    ❌     | ❌ |   ✅   |
| Plan releases, off-days, docs       |  ✅   | ✅  |    ✅     |    ✅     |    ❌     | ❌ |   ❌   |
| Manage projects, teams, membership  |  ✅   | ✅  |    ✅     |    ✅     |    ❌     | ❌ |   ❌   |
| Manage user accounts                |  ✅   | ✅  |    ✅     |    ✅     |    ❌     | ❌ |   ❌   |
| Release **detail**                  |  ✅   | ✅  |    ✅     |    ✅     |    ✅     | ✅ |   ✅   |
| Tasks, board, calendar, notes       |  ✅   | ✅  |    ✅     |    ✅     |    ✅     | ✅ |   ✅   |
| Upload release documents            |  ✅   | ✅  |    ✅     |    ✅     |    ✅     | ✅ |   ❌   |
| Tasksheet — read / own row          |  ✅   | ✅  |    ✅     |    ✅     |    ✅     | ✅ |   ✅   |
| Tasksheet **feedback**              |  ✅   | ✅  |    ✅     |    ✅     |    ❌     | ❌ |   ❌   |
| Performance                         |  ✅   | ✅  |    ✅     | own teams |    ❌     | ❌ |   ❌   |
| Competency catalog                  |  ✅   | ✅  |    ✅     |    ❌     |    ❌     | ❌ |   ❌   |
| Activity feed                       |  ✅   | ✅  |    ✅     |    ✅     |    ❌     | ❌ |   ✅   |
| Dashboard                           | timeline | timeline | timeline | timeline | personal | personal | timeline |

---

## Notes for client authors

- **Deactivation is immediate.** When an account is deactivated or deleted its tokens are revoked;
  the next call returns 401. Treat any 401 as "sign out and show the login screen".
- **Poll, don't assume push.** v1 has no websockets. Refresh on window focus and on a modest
  timer. Tasksheet PDF exports are the one place to poll quickly (~2 s) — and only while one is
  running.
- **The dashboard ships geometry, not raw rows.** Bar offsets/widths are percentages of the axis
  and phase segments are positioned relative to their bar — draw them, don't recompute them, or
  your timeline will drift from the web app's.
- **`can_edit` / `can_delete`** appear on comments, events, notes, meeting notes, and quick links.
  Use them for affordances; the server still authorizes every write.
- **Uploads** are `multipart/form-data`, max 20 MB, extensions published at `GET /meta` under
  `document_upload`.

## Code layout

```
routes/api.php                      → mounts versions
routes/api/v1.php                   → /v1 prefix, shared middleware, loads the domain files
routes/api/v1/{auth,account,workspace,releases,collaboration,tasksheet,performance,insights,engagement}.php
app/Http/Controllers/Api/V1/        → controllers (ApiController is the shared base)
app/Http/Resources/V1/              → API resources
app/Http/Requests/Api/V1/           → API-only requests (login, password, board move, …)
app/Services/                       → the domain logic, shared with the web app
```

**The API is not a second implementation.** Every query, calculation, and multi-step write lives in
`app/Services/`, called by both the API controller and its Blade counterpart. An API controller
resolves request input, delegates, and maps the result through a Resource — nothing more. So a
change to a domain rule reaches the desktop client and the browser together, and cannot reach one
without the other.

Validation for domain writes reuses the existing `app/Http/Requests/*` classes, and authorization
reuses the existing policies, gates, and role middleware — so the API and the web app can never
drift apart on a rule. `tests/Feature/Services/WebApiParityTest.php` asserts this directly: the same
records are driven through both surfaces and the numbers must match.

One consequence worth knowing as a client author: where this document's payloads use `snake_case`
and the Blade views use `camelCase`, that is a per-format presentation mapping in the controller,
not two computations. The numbers behind both are identical by construction.
