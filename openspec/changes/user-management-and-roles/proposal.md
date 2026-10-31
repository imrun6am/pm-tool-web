## Why

Today the app has only two roles (`admin`, `viewer`) and no way to create accounts from inside the tool — new users self-register as viewers. Teams need real, admin-created accounts that reflect who people are (Team Lead, Developer, QA, CTO) so they can log in and collaborate (comment, check/update tasks). This change adds user management and an expanded set of role labels.

## What Changes

- Expand the role set to **Admin, CTO, Team Lead, Developer, QA, Viewer**.
- Keep a **collaborators-only** permission model: only **Admin** manages structure (projects, teams, releases, phases, off-days, documents); **every** signed-in user (any role) can comment and check/update tasks and subtasks. Roles are organizational labels for everyone except Admin.
- Add **user management**, available to **Admin and CTO**: list users, create a user with an initial password and a role, edit name/email/role, reset a password, and deactivate/reactivate an account.
- **Deactivated users cannot log in** (and are signed out if deactivated mid-session).
- **Disable self-registration** — accounts are created by an Admin/CTO; users can still change their own password via Profile.
- Guard against lockout: a user cannot deactivate or delete their own account, and the **last active admin** cannot be deactivated, deleted, or demoted.
- Record user create/update/deactivate in the existing **activity log** (attributed to the acting Admin/CTO).

## Capabilities

### New Capabilities
- `user-management`: Admin/CTO create, edit, deactivate/reactivate, and reset passwords for user accounts, assigning any role.
- `role-permissions`: The expanded role set and the collaborators-only authorization model (Admin manages structure; Admin+CTO manage users; all roles collaborate), plus disabled self-registration and blocked login for deactivated users.

### Modified Capabilities
<!-- None as a formal delta. This supersedes the earlier auth-and-roles behavior (two roles,
     self-registration) but that spec is not yet archived to openspec/specs, so it is expressed
     here as the new role-permissions capability rather than a MODIFIED delta. -->

## Impact

- **Database**: add `deactivated_at` (nullable) to `users`.
- **Models**: `User` gains role constants/labels, `canManageUsers()`, `isActive()`, an `active` scope, and the `RecordsActivity` trait.
- **Auth**: a `manage-users` middleware (Admin or CTO); an active-account check on login and on each authenticated request; Breeze register routes and links removed.
- **Controllers/requests**: `UserController` + `UserRequest` (conditional password, unique email, role in set, lockout guards).
- **UI**: a Users management screen (index/create/edit), a nav "Users" link for Admin/CTO, and role badges reflecting the six roles.
- **Seed data**: demo CTO, Team Lead, Developer, and QA accounts.
