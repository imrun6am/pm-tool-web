## Why

Every capability this tool has — the timeline, releases, tasks, the board, the calendar, notes, meeting minutes, the tasksheet, performance — exists only as server-rendered Blade behind a session cookie. There is no machine-readable way in or out, so a desktop client (or any non-browser consumer) cannot be built at all: it would have to scrape HTML and forge CSRF tokens. This change opens the whole system through a versioned, token-authenticated REST API so a cross-platform desktop application can serve **all seven roles** — admin, CTO, tech lead, team lead, developer, QA, and viewer — with exactly the access each one has on the web.

The API is additive. The existing Blade app keeps working unchanged; the API is a second front door onto the same domain rules, not a fork of them.

## What Changes

- **New `/api/v1` surface**, versioned in the URL, covering every feature of the web app: authentication and profile, workspace (projects, teams, users), release planning (releases, phases, off-days, documents, completion), collaboration (tasks, subtasks, board, comments, calendar events, daily notes, meeting notes, quick links), the team tasksheet, performance evaluation and analytics, the activity feed, and the dashboard.
- **Token authentication for desktop clients** via Laravel Sanctum personal access tokens. A client exchanges email + password (+ a device name) for a bearer token; it can list its own active devices and revoke one or all of them. Sessions and CSRF are irrelevant to the API — every API request is stateless.
- **Role parity, enforced server-side.** The API reuses the same policies, gates, capability methods (`isLead()`, `canManageReleases()`, `hasLimitedAccess()`, …) and role middleware as the web app. A developer hitting `GET /api/v1/projects` is refused exactly as they are on the web. **Nothing is enforced only by hiding it from the client.**
- **Deactivated and deleted accounts are locked out of the API**, and a deactivation takes effect on the account's next API request — the token stops working immediately, mirroring the mid-session sign-out the web app performs.
- **A capability/permissions endpoint** (`GET /api/v1/me`) and a **metadata endpoint** (`GET /api/v1/meta`) that publish the caller's effective permissions plus every enum the domain uses (roles, task statuses, release phases and their colors, event types, note and meeting-note visibilities, leave types, competency categories/scopes/cadences, the 1–5 score scale). The desktop client renders its navigation, menus, and pickers from these instead of hard-coding — so a role or status added later reaches the client without a client release.
- **Consistent JSON contract**: `data`-wrapped resources, `meta`/`links` on paginated collections, RFC-style errors (`message` + `errors`) with conventional status codes (401 unauthenticated, 403 forbidden, 404 not found, 422 validation, 429 throttled).
- **Non-blocking warnings are returned, not swallowed.** The release overlap rule stays a warning: a save that double-books a team succeeds and returns the conflicting releases in the response payload, matching the web app's "warn on save, never block" behaviour.
- **Login is rate-limited** per email + IP, and the general API is throttled per token.
- **No breaking changes.** No existing route, controller, view, model, or permission changes behaviour. `routes/web.php` is untouched.

## Capabilities

### New Capabilities

- `api-foundation`: The versioned `/api/v1` contract itself — URL versioning, the JSON envelope for single resources, collections, and pagination, the error format and status-code conventions, filtering/sorting/pagination query conventions, throttling, and the requirement that API responses never leak fields a role may not see.
- `api-authentication`: Token issue/refresh/revoke for desktop clients — login with device name, bearer-token authentication, the current-user + effective-permissions endpoint, device (token) listing and revocation, profile and password self-service, login throttling, and lockout of deactivated/deleted accounts.
- `api-authorization`: Role parity between the API and the web app — the mapping of each of the seven roles to what the API permits, reuse of the existing policies/gates/middleware, per-record visibility scoping (private notes, attendees-only meeting notes, quick links, lead-only tasksheet feedback and performance data), and the rule that every restriction is enforced server-side.
- `api-resources`: The resource-by-resource endpoint contract — the collections, members, and sub-resources exposed for workspace, release planning, collaboration, tasksheet, performance, activity, and dashboard data, including their filters, their nested/summary representations, file upload and download, and the domain warnings (overlap) surfaced in responses.

### Modified Capabilities

<!-- None. Every existing capability keeps its current requirements; the API exposes them over HTTP without changing behaviour. -->

## Impact

- **Dependencies**: adds `laravel/sanctum` (first-party) and its `personal_access_tokens` migration. No other new packages.
- **Routing**: `bootstrap/app.php` gains an `api:` routing entry, API-specific middleware aliases, and JSON-shaped exception rendering for `api/*` (the `shouldRenderJsonWhen` hook is already present). New `routes/api.php` that only loads `routes/api/v1.php`, which is itself split into per-domain route files so each version and domain stays separately readable.
- **Backend**: `App\Http\Controllers\Api\V1\*` controllers; `App\Http\Resources\V1\*` API resources; `App\Http\Requests\Api\V1\*` for API-only inputs (login, password change, board move, bulk position updates, filters). Existing `App\Http\Requests\*` validators are reused as-is so validation rules keep a single source of truth. A small `EnsureApiUserIsActive` middleware (the JSON counterpart of `EnsureUserIsActive`, which redirects). `HasApiTokens` on `App\Models\User`.
- **Unchanged**: all models, policies, gates, services (`OverlapChecker`, `PerformanceAnalytics`), support classes (`Timeline`, `PerformancePeriod`, `HtmlSanitizer`), migrations, seeders, Blade views, and `routes/web.php`.
- **Security**: tokens are hashed at rest by Sanctum; rich-text bodies continue to pass through `HtmlSanitizer` on write; performance scores and tasksheet feedback stay behind the same lead-only gates and are omitted from payloads for other roles; documents are streamed through the authenticated download endpoint rather than exposed as public storage URLs.
- **Docs**: `README.md` gains an API section; the change ships an endpoint reference for desktop client authors.
