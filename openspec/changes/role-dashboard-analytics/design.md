## Decisions

1. **Scope follows the dashboard filters.** The project and team filters narrow delivery health too, so the numbers and the timeline describe one slice. Task scope is `tasks.team_id`, plus the release's project when a project is filtered. A team lead without a filter is scoped to `ledTeams()`, and falls back to all teams if they lead none.
2. **Rolling 28-day window, not the quarter.** The quarter filter frames the timeline. Flow metrics need a recent, comparable window that doesn't reset on the 1st.
3. **Completions come from the audit trail,** as in `member-personal-analytics`. Candidates are done tasks with `updated_at` inside the window. That bounds the read, because a task completed in the window was necessarily saved in it. **Rework** = the task was ever moved to `recheck`.
4. **Release risk is explainable.** Every flag has a reason the UI shows:
   - the team is double-booked (the existing overlap check);
   - a phase has ended with open tasks tagged to it;
   - the done share trails time used by 25 or more points, once at least 20% of the time is used;
   - the release is past its end date and not complete.
5. **Compliance** covers the last 10 weekdays before today. "Filled" uses the same every-field rule as `TasksheetEntry::isFullyFilled()`, computed in SQL. Full-day leave is excluded from what's owed, and members are only owed rows from their join date.
6. **Viewer privacy:** `people`, `attention` and `compliance` are null, and their cards are not rendered.
7. **Query budget:** about 17 queries, all aggregates or capped lists, independent of history size. A test guards this. No cache: the dashboard is per-user and filter-specific, and the queries are cheap.
