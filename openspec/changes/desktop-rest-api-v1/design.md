## Context

The application is a Laravel 13 + Blade release-planning tool. All behaviour today is reached through session-authenticated web routes in `routes/web.php`, rendered by `App\Http\Controllers\*` into Blade views. The domain rules live where they should: capability methods on `App\Models\User` (`isLead()`, `canManageReleases()`, `hasLimitedAccess()`, `canAccessTeamPerformance()`, …), seven policies, one gate (`manage-competencies`), five role middleware, form requests holding all validation, and services (`OverlapChecker`, `PerformanceAnalytics`) plus pure support classes (`Timeline`, `PerformancePeriod`, `HtmlSanitizer`) holding the calculations.

That separation is what makes this change tractable: a REST API can be a second delivery mechanism over the same rules rather than a parallel implementation. The one thing genuinely missing is a stateless authentication mechanism — the app has only the session guard.

Constraints:

- The Blade app must keep working byte-for-byte. This is additive.
- Seven roles with real, tested access differences must be honoured exactly. Divergence between web and API permissions would be a security bug, not a cosmetic one.
- Several payloads carry data that is legitimately invisible to some roles (tasksheet `feedback`, performance scores and notes, private/attendees-only notes). Serialization has to be role-aware, not just the routing.
- SQLite in development, MySQL in production — nothing may depend on a database-specific feature.

## Goals / Non-Goals

**Goals:**

- A complete `/api/v1` covering every feature of the web app, usable by a cross-platform desktop client for all seven roles.
- Stateless bearer-token auth with multi-device support and revocation.
- One source of truth for authorization and validation, shared with the web app.
- A payload contract stable enough that a desktop client can be written against it: consistent envelopes, consistent errors, published enumerations.
- Role-aware serialization so no restricted field ever reaches a client that must not see it.

**Non-Goals:**

- The desktop client itself. This change ships only the server API.
- OAuth, SSO, refresh-token rotation, or third-party API consumers. Tokens are long-lived personal access tokens for a first-party client.
- Realtime push (websockets/broadcasting). The client polls. A future change can add broadcasting.
- Offline sync, conflict resolution, or delta/change-feed endpoints.
- A `/api/v2`, or deprecating anything in the web app.
- Rewriting the web controllers to consume the API. The two front doors coexist.

## Decisions

### Sanctum personal access tokens over a hand-rolled token table

Sanctum is Laravel's first-party answer to exactly this problem, and its `HasApiTokens` trait plus `auth:sanctum` guard give hashed-at-rest tokens, `last_used_at` tracking, and per-token abilities for one migration and one trait.

*Alternatives considered.* A custom `api_tokens` table with a custom guard — no new dependency, but it means re-implementing hashing, lookup, expiry, and revocation, all of which are security-sensitive and already solved. Passport/OAuth2 — vastly more machinery than a first-party desktop client needs, and it introduces a client-credentials model that does not match "a person logs into their desktop app". JWT — stateless but unrevokable without a denylist, which defeats the requirement that deactivating an account cuts access immediately.

Sanctum's SPA cookie mode is deliberately **not** used: a desktop app is not a same-origin browser SPA, and cookie mode would drag CSRF and stateful domains back in. Only the token guard is used.

### Role-aware serialization inside API Resources

Restricted fields are omitted at the resource layer using `when()` against the authenticated user, e.g. tasksheet `feedback` is emitted only for `$request->user()->isLead()`. The alternative — separate "lead" and "member" resource classes per model — doubles the class count and makes it easy to forget one. Keeping the condition beside the field it guards means the rule is visible at the point of leak.

Collections are additionally scoped in the query (`Note::visibleTo()`, `MeetingNote::visibleTo()`, `QuickLink::visibleTo()`), so a restricted record is never loaded, let alone serialized. Serialization guards are the second layer, not the only one.

### Reuse existing FormRequests; add API-only requests under `Api\V1`

`App\Http\Requests\*` are pure validation and authorization — they touch `$this->user()` and `$this->route(...)`, both of which work identically under the `sanctum` guard with the same route-model-binding parameter names. Reusing them keeps one definition of every domain rule (phase windows inside the release window, assignee must be on the team, off-day uniqueness, competency cadence matching, the "specific people" note recipients).

Duplicating them under `Api\V1` was considered and rejected: two copies of `ReleaseRequest::withValidator()` would drift, and the drift would be silent. New request classes are added only for input shapes that exist solely in the API — `LoginRequest`, `ChangePasswordRequest`, `UpdateProfileRequest`, `MoveTaskRequest`, `CompleteReleaseRequest`, `RevokeTokenRequest`.

The one adaptation needed: requests using `prepareForValidation()` to coerce HTML checkbox values (`$this->boolean('all_day')`, `$this->boolean('active')`) must keep working when a JSON client sends a real boolean. `Request::boolean()` already handles `true`, `"true"`, `1`, and `"1"` identically, so no change is required — but only where the key is present. Where absence must mean "unchanged" rather than "false", the API controller passes the field explicitly.

### Route files split by version and by domain

`routes/api.php` does nothing but load `routes/api/v1.php`, which applies the `/v1` prefix, the `api-v1.` name prefix, and the shared middleware stack, then loads one file per domain: `auth.php`, `workspace.php`, `releases.php`, `collaboration.php`, `tasksheet.php`, `performance.php`, `insights.php`. A v2 later adds `routes/api/v2.php` beside it and touches nothing existing.

The ordering hazard from `routes/web.php` — literal segments like `create`/`edit` colliding with `{model}` bindings — largely disappears, because a REST API has no `create`/`edit` form routes. Where a literal collides with a binding (`performance/competencies` vs a `{user}` route), the literal is registered first, as the web routes already do.

