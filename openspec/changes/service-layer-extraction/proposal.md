## Why

The `desktop-rest-api-v1` change added a second delivery mechanism over the same domain, and it
did so by copying logic. `Api\V1\DashboardController` re-implements the web dashboard's timeline
geometry, conflict flags, and analytics almost line for line — roughly 350 lines mirrored on each
side. `Api\V1\ReleaseController` carries its own `syncPhases`, `syncOffDays`, and overlap-warning
builder. The tasksheet's "which members belong on this day's sheet" rule, the performance
evaluation grid, the board's column grouping, the activity roll-ups, the all-day event
normalization, the last-active-admin guards — all of it now exists twice.

That is a latent correctness problem, not a tidiness one. The bug already found in this codebase
proves it: `whereBetween` against a `date`-cast column silently drops the final day. It was fixed
on the API side and is **still live on the web side**, because the two copies have no reason to
change together. Every rule duplicated this way is one more place where the desktop client and the
browser can quietly start disagreeing about the same data.

The domain already has the right pattern — `OverlapChecker` is the single definition of "two
same-team windows overlap", reused by the save-time warning and the dashboard highlight, and
`PerformanceAnalytics`, `Timeline`, and `PerformancePeriod` follow suit. This change extends that
pattern to the rest of the domain so both controllers become thin assemblers over one shared
implementation.

## What Changes

- **New service layer under `app/Services/`** holding every query and rule currently duplicated
  between a web controller and its API counterpart. Controllers keep only: resolve input →
  delegate → present.
- **Both controllers call the same service.** Neither owns a query, a date calculation, a
  permission-adjacent guard, or a write sequence any more.
- **Presentation stays in the delivery layer.** A service returns domain values — Eloquent models,
  Carbon dates, canonical arrays. The web controller hands them to Blade; the API controller maps
  them through Resources. Field naming per format is the one thing that legitimately differs.
- **The `date`-cast comparison bug is fixed once**, in the service, which fixes the web side as a
  consequence rather than as a separate edit.
- **The quick-links view composer** in `AppServiceProvider` stops duplicating the visible-links
  query and partition, and uses the service too.
- **No behaviour changes.** No route, request shape, JSON payload, Blade view, permission, or
  validation rule changes. The 299-test suite is the contract: it must stay green throughout,
  which is what makes this a refactor rather than a rewrite.

## Capabilities

### New Capabilities

- `domain-services`: The requirement that domain logic and queries live in a single shared
  implementation rather than per delivery mechanism — what belongs in a service, what must stay in
  a controller, what a service may and may not return, and the rule that a behaviour reachable
  from both the web app and the API resolves to one code path.

### Modified Capabilities

<!-- None. This change is behaviour-preserving: every existing requirement across release
     planning, collaboration, tasksheet, performance, and the API capabilities keeps its current
     wording and its current outcome. Only the location of the implementation moves. -->

## Impact

- **New**: `app/Services/` gains roughly a dozen classes covering the dashboard, release writes,
  off-days, documents, tasks, the board, the tasksheet, performance evaluation and the competency
  catalog, activity insights, calendar events, notes, meeting notes, quick links, teams, projects,
  and users.
- **Rewritten (thinner)**: every controller in `app/Http/Controllers/` and
  `app/Http/Controllers/Api/V1/` that currently holds a private query or calculation helper.
- **Touched**: `app/Providers/AppServiceProvider.php` (the quick-links composer).
- **Unchanged**: all models, policies, gates, middleware, form requests, API resources, routes,
  migrations, seeders, and Blade views. `OverlapChecker`, `PerformanceAnalytics`, `Timeline`,
  `PerformancePeriod`, and `HtmlSanitizer` stay as they are — the new services consume them.
- **Risk**: this edits working code with no user-visible payoff, so it is only safe under a green
  suite. Mitigated by refactoring one service at a time and running the full suite after each,
  plus new unit tests for the extracted calculations that were previously unreachable without a
  full HTTP request.
