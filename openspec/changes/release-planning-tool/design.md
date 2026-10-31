## Context

Greenfield Laravel 12 application built in an empty directory. Toolchain confirmed: PHP 8.4, Composer 2.10, Laravel Installer 5.28, MySQL 9.7. The tool coordinates release planning across many projects and teams over quarters, with the defining constraint that a single team owns a release for its whole window and must not be silently double-booked. The four decisions confirmed with the user drive the model: overlaps warn (not block), phases have their own dates, one team per release, and access is gated by Admin/Viewer roles.

## Goals / Non-Goals

**Goals:**
- A single dashboard timeline showing every release with Dev/QA/Retest/Release phase segments.
- Same-team overlap detection surfaced both as a save-time warning and a dashboard highlight.
- Full CRUD for Projects, Teams, and Releases (with phases), gated so only admins write.
- Document upload/list/download/delete per release.
- Filtering by year/quarter/project/team, defaulting to the current year.

**Non-Goals:**
- No per-phase team assignment (one team owns the whole release).
- No hard scheduling engine or auto-resolution of conflicts — overlaps are advisory.
- No external Gantt library, calendar sync, notifications, or time tracking.
- No public API; server-rendered Blade only for this version.

## Decisions

**Framework & stack — Laravel 12 + Blade + Breeze (Blade) + Tailwind + MySQL.**
Matches the user's explicit request (Laravel, Blade). Breeze gives login/registration/password scaffolding with Tailwind already wired, minimizing hand-rolled auth. Alternative (Jetstream/Livewire, Inertia+Vue) rejected as heavier than needed for server-rendered CRUD + one timeline view.

**Roles via a single `role` enum column + Gate/Policy, not a permissions package.**
Only two roles with a simple rule (admin writes, viewer reads). A `role` string column on `users` plus a `Gate::before`/policies and an `admin` middleware alias is enough. Spatie/laravel-permission rejected as overkill for a binary distinction. Blade uses `@can`/`@if(auth()->user()->isAdmin())` to hide write controls from viewers.

**Data model.**
- `projects`: name (unique among active), description, color, `archived_at` (nullable).
- `teams`: name (unique among active), description, color, `archived_at` (nullable).
- `releases`: project_id, team_id, name, year (int), quarter (enum Q1–Q4 / 1–4), start_date, end_date. Indexed on (team_id, start_date, end_date) for overlap queries and (year, quarter).
- `release_phases`: release_id, phase (enum development|qa|retest|release), position (0–3), start_date, end_date. Exactly four rows per release, created/updated with the release.
- `release_documents`: release_id, uploaded_by (user_id), original_name, path, mime_type, size.
Foreign keys cascade: deleting a release deletes its phases and documents (and their files, handled in the model's `deleting` event so storage is cleaned up).

**Phases stored as rows, not four column pairs.**
A `release_phases` table (one row per phase) keeps the timeline rendering and validation uniform (iterate segments) and leaves room to reorder or extend phases later. The canonical four phases are always present; the create/edit form collects four date ranges and the controller upserts them in order. Alternative (8 date columns on `releases`) rejected as rigid and awkward to render.

**Overlap detection — a query-based domain service, reused by save and dashboard.**
`OverlapChecker` finds, for a given team + window (excluding the release being edited), all releases where `start_date <= :end AND end_date >= :start`. On store/update the controller runs it and flashes a warning listing conflicts; it never blocks the save. The dashboard runs the same check per team to flag bars. Centralizing avoids two divergent definitions of "overlap." Validation enforces structural rules (end ≥ start, phases within window, phases end ≥ start); overlap is explicitly *not* a validation failure.

**Timeline rendering — server-computed CSS positioning, no JS charting library.**
The controller computes the visible date range (from filters, default current year) and passes each release/phase a left-offset % and width % (days-from-range-start ÷ range-length). Blade renders absolutely-positioned bars inside a scrollable rail with a month/week header; phase segments are nested colored spans. Minimal vanilla JS only for filter submission and tooltips. Rejected a JS Gantt dependency to keep the artifact self-contained and the math auditable server-side.

**Document storage — local `storage/app/private` disk via `storage:link` not required for private files; downloads streamed through a controller** so access stays behind auth and the original filename is preserved. Validation caps size (e.g. 20 MB) and restricts extensions (pdf, doc(x), xls(x), ppt(x), txt, csv, png, jpg, zip).

**Seeder** creates an admin and a viewer user, a handful of projects and teams with colors, and releases that include at least one deliberate same-team overlap so the warning and dashboard highlight are demonstrable immediately.

## Risks / Trade-offs

- [Server-rendered timeline math is fiddly across month boundaries] → Keep a single helper that maps a date to an x-offset within the active range; unit-test the offset/width helper and the overlap predicate.
- [Editing a release's window can retroactively push phases out of range] → Validate phases against the (new) release window on every update; reject with a clear message.
- [Warn-but-allow means the DB can hold real conflicts] → That is intended; the dashboard highlight and team page keep conflicts visible so a human resolves them.
- [Viewers could hit write routes directly] → Enforce with `admin` middleware + policies on the server, not just hidden buttons in Blade.
- [Large uploads / disallowed types] → Enforce `max` size and an allow-list of mime/extensions in the form request; store outside the public webroot and stream via controller.
- [Duplicate names after archiving] → Uniqueness is scoped to non-archived rows via a conditional/validation rule, so an archived project's name can be reused.

## Migration Plan

1. Scaffold Laravel 12, install Breeze (Blade), configure `.env` for a local MySQL database, run `migrate`.
2. Add migrations for the role column and the five domain tables; add models, relations, policies, and the `admin` middleware.
3. Build controllers, form requests, Blade views, and the dashboard timeline; wire routes.
4. Seed demo data (including an intentional overlap) and verify the dashboard, warnings, and document flow manually.
Rollback: drop the database / `migrate:fresh`; the app is greenfield with no external consumers.

## Open Questions

- None blocking. Registration is left enabled (new users default to `viewer`); an admin can promote a user's role. If self-registration should be disabled later, remove the Breeze register route — deferred, not required for this version.
