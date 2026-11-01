## ADDED Requirements

### Requirement: Split-screen sign-in
On wide screens, the sign-in page SHALL show a branded panel describing PM Tool beside the sign-in form. On narrow screens, the description SHALL collapse to a compact header above the form.

#### Scenario: Desktop
- **WHEN** a visitor opens /login at 1280px wide
- **THEN** the left half describes PM Tool, and the right half holds the form

#### Scenario: Phone
- **WHEN** a visitor opens /login at 375px wide
- **THEN** a compact brand header sits above the form, and nothing scrolls horizontally

### Requirement: Accessible, responsive form
The sign-in form SHALL:
- label every field and keep `autocomplete` hints;
- let the user show or hide the password;
- show validation errors next to their fields;
- show a busy state while signing in.

#### Scenario: Wrong password
- **WHEN** a user submits wrong credentials
- **THEN** the email field is marked invalid, and the error is read with it

### Requirement: Auth behaviour unchanged
Sign-in, rate limiting, password-reset links and the post-reset status message SHALL behave exactly as before. No registration link SHALL appear.

#### Scenario: Existing auth tests
- **WHEN** the auth feature tests run
- **THEN** they all pass
