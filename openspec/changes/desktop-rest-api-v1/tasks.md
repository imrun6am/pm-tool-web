## 1. Foundation & authentication plumbing

- [x] 1.1 Add `laravel/sanctum` and publish its `personal_access_tokens` migration; run it
- [x] 1.2 Add `Laravel\Sanctum\HasApiTokens` to `App\Models\User`
- [x] 1.3 Register the `api:` routing entry in `bootstrap/app.php` pointing at `routes/api.php`
- [x] 1.4 Add `App\Http\Middleware\EnsureApiUserIsActive` — deletes the presented token and aborts 401 for a deactivated or deleted account
- [x] 1.5 Alias `active-api` in `bootstrap/app.php` and confirm the existing role aliases (`lead`, `manage-users`, `manage-releases`, `full-access`, `admin`) resolve for API groups
- [x] 1.6 Register named rate limiters: `api` (per token id, IP fallback) and `login` (per email + IP)
- [x] 1.7 Add `App\Http\Controllers\Api\V1\ApiController` base class with `ok()`, `created()`, `message()`, and a `per_page`-capping `paginate()` helper

## 2. API resources (`App\Http\Resources\V1`)

- [x] 2.1 `UserResource` + `UserSummaryResource` — role label, active/deleted status tag, permission flags on the full resource, never any credential field
- [x] 2.2 `ProjectResource` and `TeamResource` (with lead, members, archived state, release counts)
- [x] 2.3 `ReleaseResource` + `ReleaseSummaryResource` + `ReleasePhaseResource` — window, quarter label, duration/off-day/working-day counts, completion, member list, sub-resource counts
- [x] 2.4 `ReleaseDocumentResource` (human size, uploader, no storage path) and `ReleaseOffDayResource`
- [x] 2.5 `TaskResource` + `TaskSummaryResource` — status label and color, subtask progress, comment count, phase label
- [x] 2.6 `CommentResource`, `EventResource` (type color, covered dates, attendees), `ActivityResource` (old → new change diff)
- [x] 2.7 `NoteResource`, `MeetingNoteResource`, `QuickLinkResource` — visibility labels, recipients/attendees
- [x] 2.8 `TasksheetEntryResource` — fill state, leave labels, and `feedback` emitted **only** when the viewer is a lead
- [x] 2.9 `PerformanceCompetencyResource` and `PerformanceScoreResource` — category/scope/cadence labels, score anchor label, evaluator
- [x] 2.10 Verify every date-only field serializes `Y-m-d` and every timestamp ISO-8601

## 3. API-only form requests (`App\Http\Requests\Api\V1`)

- [x] 3.1 `LoginRequest` (email, password, device_name) with the throttle key and a credentials error that does not reveal whether the email exists
- [x] 3.2 `UpdateProfileRequest` and `ChangePasswordRequest` (current password required, application password rules)
- [x] 3.3 `MoveTaskRequest` (status + ordered ids) and `CompleteReleaseRequest` (completion notes)
- [x] 3.4 Confirm the reused web `FormRequest` classes validate identically under JSON input, especially the boolean coercion in `EventRequest` and `PerformanceCompetencyRequest`

## 4. Authentication, identity & metadata endpoints

- [x] 4.1 `AuthController` — `login` (issues a device-named token, refuses deactivated/deleted accounts), `logout`, `logoutAll`
- [x] 4.2 `AuthController` token/device listing and single-device revocation, scoped so a user only ever sees or revokes their own
- [x] 4.3 `ProfileController` — current user with effective permission flags, teams, and led teams; profile update; password change that revokes other tokens
- [x] 4.4 `MetaController` — every domain enumeration (roles, task statuses + colors, release phases + colors, event types + colors, note/meeting-note/quick-link visibilities, leave types, competency categories/scopes/cadences, the 1–5 scale)

## 5. Workspace endpoints

