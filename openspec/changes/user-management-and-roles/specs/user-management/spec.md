## ADDED Requirements

### Requirement: Manage user accounts
The system SHALL allow a user with the Admin or CTO role to list users and to create new users. Creating a user SHALL require a name, a unique email, a role from the supported set, and an initial password. Users without the Admin or CTO role SHALL NOT be able to reach user management.

#### Scenario: Admin creates a user
- **WHEN** an Admin submits a new user with a name, unique email, role, and password
- **THEN** the system creates the account and it can be used to log in

#### Scenario: CTO can manage users
- **WHEN** a CTO opens the users screen and creates a user
- **THEN** the system permits it

#### Scenario: Non-manager is forbidden
- **WHEN** a user who is not an Admin or CTO requests any user-management page or action
- **THEN** the system denies it with an authorization error

#### Scenario: Reject duplicate email
- **WHEN** an Admin creates a user with an email already in use
- **THEN** the system rejects it with a validation error

### Requirement: Edit users, roles, and passwords
The system SHALL allow an Admin or CTO to edit a user's name, email, and role, and to reset a user's password. Password reset SHALL replace the stored password with a new one.

#### Scenario: Change a user's role
- **WHEN** a manager changes a user's role to another supported role
- **THEN** the system saves the new role and it governs that user's next session

#### Scenario: Reset a password
- **WHEN** a manager sets a new password for a user
- **THEN** the system stores the new password and the user can log in with it

### Requirement: Deleting a user preserves their contributions
Deleting a user SHALL be a soft delete: the account can no longer sign in and disappears from user listings and member pickers, but everything the user produced (tasksheet rows, tasks, comments, daily notes, meeting notes, events, activity entries) SHALL remain visible. The user's team memberships end at deletion time.

#### Scenario: Deleted user's data stays visible
- **WHEN** an admin deletes a user who has tasksheet rows and comments
- **THEN** those rows and comments remain visible afterwards

#### Scenario: Deleted user cannot sign in
- **WHEN** someone attempts to log in with a deleted user's credentials
- **THEN** authentication fails

### Requirement: Departed users are tagged on their data
The system SHALL label a deleted user's name on their surviving data with a "Deleted user" tag, and a deactivated user's name with a "Deactivated" tag, wherever their contributions are displayed (tasksheet rows, comments, meeting notes, events).

#### Scenario: Deleted user tag
- **WHEN** a user views data produced by a deleted user
- **THEN** the author's name carries a "Deleted user" tag

#### Scenario: Deactivated user tag
- **WHEN** a user views data produced by a deactivated user
- **THEN** the author's name carries a "Deactivated" tag, and the tag disappears if the account is reactivated

### Requirement: Deactivate and reactivate accounts
The system SHALL allow an Admin or CTO to deactivate and reactivate an account. A deactivated user SHALL be unable to log in, and SHALL be signed out if they are deactivated during an active session.

#### Scenario: Deactivated user cannot log in
- **WHEN** a deactivated user submits valid credentials
- **THEN** the system refuses the login and shows that the account is deactivated

#### Scenario: Reactivate restores access
- **WHEN** a manager reactivates an account
- **THEN** that user can log in again

#### Scenario: Active session ends after deactivation
- **WHEN** a user is deactivated while already logged in and then makes a request
- **THEN** the system signs them out and redirects to login

### Requirement: Prevent self-lockout
The system SHALL prevent a manager from deactivating or deleting their own account, and SHALL prevent deactivating, deleting, or demoting the last remaining active Admin.

#### Scenario: Cannot deactivate self
- **WHEN** a manager attempts to deactivate or delete their own account
- **THEN** the system refuses the action

#### Scenario: Cannot remove the last admin
- **WHEN** a manager attempts to deactivate, delete, or change the role of the only active Admin
- **THEN** the system refuses the action
