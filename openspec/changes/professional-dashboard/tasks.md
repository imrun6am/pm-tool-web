## 1. Styles

- [x] 1.1 Add the `.segmented` control and the `.stat-strip` / `.stat-cell` / `.stat-label` / `.stat-value` classes to `resources/css/app.css`

## 2. Lead / Viewer Dashboard

- [x] 2.1 Header: "Dashboard" title, period label plus date range as the description, "New release" for managers
- [x] 2.2 Filter bar: Group by as a segmented Team/Project radio control. Keep the quarter `<select>` and its `@selected` options.
- [x] 2.3 KPI strip: one card, four cells. The conflicts cell uses the warning tone and links to `#timeline` when there are conflicts.
- [x] 2.4 Timeline card (`id="timeline"`):
  - header with title, release count, phase legend and conflict notice;
  - refined axis and group column;
  - bars that keep their phases, links and conflict outline;
  - a "Today" marker via `Timeline::segment`;
  - `x-empty-state` when nothing matches.
- [x] 2.5 Insights row: a Delivery progress donut with a legend, Release load bars with gridlines, and Team workload bars in team colors. Each card has an empty message.

## 3. Member "My day"

- [x] 3.1 Header greeting plus date (keeping "My day"), and a summary strip: open tasks, overdue tasks (danger tone when above zero), meetings in the next 14 days
- [x] 3.2 Task list with a release chip and due pills (overdue in red); `x-empty-state` when there are no tasks
- [x] 3.3 Tasksheet card with the status wording kept verbatim, restyled as badges; meetings card with date tiles

## 4. Tests & Verification

- [x] 4.1 Feature tests: the timeline renders before the insights; the header shows the period and range; the "Today" marker appears for the current quarter and is absent for a past year; the conflicts cell links to `#timeline`
- [x] 4.2 Feature test: the "My day" summary shows the open, overdue and meetings counts, with the overdue cell in the danger tone
- [x] 4.3 Run `php artisan test`: the full suite passes with no edits to existing tests
- [x] 4.4 Run `npm run build`. Take headless screenshots at 1440 and 390 for a lead, a viewer and a developer, with no overflow and no JS errors.
