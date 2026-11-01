## Decisions

1. **Scope versus slice (activity).** Scope filters narrow every number. Slice filters (event, record type, dates) narrow only the feed, so drilling from a bar never collapses the chart it came from. The feed is bounded by the window unless a drill-down sets its own dates. The all-time `totals()` stays for the KPI footnote and the existing parity test.
2. **Weekday × hour** is grouped in SQL with a per-driver expression (SQLite `strftime`, MySQL `dayofweek`/`hour`, Postgres `extract`), in app time. That's one query, with no row fetch.
3. **Workflow dwell time** = the time from entering a status to leaving it, for moves inside the window. Entry times come from up to 60 days before the window.
   - A task's `created` row may omit `status`, because the column default never sat on the model. So creations count as `todo`.
   - A move whose diff lost its old value uses the status the task last entered.
4. **Rework rate:**
   - **activity:** moves into Recheck ÷ moves into Done or Recheck;
   - **delivery and performance:** the share of finished tasks that were ever in Recheck.
5. **Objective record** covers 4 weeks ending at the selected week, never past today.
   - Tasksheet: this team's rows only.
   - Tasks: this team's tasks or teamless ones. Completions come from `TaskCompletions`; overdue uses `Task::counted()`.
   - Tasksheet "owed" days are weekdays up to yesterday, plus today once filled, with full-day leave excluded. Owed days with nothing logged are a real 0%. A rate with no denominator is null.
6. **Strengths and growth areas** are split at 3.5 on the member's own 8-week competency averages. They are relative to the member, never ranked against colleagues. They are lead-only, like the rest of the page.
7. **API parity:**
   - The performance API already returns the analytics arrays as-is (camelCase), so the new keys reach it unchanged.
   - Activity analysis gets a snake_case presenter, like other insights endpoints.
