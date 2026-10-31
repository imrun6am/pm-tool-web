# Release Planner

A project-management tool for planning software releases across multiple projects and teams,
built with **Laravel + Blade**. Each release is broken into **Development → QA → Retest → Release**
phases, owned by a single team, and drawn on a shared **timeline dashboard** that flags whenever a
team is double-booked.

Built spec-first with [OpenSpec](https://github.com/Fission-AI/OpenSpec) — see
[`openspec/changes/release-planning-tool/`](openspec/changes/release-planning-tool/) for the
proposal, specs, design, and task list this implementation follows.

## Features

- **Timeline dashboard** — every release as a bar on a year (or quarter) axis, with colored
  phase segments. Filter by year, quarter, project, and team; group by team or project.
- **Overlap detection** — when a team's releases overlap, they are **warned on save** (never
  blocked) and **highlighted in amber** on the dashboard, team page, and release page.
- **Releases** — project, owning team, quarter/year, overall window, an optional **description**,
  and four phase date ranges (with an "auto-split window evenly" helper).
- **Tasks & subtasks** — each release has tasks with status (To Do / In Progress / In Review /
  Done), assignee, due date, and an optional phase link; one level of subtasks with progress.
- **Comments** — threaded comments on both releases and tasks, attributed to their author;
  author or admin can edit/delete.