### Middleware: reuse the role middleware, add a JSON active-account check

The five existing role middleware (`lead`, `manage-users`, `manage-releases`, `full-access`, `admin`) call `abort(403, ...)`, which the exception handler already renders as JSON for `api/*`. They are reused as-is on API route groups.

`EnsureUserIsActive` cannot be reused: it calls `Auth::guard('web')->logout()` and `$request->session()->invalidate()`, and API requests have no session. A sibling `EnsureApiUserIsActive` deletes the presented access token and aborts 401. Deleting the token — rather than only refusing the request — means a deactivated user's client is logged out rather than looping on failures.

### Response envelope and a thin base controller

Laravel's `JsonResource` already wraps in `data` and attaches `meta`/`links` for paginators, so the envelope is free. An `ApiController` base class adds only what resources do not cover: `ok($data, $message)`, `created($resource)`, `message($text)`, and `paginate($query, $resource)` which reads `per_page`, caps it, and applies the resource collection. Keeping it thin avoids inventing a bespoke envelope that no Laravel client library expects.

The overlap warning needs a home that is neither an error nor a resource field: it is attached with `additional(['warning' => ...])`, which places it as a sibling of `data`, and is absent when there is no conflict.

### Timezone and date shape

Date-only columns (`start_date`, `due_date`, `date`, `period_start`) serialize as `Y-m-d`; timestamps serialize as ISO-8601. A desktop client spanning timezones must not receive `2026-07-10T00:00:00Z` for a date the user typed as July 10 and see July 9 in a western timezone. This is enforced per-field in the resources rather than globally, since both shapes are needed.

### Throttling

Login is throttled per `email|ip` using a named rate limiter, mirroring the web `LoginRequest`. General API traffic is throttled per token id (falling back to IP for unauthenticated calls). Both are registered as named limiters so the limits are configuration, not scattered literals.

### The dashboard endpoint returns computed data, not view state

`DashboardController` currently computes timeline geometry (offset/width percentages via `Timeline`), grouped bars, conflict flags, and analytics, then hands them to Blade. The API returns the same computed structures rather than raw releases, because re-deriving percentage geometry and cross-team conflict flags client-side would duplicate `Timeline` and `OverlapChecker` in the desktop app — the exact duplication this design is trying to avoid. The client draws; the server decides what the bars mean.

## Risks / Trade-offs

- **Web and API permission drift.** → Every API route group is gated by the *same* middleware/policy objects the web routes use; no permission literal is written in an API controller. Feature tests assert the refusal case per role, so a divergence fails the suite rather than shipping.
- **A restricted field leaks through a new resource.** → Restricted fields are enumerated in the `api-foundation` spec and guarded in the resource beside the field. Tests assert absence for a non-lead, not just presence for a lead — absence is the assertion that catches the regression.
- **Reused FormRequests were written for HTML form input.** Array-shaped inputs (`phases.development.start`, `off_days.*.date`, `scores.*`) arrive as nested JSON rather than bracketed form keys; Laravel's validator treats both identically, so this is safe. The real risk is checkbox-style booleans where absence meant false. → API controllers pass such fields explicitly; the affected requests (`EventRequest`, `PerformanceCompetencyRequest`) are covered by tests that post JSON booleans.
- **Sanctum tokens do not expire by default.** A stolen laptop keeps access. → Token expiry is configurable in `config/sanctum.php`; revocation endpoints let a user cut a lost device, and deactivating the account cuts every token on the next request. Expiry is left to deployment policy rather than hard-coded.
- **`per_page` abuse or unbounded includes.** → `per_page` is capped server-side; relations are eager-loaded in fixed sets per endpoint rather than driven by a client-supplied `include` parameter, so a client cannot ask the server to load the object graph.
- **Adding a dependency to a zero-dependency-drift project.** `laravel/sanctum` is first-party and maintained on the framework's release cadence, which is the lowest-risk dependency available; the alternative was hand-rolling authentication.
- **N+1 queries under a chattier client.** A desktop app fetches more, and more often, than a page render. → Every index endpoint eager-loads its resource's relations explicitly and uses `withCount` for the counts resources expose.
- **API surface is large, so test coverage is the real deliverable.** → Tests are organised per capability spec rather than per controller, so each spec scenario maps to an assertion.

## Migration Plan

1. `composer require laravel/sanctum` and run the `personal_access_tokens` migration. Additive; no existing table changes.
2. Add `HasApiTokens` to `App\Models\User` — a trait addition with no behavioural effect on the web app.
3. Register API routing and middleware in `bootstrap/app.php`. The `shouldRenderJsonWhen(... $request->is('api/*'))` hook is already present, so JSON error rendering needs no change.
4. Ship controllers, resources, and routes. Nothing existing is edited beyond the two files above.
5. Verify with `php artisan route:list --path=api`, Pint, and the feature suite — including the pre-existing web tests, which must stay green as the proof that this was additive.

**Rollback:** remove the `api:` entry from `bootstrap/app.php`. The API disappears; the web app is untouched. The `personal_access_tokens` table can be left in place harmlessly, or rolled back with its migration.

## Open Questions

- **Token lifetime.** Left unset (non-expiring) for now, with revocation as the control. If the deployment needs it, `config/sanctum.php` `expiration` is the single switch — but that choice belongs to whoever operates the install.
- **Realtime.** The client polls in v1. If polling proves too chatty in practice, broadcasting is the follow-up change, not a v1 addition.
- **Attachment size over the API.** The 20 MB document limit is inherited from the web app; whether a desktop client needs chunked/resumable upload for large files is deferred until there is evidence it does.
