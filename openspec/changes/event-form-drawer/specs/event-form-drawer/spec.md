## ADDED Requirements

### Requirement: Create events in a drawer on the calendar
The calendar page SHALL let a user create an event in a right-side drawer, without leaving the calendar. "New event" SHALL open the drawer with an empty form. Clicking a day SHALL open it with the start set to 09:00 on that day.

#### Scenario: New event button
- **WHEN** a user on the calendar clicks "New event"
- **THEN** a drawer slides in from the right with the event form, and the calendar stays visible behind it

#### Scenario: Clicking a day
- **WHEN** a user clicks the cell for 14 July 2026
- **THEN** the drawer opens with "Starts" set to 2026-07-14 09:00

#### Scenario: Successful create
- **WHEN** the user submits a valid event starting in July 2026
- **THEN** the event is saved, the browser shows the July 2026 calendar with the drawer closed, and an "Event created." toast appears

### Requirement: Edit events in a drawer on the event page
On an event's page, a user who may update the event SHALL edit it in the same drawer, pre-filled with the event's values. Users who may not update the event SHALL NOT see the Edit control or receive the drawer.

#### Scenario: Editing
- **WHEN** the event's creator clicks Edit on the event page
- **THEN** the drawer opens with the event's current title, type, times, location, release and attendees

#### Scenario: Successful update
- **WHEN** the creator saves valid changes
- **THEN** the event page reloads with the changes and an "Event updated." toast

#### Scenario: No access
- **WHEN** a user who is neither the creator nor an admin opens the event page
- **THEN** no Edit control and no edit drawer are rendered

### Requirement: Validation errors reopen the drawer
When a drawer submission fails validation, the system SHALL return to the page the drawer was opened from, with the drawer open, the submitted values kept and the field errors shown inline.

#### Scenario: End before start
- **WHEN** the user submits an event whose end is before its start
- **THEN** the same page shows the drawer open with an error on "Ends" and the entered title still filled in

### Requirement: Create and edit URLs open the drawer
The `events/create` and `events/{event}/edit` URLs SHALL redirect to the calendar or the event page with the drawer open. Authorization SHALL still apply, so editing someone else's event stays forbidden.

#### Scenario: Create deep link
- **WHEN** a user opens `/events/create?date=2026-07-14` (for example from the ⌘K "New event" action)
- **THEN** they are redirected to the July 2026 calendar with the create drawer open for 14 July

#### Scenario: Edit deep link
- **WHEN** the creator opens `/events/{id}/edit`
- **THEN** they are redirected to that event's page with the edit drawer open

#### Scenario: Edit deep link without access
- **WHEN** another non-admin user opens `/events/{id}/edit`
- **THEN** the response is 403 Forbidden
