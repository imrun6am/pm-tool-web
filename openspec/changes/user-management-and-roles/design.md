## Context

The app currently ships a two-role model (`admin`/`viewer`) via a `role` column on `users`, with an `admin` middleware guarding structural writes and, since the collaboration change, all authenticated users able to work on tasks/comments. This change expands the role labels to six and adds Admin/CTO-managed user accounts, with self-registration disabled and deactivated accounts blocked from logging in. The user chose a collaborators-only model, Admin+CTO user management, and admin-set passwords.

## Goals / Non-Goals

**Goals:**
- Six roles with readable labels; a single `role` string column keeps its shape.
- Admin/CTO CRUD of users: create with password, edit name/email/role, reset password, deactivate/reactivate.
- Block login for deactivated users; sign them out mid-session.
- Disable public registration; prevent self-lockout and removal of the last admin.
- Audit user changes in the existing activity log.

**Non-Goals:**
- No per-role granular permissions beyond "Admin manages structure / Admin+CTO manage users / everyone collaborates."
- No teams-to-users membership, per-project permissions, invitations/email, SSO, or 2FA.
- No soft-delete of users beyond `deactivated_at`; deleting a user is a hard delete (nullable FKs already null out authored comments / assignments / activity causers).

## Decisions

**Roles stay a string column with constants + a label map.**
`User::ROLES` maps slug → label (`admin`→Admin, `cto`→CTO, `team_lead`→Team Lead, `developer`→Developer, `qa`→QA, `viewer`→Viewer). `isAdmin()` remains the structural gate (unchanged, so all existing `admin` middleware and `@if(isAdmin())` checks keep working). `canManageUsers()` returns true for `admin` or `cto`. A Spatie-style permission package is unnecessary for this fixed, tiny matrix.

**Two guards for user management and activity.**
A `manage-users` middleware alias (`EnsureUserCanManageUsers`) wraps the user routes and checks `canManageUsers()`. `UserController` additionally enforces lockout rules the middleware can't express. The `User` model gains `RecordsActivity` (password/remember_token already in the trait's ignore list) so account changes appear in the audit feed, attributed to the acting manager; `activityReleaseId()` returns null (global, not release-scoped).

**Deactivation via `deactivated_at` + a login check + a runtime middleware.**
A nullable `deactivated_at` timestamp marks inactive accounts (mirrors the `archived_at` pattern on projects/teams). Login is blocked in Breeze's `LoginRequest::authenticate()` — after a successful credential match, if the user is deactivated it logs them straight back out and throws a validation error. An `EnsureUserIsActive` middleware added to the authenticated group catches users deactivated mid-session: it logs them out and redirects to login. Both layers are cheap and together cover "cannot log in" and "signed out if deactivated while active."

**Self-registration removed at the route layer.**
Breeze's `register` GET/POST routes are removed from `routes/auth.php` and the "Register" links dropped from the guest views, so no registration form is served. The `RegisteredUserController` is left in place but unreferenced (smallest, reversible change). Login/password-reset flows are untouched.

**Lockout guards live in the controller/request.**
`UserController` refuses to deactivate/delete the acting user's own account, and refuses to deactivate, delete, or demote the last active Admin (computed as `User::active()->where('role','admin')` count). These checks return a friendly flash error rather than a hard 403 so the manager sees why.

**Password handling.**
Create requires a password (Laravel's default password rules, confirmed). Edit keeps the password unless a new one is supplied (a dedicated "reset password" field/action). Hashing uses the model's existing `hashed` cast.

## Risks / Trade-offs

- [A manager could still lock everyone out by deactivating all admins one by one via other managers] → The last-active-admin guard blocks reducing admins to zero; CTOs manage users but are not admins, so they cannot silently remove the final admin.
- [Deleting a user with authored content] → All author/causer/assignee FKs are already `nullOnDelete`, so deletion leaves comments/tasks/activity intact but unattributed; deactivation is offered as the softer default in the UI.
- [Mid-session deactivation relies on the next request] → Acceptable; the runtime middleware ends the session on the very next authenticated request.
- [Expanding roles could surprise existing structural checks] → Only `isAdmin()` gates structure and it is unchanged; new roles are non-admin, so they inherit read + collaborate exactly like `viewer` does today.

## Migration Plan

1. Migration: add `deactivated_at` to `users`.
2. `User`: role constants/labels, `canManageUsers()`, `isActive()`, `active` scope, `RecordsActivity`, `deactivated_at` fillable/cast.
3. `EnsureUserCanManageUsers` + `EnsureUserIsActive` middleware and aliases; add active-check to `LoginRequest`; remove register routes/links.
4. `UserController` + `UserRequest` + routes under `manage-users`; nav "Users" link + six-role badges.
5. Seed demo CTO/Team Lead/Developer/QA; migrate, build, run tests, smoke-test.
Rollback: drop `deactivated_at`, restore register routes; `migrate:fresh --seed`.

## Open Questions

- None blocking. If per-team membership or finer permissions are needed later, the `role` column can be complemented by a pivot without disturbing this model.
