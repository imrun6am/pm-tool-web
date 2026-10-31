## Context

Before the API existed, each controller owning its own private helpers was fine — there was one
caller. Adding `Api\V1\*` made every one of those helpers a second copy. The concrete inventory of
what is currently duplicated:

| Behaviour | Web | API | Shape of the duplication |
| --- | --- | --- | --- |
| Timeline geometry, conflict flags, analytics, member dashboard | `DashboardController` | `Api\V1\DashboardController` | ~350 lines, near-identical |
| Phase + off-day + member reconciliation, overlap warning, index filters | `ReleaseController` | `Api\V1\ReleaseController` | `syncPhases`, `syncOffDays`, `overlapWarning`, filter chain |
| Weekend marking | `ReleaseOffDayController` | `Api\V1\ReleaseOffDayController` | identical cursor loop |
| Upload/delete file handling | `ReleaseDocumentController` | `Api\V1\ReleaseDocumentController` | identical store + `Storage::delete` |
| Task attribute assembly, subtask nesting guard | `TaskController` | `Api\V1\TaskController` | identical `attributes()` |
| Board column grouping, move + reorder transaction | `BoardController` | `Api\V1\BoardController` | identical |
| Which members belong on a day's sheet, output trend, row upsert | `TasksheetController` | `Api\V1\TasksheetController` | ~80 lines, and **already divergent** |
| Evaluation grid rows, leave flags, ratings upsert | `PerformanceScoreController` | `Api\V1\PerformanceScoreController` | ~80 lines, and **already divergent** |
| Competency key generation, delete guard | `PerformanceCompetencyController` | `Api\V1\PerformanceCompetencyController` | identical `uniqueKey()` |
| Feed filters, roll-ups, trend, contributors, by-type | `ActivityController` | `Api\V1\ActivityController` | ~70 lines |
| All-day normalization, month grid window | `EventController`, `CalendarController` | `Api\V1\EventController` | identical `attributes()` |
| Recipient sync, date filters | `NoteController` | `Api\V1\NoteController` | identical |
| Visibility + release/general/date filters | `MeetingNoteController` | `Api\V1\MeetingNoteController` | identical |
| Visible links + own/shared partition | `AppServiceProvider` composer | `Api\V1\QuickLinkController` | identical |
| Member add/remove, lead assignment, delete guard | `TeamController` | `Api\V1\TeamController`, `TeamMemberController` | identical |
| Release-exists delete guard, archive/restore | `ProjectController` | `Api\V1\ProjectController` | identical |
| Directory filters, stats, self/last-admin guards | `UserController` | `Api\V1\UserController` | identical |

Two rows are marked **already divergent** — the `whereBetween` versus `whereDate` fix went into the
API copies only. That divergence appeared within a single change. It is the argument for this one.

The codebase already demonstrates the target pattern. `OverlapChecker` is described in its own
docblock as "the single definition of 'two releases owned by the same team overlap in time' … so
the rule lives in exactly one place", and `Timeline` and `PerformancePeriod` are deliberately pure
and independently unit-tested. This change finishes what those three started.

## Goals / Non-Goals

**Goals:**

- One implementation per domain behaviour, called by both delivery layers.
- Controllers reduced to input resolution, delegation, and presentation.
- The extracted calculations become directly unit-testable, which most of them are not today.
- Fix the `date`-cast comparison bug once, in the shared path, so the web side is corrected as a
  consequence.
- Zero behaviour change, proven by the existing suite passing with unaltered assertions.

**Non-Goals:**

- No repository pattern, no interfaces-for-their-own-sake, no DTO layer. Services take and return
  Eloquent models; this app has one database and one ORM, and abstracting Eloquent behind a
  repository would add indirection with no beneficiary.
- No new endpoints, no payload changes, no Blade changes.
- Not extracting logic that genuinely has one caller. `ProjectController::analytics()` is web-only;
  it moves only because its sibling guards move with it, not on a duplication argument.
- No CQRS split, no event sourcing, no queue work.
- Not touching `OverlapChecker`, `PerformanceAnalytics`, `Timeline`, `PerformancePeriod`, or
  `HtmlSanitizer` — they are already correct and already shared.

## Decisions

### One service per aggregate, not one per controller

Services are named after the thing they operate on (`ReleaseService`, `TasksheetService`), not after
the controller they were extracted from. Where a controller pair spans two aggregates the service
follows the aggregate: `TeamMemberController` and web `TeamController::addMember` both collapse into
`TeamService`, because membership is a team concern regardless of which controller exposed it.

*Alternative considered.* One service per controller — mechanical, but it would have produced both a
`TeamController` service and a `TeamMemberController` service holding two halves of the same rule,
recreating the split this change exists to remove.

### Services return domain values; controllers own presentation

A service returns models, Carbon instances, collections, and plain arrays. It never returns a
response, a redirect, a resource, or a formatted string. This is what lets one computation serve two
formats: the web controller passes the result to Blade, the API controller maps it through a
Resource.

