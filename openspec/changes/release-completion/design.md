## Context

Releases currently live only on the dashboard timeline; there is no index and no lifecycle state. This adds a completed state (with rich notes), removes finished work from the planning views, and adds an all-releases list so completed releases stay reachable.

## Goals / Non-Goals

**Goals:** mark complete / reopen with an audit; Markdown completion notes rendered safely; hide completed releases from the dashboard and overlap checks; an all-releases list with filters.

**Non-Goals:** no multi-state workflow (draft/active/blocked/…) — just ongoing vs completed; no per-phase completion; no WYSIWYG editor (Markdown text + server render); no approvals.

## Decisions

**Completion as nullable columns, not a status enum.** `completed_at` (timestamp), `completed_by` (user, nullOnDelete), `completion_notes` (text) on `releases`. `isComplete()` = `completed_at !== null`. Scopes `ongoing()` (`whereNull('completed_at')`) and `completed()`. This mirrors the existing `archived_at` pattern on projects/teams and avoids a status column when the state is binary.

**Rich notes via `Str::markdown()` with safe options.** Notes are stored as raw Markdown and rendered with `Str::markdown($notes, ['html_input' => 'strip', 'allow_unsafe_links' => false])` (CommonMark ships with the framework). Stripping raw HTML input prevents stored XSS; the form advertises "Markdown supported." A rendered-notes accessor centralizes this.

**Hiding is a query filter, applied in two places.** `DashboardController` adds `->whereNull('completed_at')` to its release query and to the per-team overlap query; `OverlapChecker::conflictsFor()` adds `whereNull('completed_at')` so save-time warnings ignore finished releases. One predicate, applied where releases are gathered — completed releases simply aren't considered "booking" the team.

**Completion flow on the release page, not a separate screen.** A completion panel: when ongoing, an admin sees a notes textarea + "Mark complete"; when completed, everyone sees a Completed badge, who/when, and the rendered notes, and an admin sees "Reopen." Routes: `POST releases/{release}/complete` and `POST releases/{release}/reopen` (admin-gated). Small dedicated actions keep the release update form untouched.

**All-releases list is `ReleaseController@index`.** A table over every release with a status badge, filters (status/project/team/year) combined server-side, ordered by year/quarter/start. Read-only (auth), so any role can browse history. A "Releases" nav link points here; the dashboard stays the ongoing-only timeline.

## Risks / Trade-offs

- [Completed release still referenced by tasks/events] → Fine; completion only affects planning visibility, not the record. Tasks/comments/events remain.
- [Markdown XSS] → Mitigated by `html_input => strip` + `allow_unsafe_links => false`.
- [A user expects completed releases on the dashboard] → Intentional per the brief; they remain one click away in the Releases list and on their own page.
- [Overlap now ignores completed releases] → Desired: a shipped release no longer occupies the team.

## Migration Plan

1. Migration: add the three completion columns.
2. Model scopes/relation/accessor/helpers.
3. Routes + controller actions (index, complete, reopen); dashboard + OverlapChecker filters.
4. Views: releases index + nav link; completion panel/badge on the release page.
5. Seed one completed release; migrate, build, test, smoke.
Rollback: drop the columns; remove routes/views/link.

## Open Questions

- None blocking. A richer lifecycle (statuses, sign-off) is a later change if needed.
