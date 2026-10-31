## Context

The app is server-rendered Blade + Alpine with form POSTs and redirects; there is no ajax-partial pattern beyond the board's move endpoint. The drawer must exist on **every** page, so its data can't come from individual controllers. Daily notes already model per-user `private`/`shared` visibility (`Note::VISIBILITIES`, `scopeVisibleTo`, author-only `NotePolicy`) — quick links copy that shape.

## Goals / Non-Goals

**Goals:**
- One-click access to links from any page via a right-hand slide-over drawer.
- Private-by-default links with opt-in sharing to all authenticated users.
- Manage (add/edit/delete) entirely inside the drawer — no dedicated page.
- Available to every role, including limited (developer/QA) users.

**Non-Goals:**
- No categories/folders, tags, or reordering in v1 (newest first).
- No per-team or per-release scoping — visibility is personal vs global, like daily notes.
- No favicon fetching or link previews.
- No dedicated full-page view; the drawer is the whole UI.

## Decisions

1. **`quick_links` table**: `user_id` (FK cascade — personal bookmarks die with the account, and shared ones are light enough not to warrant preservation), `release_id` (nullable FK `nullOnDelete`, the established "release-wise or general" pattern — deleting a release turns its links into general ones), `label` (string), `url` (string 2048), `visibility` (string, default `private`), timestamps, index (`user_id`), index (`visibility`), index (`release_id`). Mirrors `notes` naming (`VISIBILITY_PRIVATE`/`VISIBILITY_SHARED` consts, `scopeVisibleTo`, `isShared()`).

1a. **Release "Links" section** — `releases/show` gains a **"Links" card in the main column** (below the Meeting notes card — deliberately *not* in the sidebar, and titled "Links", not "Quick links"): that release's links filtered by `visibleTo` (a private release link is still author-only, even here), plus a small inline add form (label, URL, visibility) posting with a hidden `release_id`. Release attachment in the drawer's add/edit forms is an optional release select listing **ongoing releases only** (consistent with the meeting-notes rule, and it keeps the ever-present dropdown from listing completed releases on pages that promise not to show them); links already attached to a since-completed release keep their badge and stay useful. Release-linked links show a release badge in the drawer (plain text for limited users, who cannot open release pages).

2. **Data via a view composer, not per-controller** — `AppServiceProvider` registers a composer on the drawer partial that provides `QuickLink::visibleTo($user)` (own links + others' shared), own-first then newest-first. Alternatives rejected: passing from every controller (100+ call sites), lazy ajax fetch (introduces a new pattern for a tiny payload).

3. **Drawer UI**: Alpine `x-data="{ open: … }"` slide-over included from `layouts/app.blade.php`, opened by a **floating link-icon handle fixed to the right screen edge** (hidden while the drawer is open). Inside: an **"Add link" button on top** that expands the add form (collapsed by default; auto-expanded when validation errors flash back), and beneath it all links — "My links" (own, with a Private/Shared badge and inline edit/delete) then "Shared by others" (read-only, author name shown). Links open `target="_blank" rel="noopener"`. Add/edit forms are plain POST/PUT forms inside the drawer.

4. **Redirect + reopen** — saves redirect `back()` with a `quick-links-open` session flash; the drawer initializes open when the flash is present, so add → save → see the new link feels seamless despite full page loads. No ajax needed.

5. **Authorization** — `QuickLinkPolicy`: author-only update/delete (exactly `NotePolicy`'s stance — private bookmarks are personal, admins don't manage them). Creation open to any authenticated user; routes live in the collaboration section (NOT behind `full-access`, so developers/QA keep them).

5a. **Limited roles are private-only** — for developers/QA (`hasLimitedAccess()`): the drawer lists **only their own links** (no "Shared by others" section), the add/edit forms have **no visibility selector** (always private), and `QuickLinkRequest` rejects a `shared` visibility from a limited user server-side (a crafted request errors rather than being silently downgraded). The `visibleTo` scope branches on the viewer's role: limited → own links only; full-access → own + others' shared. Rationale: sharing is a team-communication surface and follows the same role split as the planning pages.

6. **Validation** (`QuickLinkRequest`): `label` required max:100; `url` required, `url:http,https`, max:2048; `visibility` in private|shared. URLs render escaped and only ever in `href` of a plain anchor — the `http/https` protocol restriction blocks `javascript:` URIs.

## Risks / Trade-offs

- [Composer query runs on every page load] → One indexed query returning a handful of rows; measured cost is negligible at this app's scale. If it grew, the drawer could lazy-load — the partial boundary makes that a drop-in change.
- [Shared links are global, not team-scoped] → Accepted for v1 (matches daily notes); per-team scoping noted as a future change if link volume grows.
- [Drawer forms lose unsaved input on navigation] → Forms are tiny (two fields); acceptable.