The consequence worth stating: **field naming stays in the delivery layer**. The web dashboard's
Blade consumes `$analytics['statusLabels']` and `$analytics['monthlyMax']`; the published API
contract uses `status_counts` and `monthly_max` and is covered by tests and `docs/api-v1.md`. Both
are fixed contracts that cannot move. So the service returns one canonical structure and the API
controller carries an explicit private presenter that renames into its JSON shape. That is not
duplicated logic — it is two presentations of one computation, which is the correct place for the
difference to live.

The canonical structure uses the keys the Blade views already consume, so the web side passes
through unchanged. That choice is deliberately asymmetric: changing 300+ lines of working Blade to
satisfy a naming preference would risk the one thing this change must not do.

### Geometry returns models, not identifiers

`DashboardService::groups()` returns each bar as `['release' => Release, 'offset' => float, 'width'
=> float, 'conflict' => bool, 'phases' => [...]]` — the model itself, with Carbon phase bounds. Blade
reads `$bar['release']->name` directly; the API maps the model through `ReleaseSummaryResource` and
the Carbon bounds through `toDateString()`. Returning ids instead would force each caller to re-query.

### Authorization stays out of the services

Middleware, policies, and gates keep deciding *whether* the actor may act; services decide *what
happens* and *which records are visible*. So `TasksheetService::upsert()` receives the resolved
entry for the controller to authorize before the write, rather than authorizing internally — and
`NoteService::visibleTo($user)` does scope its query by viewer, because that shapes a result set
rather than granting an action.

This keeps the security model exactly where the existing tests assert it, and avoids the failure
mode where a service is called from a new place and silently skips a check the old controller made.

### Constructor injection, resolved by the container

Services are plain classes with constructor-injected dependencies (`ReleaseService` takes
`OverlapChecker`; `DashboardService` takes `OverlapChecker` and `TaskMetricsService`). Laravel's
container autowires them into controller constructors or method signatures. No service provider
bindings, no facades, no singletons — nothing here holds state between calls.

### One service, both callers, in the same commit

Each extraction moves the logic and rewires *both* controllers together, then runs the full suite.
Extracting into a service while leaving one caller on its own copy would leave the codebase in the
exact state this change is removing.

### The date-cast bug is fixed in the shared path

`TasksheetService::trend()` and `PerformanceEvaluationService::leaveFlags()` use `whereDate` bounds.
Because the web controllers now call those methods, the web side is fixed by construction. This is
the only intended behaviour change in the whole refactor, it is a bug fix, and it gets its own
regression test asserting the final day is included.

## Risks / Trade-offs

- **Editing working code with no user-visible payoff.** → Only safe under a green suite: one service
  at a time, full suite after each, and the pre-existing assertions are never edited to accommodate
  a refactor. An assertion that needs changing means the behaviour changed, which means stop.
- **The `date`-cast fix changes web output.** A chart that was silently missing its last day starts
  including it. → Intended, it is the bug fix; covered by a regression test rather than left implicit.
- **Blade breakage from a renamed array key.** The views consume nested array keys that no type
  checker guards. → The canonical structure keeps the keys Blade already uses, so the web payload is
  byte-identical; the existing feature tests render the real views and would fail on a missing key.
- **Service sprawl / anaemic wrappers.** A service that only forwards one Eloquent call earns
  nothing. → The bar for extraction is "duplicated, or a multi-step sequence". `ProjectService` is
  the thinnest and is justified only by the guard message living once.
- **More indirection to read.** A reader now hops controller → service. → Accepted: the hop replaces
  having to diff two controllers to discover whether they still agree.
- **Over-abstraction pressure.** The obvious next step is interfaces and repositories. → Explicitly a
  non-goal. Concrete classes, injected directly.

## Migration Plan

Incremental and reversible at every step; each numbered group in `tasks.md` is one safe stopping
point.

1. Highest-duplication first — `DashboardService`, then the release services. These carry the most
   risk and the most benefit, so they get attention while it is freshest.
2. Then tasks/board, tasksheet, performance, activity.
3. Then the smaller collaboration and workspace services.
4. Unit tests for the extracted calculations, which are reachable directly for the first time.
5. Full suite plus a live smoke test comparing a web page and its API counterpart for the same
   records, confirming the shared path produces the same numbers on both.

**Rollback:** each group is independent — revert the commit for that service and its two callers
return to their previous state. Nothing outside `app/Services/` and the controllers changes, so
there is no migration, no config, and no data to undo.

## Open Questions

- **Where release *reads* belong.** `ReleaseService` owns the index filter chain and the write
  sequence. Whether the eager-load sets for the detail views also belong there is unresolved — they
  currently differ between web (needs comment authors for Blade) and API (needs counts for the
  resource), and forcing them together would make one caller load what it does not use. Left as-is
  for now; if they converge, they move.
- **`ProjectController::analytics()`.** Web-only today. Moved to `ProjectService` for consistency
  with its siblings, but if the API never exposes project analytics it is a service with one caller.
  Acceptable; worth revisiting if it stays that way.