- **Off-days** — mark non-working days within a release window (with a reason and a "mark
  weekends" helper); shown on the timeline and used for a working-day count.
- **Kanban board** — a Trello-style board with To Do / In Progress / In Review / Done columns;
  drag cards to change status and reorder them. Available globally and per-release, filterable
  by release and assignee.
- **Calendar** — a month view of events (Meeting / Review / Deadline / Release / Other) with
  attendees and optional release links. Any signed-in user adds events; the creator or an admin
  edits/deletes.
- **Activity log** — an attributable, app-wide history of every create/update/delete (with
  who, what changed old → new, and when), on a global **Activity** page and a per-release
  history panel.
- **Projects & Teams** — CRUD with colors; archive when they have releases, hard-delete when empty.
- **Documents** — upload/list/download/delete files per release (max 20 MB; pdf, doc(x), xls(x),
  ppt(x), txt, csv, png, jpg, zip).
- **Users & roles** — six roles: **Admin, CTO, Team Lead, Developer, QA, Viewer**. Only Admin
  manages structure (projects, teams, releases, off-days, documents); **Admin + CTO** manage
  user accounts (create, set role, reset password, deactivate); **every signed-in user
  collaborates** on tasks, comments, board, and calendar. Self-registration is disabled and
  deactivated accounts cannot log in. Enforced by middleware and policies, not just hidden buttons.

## Requirements

- PHP 8.3+ and Composer
- Node 18+ and npm

## Setup

```bash
composer install
npm install

cp .env.example .env        # if you don't already have a .env
php artisan key:generate

php artisan migrate --seed  # creates schema + demo data
npm run build               # or: npm run dev
php artisan serve
```

Open http://127.0.0.1:8000 and log in.

### Demo logins (from the seeder)

All demo accounts use the password `password`. Accounts are created by an Admin/CTO in
**Users** — there is no public sign-up.

| Role      | Email                |
| --------- | -------------------- |
| Admin     | `admin@example.com`  |
| CTO       | `cto@example.com`    |
| Team Lead | `lead@example.com`   |
| Developer | `dev@example.com`    |
| QA        | `qa@example.com`     |
| Viewer    | `viewer@example.com` |

The seed data includes a deliberate same-team overlap (**Team Alpha** — *Checkout v2.4* Jul 10–30
and *Billing hotfix* Jul 20–Aug 5) so the conflict warning and amber highlight are visible
immediately on the dashboard.

## Database

This app ships configured for **SQLite** (`database/database.sqlite`) so it runs with zero database
setup. To use **MySQL** instead, set the following in `.env` and re-run `php artisan migrate --seed`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pm_tool
DB_USERNAME=root
DB_PASSWORD=
```

> Note: SQLite was chosen because the local MySQL server on the build machine had an unrelated
> version-mismatch issue. The schema is standard and portable — switching to MySQL is just the
> `.env` change above.

## REST API (for the desktop client)

Everything above is also reachable over a versioned REST API at **`/api/v1`**, so a
cross-platform desktop application can serve all seven roles with exactly the access each role
has in the browser. See **[`docs/api-v1.md`](docs/api-v1.md)** for the full reference.

```bash
# 1. exchange credentials for a token
curl -X POST http://127.0.0.1:8000/api/v1/auth/login \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d '{"email":"admin@example.com","password":"password","device_name":"Desktop"}'

# 2. use it
curl http://127.0.0.1:8000/api/v1/dashboard \
  -H 'Authorization: Bearer <token>' -H 'Accept: application/json'
```

- **Auth** — Laravel Sanctum personal access tokens; stateless, no CSRF. Multi-device with
  per-device revocation. Deactivating an account revokes its tokens immediately.
- **Discovery** — `GET /me` returns the caller's effective permission flags; `GET /meta` returns
  every domain enumeration (statuses, phases, colors, visibilities, the 1–5 scale). Clients build
  their navigation and pickers from these instead of hard-coding them.
- **Role parity** — the API reuses the *same* policies, gates, and role middleware as the web
  routes, so a permission rule cannot drift between the two surfaces. Restricted fields
  (tasksheet `feedback`, performance scores) are **omitted** from payloads, not merely hidden.
- **Additive** — `routes/web.php` and the Blade app are untouched.

Built spec-first: see
[`openspec/changes/desktop-rest-api-v1/`](openspec/changes/desktop-rest-api-v1/) for the proposal,
capability specs, design, and task list.

## Tests

```bash
php artisan test
```

Covers the overlap predicate and timeline math (unit) plus release creation, phase validation,
the overlap warning, and role gating (feature). `tests/Feature/Api/V1/` covers the API by
capability spec: token auth and device revocation, role parity for all seven roles,
restricted-field omission, and the domain rules the API must preserve.

## Architecture notes

- **Domain model:** `Project`, `Team`, `Release` (belongs to one project + one team), `ReleasePhase`
  (four ordered rows per release), `ReleaseDocument`, `Task` (self-referencing `parent_id` for one
  level of subtasks), `Comment` (polymorphic — releases + tasks), `ReleaseOffDay`, `Activity`.
- **Service layer (`App\Services\`) is where domain logic lives.** The web app and the REST API are
  two delivery mechanisms over one implementation: a controller resolves its input, delegates to a
  service, and presents the result. Controllers hold no queries, no date arithmetic, and no
  multi-step writes. `DashboardService`, `ReleaseService`, `TaskService`, `BoardService`,
  `TasksheetService`, `PerformanceEvaluationService`, `ActivityInsights`, `EventService`,
  `NoteService`, `MeetingNoteService`, `QuickLinkService`, `TeamService`, `ProjectService`, and
  `UserService` each own one aggregate's rules.
  - Services return **domain values** — models, Carbon instances, collections — never responses,
    resources, or formatted strings. That is what lets one computation serve Blade and JSON: the
    web controller passes it to a view, the API controller maps it through a Resource. Field
    *naming* per format is the only thing that differs.
  - **Authorization stays out of the services**, in middleware, policies, and gates. A service may
    scope a query by viewer (which records) but never decides whether the actor may act.
  - `tests/Feature/Services/WebApiParityTest.php` drives the same records through both surfaces and
    asserts the numbers agree, so a reintroduced copy of a query fails the suite.
- **`App\Services\OverlapChecker`** is the single definition of "two same-team windows overlap,"
  reused by the save-time warning and the dashboard highlight.
- **`App\Support\Timeline`** does pure date→percent math for the timeline (offset/width, clipping,
  month columns) and is unit-tested independently of the database.
- **`App\Models\Concerns\RecordsActivity`** is a lightweight in-app audit trait (no external
  package) added to auditable models; it records create/update/delete with the causer and, for
  updates, old → new values, denormalizing a `release_id` for fast per-release history.
- **Permissions:** the `admin` middleware (`EnsureUserIsAdmin`) guards structural write routes;
  task/comment routes sit under `auth` so any signed-in user can collaborate; `CommentPolicy`
  limits comment edit/delete to the author or an admin.
