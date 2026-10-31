> Implemented and verified. 56 tests pass (8 new user-management tests). Live smoke test confirmed
> only Admin+CTO reach /users (others 403), user create works, and deactivated users are blocked
> from logging in.

## 1. Roles & model

- [x] 1.1 Migration: add nullable `deactivated_at` to `users`
- [x] 1.2 `User`: role constants + `ROLES` label map; `roleLabel()`, `canManageUsers()`, `isActive()`, `active` scope; add `deactivated_at` to fillable + datetime cast
- [x] 1.3 Add `RecordsActivity` to `User` (password already ignored); `activityTitle()` = name, release id null

## 2. Auth guards

- [x] 2.1 `EnsureUserCanManageUsers` middleware (admin or cto) + `manage-users` alias
- [x] 2.2 `EnsureUserIsActive` middleware in the authenticated group (logout + redirect deactivated users)
- [x] 2.3 Block deactivated login in Breeze `LoginRequest::authenticate()`
- [x] 2.4 Disable self-registration: remove register routes from `routes/auth.php` and the register links in guest views

## 3. User CRUD

- [x] 3.1 `UserRequest`: name, unique email (ignore self on edit), role in set, password required on create / optional on edit (confirmed)
- [x] 3.2 `UserController`: index, create, store, edit, update (incl. password reset), toggleActive, destroy
- [x] 3.3 Lockout guards: cannot deactivate/delete self; cannot deactivate/delete/demote the last active admin
- [x] 3.4 Routes under `manage-users`

## 4. Views & nav

- [x] 4.1 Users index (list with role + status, actions) 
- [x] 4.2 Users create/edit forms (name, email, role, password / reset password, active toggle)
- [x] 4.3 Nav: "Users" link for Admin/CTO; role badge shows the six-role label
- [x] 4.4 Reusable role-badge partial

## 5. Seed, test, verify

- [x] 5.1 Seeder: demo CTO, Team Lead, Developer, QA accounts (password `password`)
- [x] 5.2 Feature tests: admin+CTO can manage users / others 403; duplicate email rejected; deactivated cannot log in; last-admin + self guards; a non-admin role can comment + change task status; register route gone
- [x] 5.3 Migrate, `npm run build`, run full test suite, live smoke test, then check off tasks and `openspec validate`

## 6. Revision: soft delete + departed-user tags

- [x] 6.1 `SoftDeletes` on `User` (+ `deleted_at` migration); destroy ends team memberships (`left_at`) then soft-deletes; guards (self / last admin) unchanged
- [x] 6.2 `withTrashed()` on user-bearing relations (tasksheet member, comment user, meeting-note author, note author, event creator, task assignee/creator, activity causer, document uploader)
- [x] 6.3 `User::statusTag()` + `partials/user-tag` applied on tasksheet rows, comments, meeting notes, events; tasksheet history rows include deleted/deactivated members up to their departure date
- [x] 6.4 Feature tests: deleted user's data visible with "Deleted user" tag, cannot log in; deactivated tag shown
