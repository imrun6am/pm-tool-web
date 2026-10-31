## ADDED Requirements

### Requirement: Add a quick link
The system SHALL allow any authenticated user to add a quick link with a label, an http(s) URL, and a visibility of `private` or `shared` (default private). Developer and QA users SHALL only be able to create private links — a shared visibility submitted by them SHALL be rejected. The link MAY be attached to a release or left general, and SHALL record its author.

#### Scenario: Add a private link
- **WHEN** a user adds a link with visibility private
- **THEN** it is stored, owned by the user, and visible only to them

#### Scenario: Add a shared link
- **WHEN** a user adds a link with visibility shared
- **THEN** it becomes visible to all authenticated users

#### Scenario: Reject an invalid URL
- **WHEN** a link is submitted with a malformed URL or a non-http(s) scheme (e.g. `javascript:`)
- **THEN** the system rejects it with a validation error

#### Scenario: Reject a missing label
- **WHEN** a link is submitted without a label
- **THEN** the system rejects it with a validation error

#### Scenario: Developer/QA cannot create a shared link
- **WHEN** a developer or QA user submits a link with visibility shared
- **THEN** the system rejects it with a validation error

### Requirement: Quick links drawer
The system SHALL provide a side drawer, openable from the navigation on every page, listing the user's own links (with their visibility) and — for full-access roles only — other users' shared links (with the author's name), newest first, own links first. Developer and QA users SHALL see only their own links, with no shared section and no visibility selector. Link targets SHALL open in a new tab. Other users' private links SHALL never appear.

#### Scenario: Drawer shows own and shared links
- **WHEN** a full-access user opens the drawer
- **THEN** they see their own links and every shared link, and no one else's private links

#### Scenario: Drawer is available on any page
- **WHEN** a user is on any authenticated page
- **THEN** the drawer toggle is present and the drawer lists their links

#### Scenario: Developer/QA see only their own private links
- **WHEN** a developer or QA user opens the drawer while other users have shared links
- **THEN** they see only their own links — no shared links from others and no visibility selector in the forms

### Requirement: Release links section
The system SHALL display a "Links" section in the main content area of a release's details page (not the sidebar) listing that release's links the viewer may see (own plus shared — never others' private links), with an inline form to add a link pre-attached to the release. Release-linked links SHALL carry a release badge in the drawer. Deleting a release SHALL NOT delete its links; they become general links.

#### Scenario: Links section lists the release's visible links
- **WHEN** a user views a release with links (own private, own shared, and another user's private one)
- **THEN** the Links section shows their own and all shared links for that release, but not the other user's private link

#### Scenario: Add a link from the release page
- **WHEN** a user adds a link via the Links section's form
- **THEN** it is stored attached to that release and appears in the section and in the drawer with a release badge

#### Scenario: Release deletion keeps links
- **WHEN** a release with links is deleted
- **THEN** the links remain as general links

### Requirement: Manage own quick links
The system SHALL allow a link's author to edit its label, URL, and visibility and to delete it from within the drawer, and SHALL prevent other users from editing or deleting it. After a save the drawer SHALL be open again so the user sees the result.

#### Scenario: Author edits their link
- **WHEN** the author updates a link's label, URL, or visibility
- **THEN** the changes are saved and the drawer shows them

#### Scenario: Non-author cannot modify
- **WHEN** another user attempts to edit or delete someone's link
- **THEN** the system forbids the action

#### Scenario: Author deletes their link
- **WHEN** the author deletes a link
- **THEN** it is removed from the drawer for everyone
