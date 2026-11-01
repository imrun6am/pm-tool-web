## ADDED Requirements

### Requirement: Event visibility
Every event SHALL be `shared` or `private`, defaulting to `shared`.
- A private event SHALL be visible only to its creator and its attendees.
- A shared event SHALL be visible to everyone the existing team-scoping rules allow.

Every listing and every direct URL (web and API) SHALL apply this rule. A direct request for an event the viewer may not see SHALL return 403.

#### Scenario: Private event hidden from others
- **WHEN** Alice creates a private event and Bob, who is not an attendee and may be an admin, opens the calendar or the event URL
- **THEN** the event is not listed, and the URL returns 403

#### Scenario: Attendee sees a private event
- **WHEN** Alice invites Carol to a private event
- **THEN** Carol sees it on her calendar

#### Scenario: Existing events
- **WHEN** the migration runs
- **THEN** every existing event is shared

### Requirement: Only the creator modifies an event
Only an event's creator SHALL be able to edit it. Only the creator, or an admin for a shared event, SHALL be able to delete it. Others SHALL get 403, and actions they may not take SHALL be hidden from them.

#### Scenario: Admin cannot edit someone's event
- **WHEN** an admin submits an update to another user's event
- **THEN** the response is 403 and the event is unchanged

#### Scenario: Admin deletes a shared event
- **WHEN** an admin deletes another user's shared event
- **THEN** it is deleted; for another user's private event the response is 403

### Requirement: Calendar filter
The calendar SHALL offer All / Mine / Shared, and the API SHALL accept the same `show` values.

#### Scenario: Mine
- **WHEN** a user picks Mine
- **THEN** only events they created are listed