- [x] 5.1 `ProjectController` — index (filterable, paginated), show, store, update, destroy, archive, restore
- [x] 5.2 `TeamController` — index, show, store, update, destroy, archive, restore, and lead assignment
- [x] 5.3 `TeamMemberController` — list, add, remove, preserving departure history
- [x] 5.4 `UserController` — index (filterable by role/active), show, store, update, toggle active, destroy

## 6. Release planning endpoints

- [x] 6.1 `ReleaseController` — index with status/project/team/year filters, show, store, update, destroy
- [x] 6.2 Return the overlap warning as a sibling of `data` on store and update, and never block the save
- [x] 6.3 `ReleaseController` complete + reopen, and a conflicts endpoint for a single release
- [x] 6.4 `ReleasePhaseController` — the four ordered phases, unpaginated
- [x] 6.5 `ReleaseOffDayController` — index, store, mark weekends, destroy
- [x] 6.6 `ReleaseDocumentController` — index, upload, authenticated download, destroy

## 7. Collaboration endpoints

- [x] 7.1 `TaskController` — index, show, store under a release, store subtask (one level only), update, update status, destroy
- [x] 7.2 `BoardController` — columns for every status with release/assignee filters, and the combined move + reorder endpoint
- [x] 7.3 `CommentController` — list and create for releases and tasks, update, destroy
- [x] 7.4 `EventController` — index by month or date range, show, store, update, destroy
- [x] 7.5 `NoteController` — visibility-scoped index, store, update, destroy, with recipients for "specific" notes
- [x] 7.6 `MeetingNoteController` — visibility-scoped index (release / general filters), show, store, update, destroy
- [x] 7.7 `QuickLinkController` — scoped index partitioned into own and shared, store, update, destroy

## 8. Tasksheet, performance & insights endpoints

- [x] 8.1 `TasksheetController` — day grid for a team (rows for members whose membership covered the date), upsert of one row, and per-member history
- [x] 8.2 Enforce lead-only `feedback` on write and omit it from non-lead payloads
- [x] 8.3 `PerformanceController` — team overview and member scorecard from `PerformanceAnalytics`
- [x] 8.4 `PerformanceScoreController` — evaluation grid for a team/cadence/period and the ratings upsert
- [x] 8.5 `PerformanceCompetencyController` — catalog index, store, update, toggle, destroy, behind the `manage-competencies` gate
- [x] 8.6 `ActivityController` — paginated feed with release/subject/causer/date filters, full-access roles only
- [x] 8.7 `DashboardController` — planning timeline with geometry, analytics and conflict flags for full-access roles; personal member dashboard for limited roles

## 9. Route wiring

- [x] 9.1 `routes/api.php` that loads `routes/api/v1.php` only
- [x] 9.2 `routes/api/v1.php` applying the `/v1` prefix, `api-v1.` name prefix, and shared middleware, then loading the per-domain files
- [x] 9.3 Split the domain route files: `auth.php`, `workspace.php`, `releases.php`, `collaboration.php`, `tasksheet.php`, `performance.php`, `insights.php`
- [x] 9.4 Apply the correct role middleware to each group so API access mirrors `routes/web.php`, registering literal segments before `{model}` bindings

## 10. Verification

- [x] 10.1 `php artisan route:list --path=api` — every endpoint registered under `/api/v1` with its intended middleware
- [x] 10.2 Feature tests for authentication: login, bad credentials, deactivated account, token revocation, password change revoking other devices
- [x] 10.3 Feature tests for role parity: for each of the seven roles, assert the endpoints it may and may not reach
- [x] 10.4 Feature tests for restricted-field omission: tasksheet feedback and performance data absent for non-leads
- [x] 10.5 Feature tests for the domain rules the API must preserve: overlap warns without blocking, phase inside window, assignee on team, one-level subtasks, note visibility scoping
- [x] 10.6 Run Pint and the full existing suite; the pre-existing web tests must stay green as proof the change was additive
- [x] 10.7 Document the API in `README.md` and ship an endpoint reference for desktop client authors
