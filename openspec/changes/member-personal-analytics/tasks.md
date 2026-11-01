## 1. Service

- [x] 1.1 `MemberPeriod`: resolve week/month/quarter to the current span and the previous same-length span
- [x] 1.2 `MemberAnalyticsService::forUser()`: load tasks, status activities, tasksheet rows, comments and meetings in a fixed number of queries
- [x] 1.3 Derive completion and start times; compute the today strip, focus list, period KPIs with change, weekly series, best week, pace, status breakdown, heatmap, streak, standup and fill rates, lead-time trend and recent completions, with every rate null-guarded

## 2. Delivery

- [x] 2.1 Web `DashboardController` member branch passes `analytics` and `period`
- [x] 2.2 API member branch adds the `analytics` presenter and validates `period`
- [x] 2.3 `docs/api-v1.md`: member payload and `period`

## 3. UI

- [x] 3.1 Partials: column chart, contribution heatmap, delta pill, donut; optional color/guide/label on spark-line
- [x] 3.2 `dashboard-member.blade.php`: period switcher, momentum banner, today strip, focus list, KPI strip, trend, donut, heatmap, habits, recently completed; keep the existing tasks, tasksheet and meetings sections

## 4. Tests

- [x] 4.1 Service tests: completed counts and change, overdue and due soon, cycle time, streak, empty user, isolation, feedback never loaded
- [x] 4.2 Render tests for developer and QA; new-user empty state
- [x] 4.3 Web/API parity for member analytics; API period validation
- [x] 4.4 `php artisan test`, `vendor/bin/pint --test`, browser check as a developer
