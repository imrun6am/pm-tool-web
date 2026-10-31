> Implementation notes: the environment installed **Laravel 13** (not 12) and the local **MySQL**
> server had an unrelated version-mismatch fault, so the app runs on **SQLite** (portable; switch
> via `.env`). Everything else follows the design. 42 tests pass; the running app was smoke-tested
> for admin CRUD, the overlap warning, viewer read-only enforcement, and the dashboard timeline.

## 1. Scaffold & configuration

- [x] 1.1 Create a Laravel app in this directory (temp dir + rsync, preserving `openspec/` and `.claude/`)
- [x] 1.2 Configure `.env` and run initial `migrate` (SQLite; MySQL blocked by a local server fault)
- [x] 1.3 Install Breeze (Blade + Tailwind) auth scaffolding; run `npm install`
- [x] 1.4 Verify the app boots (`php artisan serve` / route list) and login page renders

## 2. Auth & roles (spec: auth-and-roles)

- [x] 2.1 Migration: add `role` enum column (`admin`/`viewer`, default `viewer`) to `users`
- [x] 2.2 User model: cast/fillable `role`, add `isAdmin()`/`isViewer()` helpers
- [x] 2.3 An `admin` route middleware alias for write routes
- [x] 2.4 Blade uses role checks to hide write controls from viewers
- [x] 2.5 New registrations default to `viewer` (DB default)

## 3. Data model & migrations (design: data model)

- [x] 3.1 Migration + model `Project` (name, description, color, archived_at) with active-name uniqueness
- [x] 3.2 Migration + model `Team` (name, description, color, archived_at) with active-name uniqueness
- [x] 3.3 Migration + model `Release` (project_id, team_id, name, year, quarter, start_date, end_date) with indexes on (team_id, start_date, end_date) and (year, quarter)
- [x] 3.4 Migration + model `ReleasePhase` (release_id, phase, position, start_date, end_date)
- [x] 3.5 Migration + model `ReleaseDocument` (release_id, uploaded_by, original_name, path, mime_type, size)
- [x] 3.6 Define Eloquent relations; cascade delete phases + documents (and stored files) when a release is deleted

## 4. Overlap detection service (spec: release-planning, planning-dashboard)

- [x] 4.1 `OverlapChecker` service: given team + window (excluding a release id), return overlapping releases using `start <= :end AND end >= :start`
- [x] 4.2 Unit test the overlap predicate (touching, contained, disjoint, different-team cases)

## 5. Projects CRUD (spec: project-management)

- [x] 5.1 Controller + form request (unique active name, color) + resource routes (admin-gated writes)
- [x] 5.2 Blade index/create/edit/show views with archive action; block hard-delete when releases exist; allow delete when empty

## 6. Teams CRUD (spec: team-management)

- [x] 6.1 Controller + form request + resource routes (admin-gated writes)
- [x] 6.2 Blade index/create/edit views with archive action and empty-delete rule
- [x] 6.3 Team show page: that team's releases in chronological order with overlaps highlighted

## 7. Releases + phases CRUD (spec: release-planning)

- [x] 7.1 Form request: project/team required, end ≥ start, four phase ranges within window and each end ≥ start
- [x] 7.2 Controller store/update: upsert the four phases in canonical order; run `OverlapChecker` and flash a warning listing conflicts (never block)
- [x] 7.3 Controller destroy: delete release with phases + documents
- [x] 7.4 Blade create/edit form: project & team pickers (active only), year/quarter, overall window, four phase date rows
- [x] 7.5 Blade show page: release details, phase segments, overlap warning banner, documents panel

## 8. Release documents (spec: release-documents)

- [x] 8.1 Upload endpoint + form request (size cap, allowed extensions/mime); store on local private disk with original name recorded
- [x] 8.2 Streamed download endpoint (auth-gated, original filename) and admin delete (removes file + record)

## 9. Planning dashboard (spec: planning-dashboard)

- [x] 9.1 Dashboard controller: apply year/quarter/project/team filters (default current year); compute active date range
- [x] 9.2 Timeline helper: map a date to left-offset % and width % within the active range (unit-tested)
- [x] 9.3 Blade timeline: date-axis header, release bars with nested phase segments, project/team/window labels, empty state
- [x] 9.4 Group-by-team view and visual highlight of same-team overlapping bars/regions
- [x] 9.5 Filter controls (year, quarter, project, team) that combine

## 10. Seed data & verification

- [x] 10.1 Seeder: admin + viewer users, several colored projects/teams, releases with phases including at least one deliberate same-team overlap
- [x] 10.2 Manual pass: login as admin (CRUD + upload + overlap warning) and as viewer (read-only); confirm dashboard timeline, filters, and overlap highlight
- [x] 10.3 Run `openspec validate` and the test suite; write a short README with setup + demo login credentials

## 11. Revision: default timeline to the current quarter

- [x] 11.1 Dashboard defaults to the current quarter on a fresh visit; explicit "All quarters" still shows the whole year
- [x] 11.2 Feature test covering the default and the all-quarters override
