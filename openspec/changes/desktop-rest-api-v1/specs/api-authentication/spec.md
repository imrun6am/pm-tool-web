## ADDED Requirements

### Requirement: Token login for desktop clients
The system SHALL allow a client to exchange an email address, a password, and a device name for a bearer token. The response SHALL include the plain-text token exactly once, together with the authenticated user. The system SHALL reject invalid credentials with a validation error that does not reveal whether the email exists.

#### Scenario: Valid credentials issue a token
- **WHEN** a client posts a correct email, password, and device name
- **THEN** the system returns a bearer token and the authenticated user

#### Scenario: Invalid credentials rejected
- **WHEN** a client posts an unknown email or a wrong password
- **THEN** the system returns a credentials error that does not distinguish the two cases

#### Scenario: Device name required
- **WHEN** a client posts credentials without a device name
- **THEN** the system rejects the request with a validation error

### Requirement: Bearer authentication on protected endpoints
The system SHALL authenticate every protected API endpoint from a bearer token presented in the `Authorization` header. A request with no token, a malformed token, or a revoked token SHALL be rejected as unauthenticated.

#### Scenario: Valid token authenticates
- **WHEN** a client calls a protected endpoint with a valid bearer token
- **THEN** the request is processed as that user

#### Scenario: Revoked token rejected
- **WHEN** a client calls a protected endpoint with a token that has been revoked
- **THEN** the system responds 401

### Requirement: Self-registration remains disabled
The system SHALL NOT provide any API endpoint for public account creation. Accounts SHALL be created only by users permitted to manage users, exactly as on the web.

#### Scenario: No public sign-up
- **WHEN** a client looks for a registration endpoint
- **THEN** none exists and accounts can only be created through the user-management endpoints by an authorized role

### Requirement: Deactivated and deleted accounts cannot use the API
The system SHALL refuse API access to any account that has been deactivated or deleted, taking effect on that account's next API request even if its token was issued while the account was active. The system SHALL NOT issue a token to a deactivated or deleted account at login.

#### Scenario: Deactivation takes effect immediately
- **WHEN** an account holding a valid token is deactivated and then makes an API request
- **THEN** the request is refused

#### Scenario: Deactivated account cannot log in
- **WHEN** a deactivated account posts correct credentials
- **THEN** no token is issued and the response explains the account is deactivated

### Requirement: Current user and effective permissions
The system SHALL provide an endpoint returning the authenticated user together with their effective permissions — at minimum whether they may manage users, manage releases, manage the workspace, manage team members, oversee all teams, manage competencies, and whether their access is limited — plus the teams they belong to and the team(s) they lead. Clients SHALL use this to build their navigation.

#### Scenario: Client builds its menu from permissions
- **WHEN** a client fetches the current-user endpoint
- **THEN** it receives the user, their role, their effective permission flags, their teams, and the teams they lead

#### Scenario: Permissions match the role
- **WHEN** a developer fetches the endpoint
- **THEN** the limited-access flag is true and the management flags are false

### Requirement: Device listing and revocation
The system SHALL let an authenticated user list their own active tokens, showing each device's name, creation time, and last-used time, and SHALL let them revoke the current token (log out), a single named device, or every device at once. A user SHALL NOT be able to see or revoke another user's tokens.

#### Scenario: User lists their devices
- **WHEN** a user who has logged in from two devices lists their tokens
- **THEN** both are returned with their device names and last-used times

#### Scenario: Logout revokes only the current token
- **WHEN** a user logs out on one device
- **THEN** that token stops working and their other devices remain signed in

#### Scenario: Revoke all signs out everywhere
- **WHEN** a user revokes all tokens
- **THEN** every one of their tokens stops working

#### Scenario: Cannot revoke another user's token
- **WHEN** a user attempts to revoke a token belonging to someone else
- **THEN** the system refuses

### Requirement: Profile and password self-service
The system SHALL let an authenticated user update their own name and email, and change their own password by supplying their current password and a new one that satisfies the application's password rules. Changing the password SHALL revoke the user's other tokens while keeping the current one valid, so other devices are signed out.

#### Scenario: User updates their profile
- **WHEN** a user submits a new name and a unique email
- **THEN** the profile is updated and the updated user is returned

#### Scenario: Password change requires the current password
- **WHEN** a user submits a new password with an incorrect current password
- **THEN** the change is rejected with a validation error

#### Scenario: Password change signs out other devices
- **WHEN** a user successfully changes their password
- **THEN** their other tokens are revoked and the token used for the change still works

### Requirement: Login throttling
The system SHALL rate-limit login attempts keyed on the submitted email and the client IP address, and SHALL reject further attempts with a throttle error once the limit is exceeded, stating how long the caller must wait.

#### Scenario: Repeated failures throttled
- **WHEN** a client submits several failed logins for the same email from the same address
- **THEN** further attempts are refused with a throttle message until the window passes

#### Scenario: Successful login clears the counter
- **WHEN** a client logs in successfully after a failed attempt
- **THEN** the throttle counter for that email and address is cleared
