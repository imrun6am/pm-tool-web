## Context

`dashboard.blade.php` (341 lines) shows these sections, in order: filter bar, four big KPI cards, a delivery-progress card, two charts, a loose legend, and finally the timeline. `dashboard-member.blade.php` is the "My day" page for developers and QA. Both receive data from `DashboardService` through `DashboardController`. A web/API parity test compares that view data with `/api/v1/dashboard`, so this change is limited to views and CSS.

## Goals / Non-Goals

**Goals:** make the timeline the main element; give both dashboards a polished, information-dense overview; reuse the component system from `modernize-ui-components-and-pages`.

**Non-Goals:**
- new metrics or queries;
- interactive charts with a JS chart library;
- drag-to-reschedule on the timeline;
- changes to the API.

## Decisions

### 1. Layout order
The order is header → filters → KPI strip → timeline → insights.

The KPI strip is one card using `grid-cols-2 lg:grid-cols-4` with `divide-x`/`divide-y`. That makes it about 96px tall, instead of the current ~130px of separate cards. The conflicts cell is an `<a href="#timeline">` when there are conflicts.

*Alternative considered:* a two-column layout with the timeline beside the charts. The timeline needs at least about 900px of width, so it gets the full row.

### 2. Today marker
`Timeline::segment(today(), today(), $rangeStart, $rangeEnd)` returns `visible` and `offset`. This is the same function that positions the bars, so the marker lines up with them. The marker is an absolutely positioned 2px brand line inside each row's bar area, plus a "Today" pill in the month axis. It is only drawn when `visible` is true.

### 3. Group-by segmented control
The two values are radio inputs (`name="group_by"`) inside labels styled as a segmented control (`.segmented`). Because the `x-filter-bar` form already submits on `change`, no extra JS is needed.

Quarter stays a `<select>`, because `ReleasePlanningTest` asserts `value="N" selected`.

### 4. Donut chart
The donut is inline SVG: a circle with `r=15.9155` (circumference 100), so each status's `stroke-dasharray="{pct} {100-pct}"` and cumulative `stroke-dashoffset` map straight from percentages. It is rotated −90° so it starts at 12 o'clock. The center shows `donePct`. Colors match the existing `$statusFill` map. The SVG has `role="img"` and an `aria-label` that summarizes the counts.

### 5. Member page
The summary strip is computed in the view from `$tasks->count()`, `$overdueCount` and `$meetings->count()`. The greeting is "Good morning/afternoon/evening, {first name}". The tasksheet status texts ("Not filled — fill now", "Partially filled · n/m", "Filled ✓", leave labels) are kept verbatim and restyled as badges. The summary labels avoid "filled" wording, because the tests assert those strings are absent.

### 6. CSS
- `.segmented`: an inline-flex group with a slate-100 track and a white, raised `:has(:checked)` option. It reuses the filter height of 36px and has a `focus-within` ring.
- `.stat-strip` / `.stat-cell`: the KPI layout, shared by both dashboards.

## Risks / Trade-offs

- **The marker could disagree with the bars.**
  - *Mitigation:* it uses the same `Timeline::segment` function.
- **`:has()` support** is Chrome/Edge 105+, Safari 15.4+ and Firefox 121+, which is acceptable for this internal tool.
  - *Fallback:* the radio buttons still work; the checked option just isn't raised.
- **The timeline moving above the charts** changes where returning users look.
  - *Accepted:* it is the page's main purpose, and the charts are one scroll away.

## Migration Plan

Views and CSS only. Run `npm run build`. Rollback is a revert.
