## ADDED Requirements

### Requirement: Full-width settings page
The profile page SHALL use the full content width. It SHALL have two columns on large screens:
- a sticky section nav listing Profile, Password and Danger zone;
- the sections themselves.

On small screens the nav SHALL become a horizontal row of tabs above the sections. The nav SHALL link to each section by anchor and SHALL highlight the section currently in view. The page SHALL begin with an account overview showing the user's avatar, name, email, role, teams and member-since date.

#### Scenario: Section nav present
- **WHEN** a user opens their profile
- **THEN** the page shows links to `#profile`, `#password` and `#danger-zone`, and an overview with their name, email and role

#### Scenario: Behaviour unchanged
- **WHEN** a user updates their name, changes their password, or deletes their account
- **THEN** the same routes, validation errors and confirmation modal apply as before

### Requirement: Shared KPI strip on dashboard-style pages
The performance, performance-member, tasksheet, team and project pages SHALL present their headline metrics as `x-stat` cells inside a single `.stat-strip` card, matching the main dashboard. The separate stat-tile cards SHALL no longer be used on those pages.

#### Scenario: Team page summary
- **WHEN** a lead opens a team page
- **THEN** the member, release, booked-through and overlap metrics render as cells of one `stat-strip`

### Requirement: Task detail layout
The task page SHALL show:
- breadcrumbs (release name, then task title);
- a main column with the editable title and description, subtasks and comments;
- a properties panel with status, assignee, due date, phase, creator and the save action.

The form fields, their names and the save behaviour SHALL be unchanged.

#### Scenario: Task breadcrumbs
- **WHEN** a user opens a task in release "Checkout v2.4"
- **THEN** the breadcrumbs link "Checkout v2.4" to the release page

### Requirement: Event detail layout
The event page SHALL show:
- breadcrumbs back to Calendar;
- the event type and color in the header, with Edit and Delete actions for permitted users;
- a details panel: when, location, release and creator;
- attendees with avatars;
- the event's meeting notes, including the "Write meeting note" action where it is offered today.

#### Scenario: Event breadcrumbs
- **WHEN** a user opens an event
- **THEN** the breadcrumbs read "Calendar" followed by the event title

### Requirement: Calendar and release headers
The calendar SHALL use the standard page header with month navigation and "New event" in its actions, and SHALL highlight today's date. The release page SHALL show breadcrumbs back to Releases and the release status as a badge in its header.

#### Scenario: Release breadcrumbs
- **WHEN** a user opens a release
- **THEN** the breadcrumbs show "Releases" followed by the release name, and the header shows its status badge
