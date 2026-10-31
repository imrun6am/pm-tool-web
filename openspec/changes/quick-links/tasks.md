## 1. Database & Model

- [x] 1.1 Create `quick_links` migration: `user_id` FK cascade, `release_id` nullable FK `nullOnDelete`, `label`, `url` (2048), `visibility` default private, timestamps, indexes on `user_id`, `visibility`, `release_id`
- [x] 1.2 Create `QuickLink` model: fillable, `VISIBILITY_*` consts + `VISIBILITIES` map, `author()` + `release()` belongsTo, `isShared()`, `scopeVisibleTo()` (limited viewer → own links only; full-access → own + others' shared) — mirroring `Note`; `Release::quickLinks()` hasMany
- [x] 1.3 Run migration and verify schema

## 2. Authorization, Validation & Routes

- [x] 2.1 Create `QuickLinkPolicy`: author-only update/delete (NotePolicy stance)
- [x] 2.2 Create `QuickLinkRequest`: label required max:100, url required `url:http,https` max:2048, visibility in private|shared (limited users: private only — shared rejected), `release_id` nullable exists:releases,id
- [x] 2.3 Create `QuickLinkController` (store/update/destroy) redirecting `back()` with a `quick-links-open` flash; register three routes in the collaboration section (not behind `full-access`)

## 3. Drawer UI

- [x] 3.1 View composer in `AppServiceProvider` supplying `QuickLink::visibleTo()` data (own first, newest first) to the drawer partial
- [x] 3.2 Create `partials/quick-links-drawer.blade.php`: Alpine slide-over (right), "My links" section with visibility + release badges + inline edit form (incl. optional release select) + delete, "Shared by others" section with author names (full-access viewers only), add form (no visibility selector for limited users), empty states; initializes open on the `quick-links-open` flash
- [x] 3.3 Include the drawer in `layouts/app.blade.php`; add link-icon toggle button to the navbar (desktop + mobile)
- [x] 3.4 Release details main column: "Links" card (below Meeting notes, titled "Links") — release's `visibleTo` links + inline add form with hidden `release_id`; eager-load in `ReleaseController@show`

## 4. Tests & Verification

- [x] 4.1 Feature tests: add private/shared links, validation (missing label, malformed URL, `javascript:` scheme rejected), author recorded
- [x] 4.2 Feature tests: drawer content — own private visible to self, hidden from others; shared visible to full-access roles; dev/QA see only their own links and cannot create shared ones (validation error); author-only edit/delete enforced
- [x] 4.3 Feature test: drawer toggle present on an arbitrary page for all roles
- [x] 4.4 Feature tests: release card shows visible links only, add-from-release-page attaches the release, release deletion nulls `release_id` (links survive)
- [x] 4.5 Run full test suite and fix regressions
