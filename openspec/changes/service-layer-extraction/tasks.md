## 1. Dashboard

- [x] 1.1 Add `App\Services\DashboardService` with `rangeFor()`, `availableYears()`, `timelineReleases()`, `conflictFlags()`, `groups()`, `analytics()`, and `memberSnapshot()`
- [x] 1.2 Return bars as `['release' => Release, 'offset', 'width', 'conflict', 'phases' => [...]]` with Carbon phase bounds, matching what the Blade views already consume
- [x] 1.3 Rewire web `DashboardController` to the service, keeping its Blade payload byte-identical
- [x] 1.4 Rewire `Api\V1\DashboardController` to the service, with a private presenter mapping to the published snake_case JSON contract
- [x] 1.5 Run the full suite

## 2. Release planning

- [x] 2.1 Add `App\Services\ReleaseService` with `filtered()`, `create()`, `update()`, `complete()`, `reopen()`, `conflictsFor()`, and `overlapMessage()`
- [x] 2.2 Move `syncPhases()` and `syncOffDays()` into the service, inside its own transaction
- [x] 2.3 Add `App\Services\ReleaseOffDayService` with `add()`, `markWeekends()`, and `remove()`
- [x] 2.4 Add `App\Services\ReleaseDocumentService` with `store()` and `delete()`, owning the disk writes
- [x] 2.5 Rewire the web and API release, off-day, and document controllers to all four services
- [x] 2.6 Run the full suite

## 3. Tasks & board

- [x] 3.1 Add `App\Services\TaskService` with `filtered()`, `createForRelease()`, `createSubtask()` (one-level guard), `update()`, and `changeStatus()`
- [x] 3.2 Add `App\Services\BoardService` with `columns()`, `quickAdd()`, and `move()` (status + reorder in one transaction)
- [x] 3.3 Rewire the web and API task and board controllers
- [x] 3.4 Run the full suite

## 4. Tasksheet

- [x] 4.1 Add `App\Services\TasksheetService` with `teamsFor()`, `rowUsersFor()`, `entriesFor()`, `trend()`, `resolveEntry()`, `save()`, and `history()`
- [x] 4.2 Use `whereDate` bounds in `trend()`, fixing the dropped-final-day bug on the web side as a consequence
- [x] 4.3 Keep the lead-only `feedback` write rule in the service, driven by the actor passed in
- [x] 4.4 Rewire the web and API tasksheet controllers
- [x] 4.5 Add a regression test asserting the trend includes the viewed day
- [x] 4.6 Run the full suite

## 5. Performance

- [x] 5.1 Add `App\Services\PerformanceEvaluationService` with `grid()`, `rowUsersFor()`, `leaveFlags()`, and `upsertScores()`
- [x] 5.2 Use `whereDate` bounds in `leaveFlags()`
- [x] 5.3 Add `App\Services\PerformanceCompetencyService` with `uniqueKey()`, `create()`, `toggle()`, and `isDeletable()`
- [x] 5.4 Rewire the web and API performance score and competency controllers
- [x] 5.5 Run the full suite

## 6. Activity insights

- [x] 6.1 Add `App\Services\ActivityInsights` with `feed()`, `totals()`, `trend()`, `topContributors()`, and `bySubjectType()`
- [x] 6.2 Rewire the web and API activity controllers
- [x] 6.3 Run the full suite

## 7. Collaboration

- [x] 7.1 Add `App\Services\EventService` with `attributes()` (all-day normalization), `monthWindow()`, and `save()`
- [x] 7.2 Rewire the web `EventController`, web `CalendarController`, and `Api\V1\EventController`
- [x] 7.3 Add `App\Services\NoteService` with `visibleTo()` and `save()` (recipient sync for specific-visibility notes only)
- [x] 7.4 Add `App\Services\MeetingNoteService` with `visibleTo()` and `save()` (attendee sync)
- [x] 7.5 Add `App\Services\QuickLinkService` with `visibleTo()` and `partitionedFor()`
- [x] 7.6 Rewire the note, meeting-note, and quick-link controllers on both sides, and the quick-links view composer in `AppServiceProvider`
- [x] 7.7 Run the full suite

## 8. Workspace

- [x] 8.1 Add `App\Services\TeamService` with `addMember()`, `removeMember()` (soft leave), `updateLead()`, `assignableUsers()`, and `isDeletable()`
- [x] 8.2 Add `App\Services\ProjectService` with `isDeletable()`, `archive()`, `restore()`, and the web-only detail `analytics()`
- [x] 8.3 Add `App\Services\UserService` with `directory()`, `stats()`, `isSelf()`, `isLastActiveAdmin()`, `save()`, `toggleActive()`, and `softDelete()` (ends memberships, revokes tokens)
- [x] 8.4 Rewire the web and API project, team, team-member, and user controllers
- [x] 8.5 Run the full suite

## 9. Tests for the extracted logic

- [x] 9.1 Unit tests for `DashboardService` geometry, grouping, and analytics roll-ups
- [x] 9.2 Unit tests for `TasksheetService::trend()` and `rowUsersFor()` membership-covered-the-date rule
- [x] 9.3 Unit tests for `PerformanceEvaluationService::leaveFlags()` and period normalization
- [x] 9.4 Unit tests for `ActivityInsights` roll-ups and gap-filled trend
- [x] 9.5 Unit tests for `TaskService` subtask nesting guard and `BoardService::move()` reordering

## 10. Verification

- [x] 10.1 Confirm no controller still contains a domain query, date calculation, or multi-step write
- [x] 10.2 Full suite green with no pre-existing assertion modified
- [x] 10.3 Pint clean on every touched file
- [x] 10.4 Live smoke test: same records through a web page and its API counterpart produce the same numbers
- [x] 10.5 Update `README.md` architecture notes and `docs/api-v1.md` code-layout section
