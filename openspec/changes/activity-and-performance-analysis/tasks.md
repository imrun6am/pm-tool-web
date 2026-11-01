## 1. Shared
- [x] 1.1 Extract `TaskCompletions`; delivery analytics uses it

## 2. Activity
- [x] 2.1 `ActivityInsights::filters/window/windowFeed/analysis` (volume, breakdowns, people, rhythm, flow, deletions)
- [x] 2.2 Web controller and page with period switcher, filters, drill-down links and the feed; API `activities/analysis`

## 3. Performance
- [x] 3.1 Overview: averageChange, competencyTrends, distribution, vsTeam, records/recordTotals; competency catalog memoized
- [x] 3.2 Scorecard: overallChange, strengths/growthAreas, notes, record against the previous window
- [x] 3.3 `_team_depth` / `_member_depth` partials

## 4. Verification
- [x] 4.1 Tests: access per role, team-lead scoping, empty states, archived exclusion, parity
- [x] 4.2 `docs/api-v1.md`
- [x] 4.3 Browser check as admin, team lead and viewer
