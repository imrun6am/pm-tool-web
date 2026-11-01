## Decisions

**Who sees a private event: the creator and its attendees.** Inviting someone to a private event is a deliberate choice, and an invite the invitee can't see is useless. This matches how private events with guests behave in common calendars. Admins and leads get no exception: private means private.

**Team scoping still applies to shared events.** The existing `LimitsToViewerTeams` rule (developers and QA see their teams' events) continues to apply. Private events are checked before it: creator or attendee, otherwise hidden.

**Edits are creator-only; admins may also delete shared events.** Editing follows the user's rule ("modified by the one who added"). The user confirmed that admins keep delete rights for clean-up. That covers shared events only: an admin can't see someone else's private event, so they can't delete it either. A deleted user's shared events can be removed by an admin.

**Activity.** `RecordsActivity` writes to a feed every lead can read. `Event::recordActivity` therefore skips events that are private after the change. Entries written while an event was shared stay as they are.

**Imports default to Shared**, matching the event form's default. Each review row has its own Private/Shared choice.

**Filter.**
- `show=mine`: events I created.
- `show=shared`: shared events only.
- `show=all` (default): everything I may see.

The filter is applied in `EventService::inWindow`, so the web calendar and the API agree.
