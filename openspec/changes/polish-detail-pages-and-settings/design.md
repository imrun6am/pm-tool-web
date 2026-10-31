## Context

The shell, the components, the list pages, the forms and the main dashboard are modern. What remains are the profile page, a few detail pages, and the summary rows of the dashboard-style pages. The existing tests pin parts of these pages' text, such as "Write meeting note" on events and the release completion and quick-link copy on releases. The profile page must not render a sidebar `aria-current` item.

## Goals / Non-Goals

**Goals:**
- a full-width, professional settings page;
- one KPI-strip pattern across every dashboard-style page;
- detail pages that share the breadcrumb + main column + side panel anatomy.

**Non-Goals:**
- new settings such as avatars, notification preferences or 2FA;
- new data on any page;
- redesigning the kanban board or the release form.

## Decisions

### 1. Settings layout
The page uses `app-container` at full width with `grid lg:grid-cols-[14rem_minmax(0,1fr)] gap-8`.

The section nav is `lg:sticky lg:top-24`. An Alpine `IntersectionObserver` (`rootMargin: '-40% 0px -55% 0px'`) tracks the section in view and toggles its active class. It falls back to the first section, and it marks the active link with `aria-current="true"`, which is not `page`, so the sidebar's "no current item" rule on the profile page still holds. Below `lg` the nav is a horizontally scrollable row of pill tabs.

The sections are cards with `id="profile"`, `id="password"` and `id="danger-zone"` and `scroll-mt-24`. Fields sit two per row (name/email; new/confirm password) instead of being capped at `max-w-md`, which uses the width the user asked for.

*Alternative considered:* a separate route per settings tab. That adds routes and controller changes for three short forms.

### 2. Account overview
A card with a soft brand-gradient band, an `x-avatar size="xl"`, the name, email, role badge, "Member since {M Y}" (from `created_at`), and team chips from `$user->teams`. It is read-only.

### 3. KPI strip
`x-stat` renders one `stat-cell` (an icon in a tone color, the label, the value, an optional sub-line, an optional `href`, and an `alert` warning state). Pages wrap the cells in `<div class="stat-strip">`. Three-cell pages add `lg:grid-cols-3`.

### 4. Detail pages
These pages follow the meeting-note detail layout: `x-page-header` with breadcrumbs and actions, then `grid lg:grid-cols-[minmax(0,1fr)_20rem] items-start gap-6`. The side panel uses the `card` + `card-header` + `dl` pattern from the meeting-note detail. "Back to …" links are removed; the breadcrumbs replace them.

## Risks / Trade-offs

- **Tests that pin detail-page copy.**
  - *Mitigation:* grep the tests before editing each page, and keep their strings verbatim.
- **The scroll-spy could flicker on short pages.**
  - *Mitigation:* the active section only changes when a section crosses the middle band. Clicking a nav link sets it immediately.

## Migration Plan

Views only. Run `npm run build`. Rollback is a revert.
