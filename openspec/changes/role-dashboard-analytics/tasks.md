## 1. Backend
- [x] 1.1 `App\Support\Metric` (pct/compare), shared with `MemberAnalyticsService`
- [x] 1.2 `DeliveryAnalyticsService`: audience, scope, flow KPIs, backlog, aging, weekly, team load, people, attention, release health, compliance, mine
- [x] 1.3 Web and API `DashboardController` pass `delivery`; API presenter; `docs/api-v1.md`

## 2. UI
- [x] 2.1 `partials/delivery-health.blade.php` below the insights row; "Your work" strip

## 3. Tests
- [x] 3.1 Service tests: audience, KPIs, archive rule, backlog/aging, release flags, team-lead scope, viewer privacy, compliance, query count
- [x] 3.2 Role render order and web/API parity
- [ ] 3.3 Browser check as admin, team lead and viewer
