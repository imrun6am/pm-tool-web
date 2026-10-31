## ADDED Requirements

### Requirement: Authenticated access
The system SHALL require a user to be authenticated before viewing or modifying any planning data. Unauthenticated requests to application routes SHALL be redirected to the login page.

#### Scenario: Unauthenticated user is redirected
- **WHEN** an unauthenticated user requests the dashboard or any resource page
- **THEN** the system redirects them to the login page

#### Scenario: Authenticated user reaches the dashboard
- **WHEN** a user submits valid credentials on the login page
- **THEN** the system authenticates them and redirects to the planning dashboard

### Requirement: Role assignment
The system SHALL assign each user exactly one role, either `admin` or `viewer`, stored on the user record and defaulting to `viewer` for newly registered users.

#### Scenario: New registration defaults to viewer
- **WHEN** a new user registers
- **THEN** the system creates the user with the `viewer` role

#### Scenario: Role persists on the user
- **WHEN** an admin sets a user's role to `admin`
- **THEN** the user's role is stored as `admin` and used on subsequent logins

### Requirement: Admin-only write access
The system SHALL permit only users with the `admin` role to create, edit, delete, or upload/delete documents. Users with the `viewer` role SHALL have read-only access to all pages.

#### Scenario: Viewer cannot modify data
- **WHEN** a `viewer` attempts to submit a create, edit, delete, or upload action
- **THEN** the system denies the action with an authorization error and does not change any data

#### Scenario: Viewer sees read-only UI
- **WHEN** a `viewer` views a page that has create/edit/delete controls for admins
- **THEN** those controls are hidden or disabled

#### Scenario: Admin can modify data
- **WHEN** an `admin` submits a create, edit, delete, or upload action
- **THEN** the system performs the action
