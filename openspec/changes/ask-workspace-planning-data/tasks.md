## 1. Reading the question

- [x] 1.1 Add `AskQuestion`: search words, topics, named period and quoted task references
- [x] 1.2 Add team, task, release and similar words to the stop words

## 2. Always in the context

- [x] 2.1 State today, the working week and the next days off
- [x] 2.2 Say what the asker's role can't see
- [x] 2.3 Add forecast, latest status, blocked and overdue counts, working days left, off-days and current phase to releases
- [x] 2.4 Put the asker's teams and in-window releases first
- [x] 2.5 Add priority, estimate, logged total, labels, phase, time in status and blocked reason to tasks
- [x] 2.6 Name only the blockers the asker may see
- [x] 2.7 Add blocked, overdue and urgent lists, with totals, narrowed to a named team or release
- [x] 2.8 Find work by quoted reference, person and label

## 3. Asked for by wording

- [x] 3.1 Add `WorkspaceDigests`
- [x] 3.2 Capacity for leads, within `CapacityService::teamsFor`
- [x] 3.3 Flow report for leads and viewers
- [x] 3.4 Logged-hours report for leads, within `TimeReportService`; own hours for everyone but viewers
- [x] 3.5 Requests: own, plus pending ones the asker may decide
- [x] 3.6 The asker's own notifications
- [x] 3.7 Tasksheet plans, results, leave and standup, without feedback or points
- [x] 3.8 Recent changes for a named period or the last seven days
- [x] 3.9 Latest meeting notes and daily notes

## 4. Page and prompt

- [x] 4.1 Link the new references and leave other brackets as text
- [x] 4.2 Add the new references and reading rules to the system prompt, and make follow-up questions stand alone
- [x] 4.3 Offer example questions by role

## 5. Verification

- [x] 5.1 Add `WorkspaceAskDataTest`, covering the data and every role's limits
- [x] 5.2 Run `php artisan test` and `vendor/bin/pint --test`
- [x] 5.3 Make real API calls on a scratch database, with the system prompt and schema as sent
