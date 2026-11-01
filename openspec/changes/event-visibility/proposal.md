## Why

The calendar mixes personal entries (a dentist slot, a focus block) with team events. The user's words: "there should be 2 type of events, one private and 1 shared or public, event can be modified by the one who added".

## What Changes

- **Visibility on events:** `shared` (default) or `private`. A new `visibility` column defaults to `shared`, so existing rows are unchanged.
  - **Shared:** everyone who can see the calendar, with the existing team scoping still applied (developers and QA see their teams' events).
  - **Private:** the creator and the attendees the creator invited. Nobody else, including admins and leads.
- **One scope everywhere:** `Event::scopeVisibleTo` (already used for team scoping) now also hides other people's private events. It covers:
  - the calendar month view;
  - `events.show` (403);
  - the API index and show;
  - the dashboard meetings list;
  - meeting-note event links and pickers.
- **Activity feed:** private events don't write to the shared activity feed.
- **Creator-only edits:** `EventPolicy` `update` is creator-only; the lead/admin override is removed. `delete` is the creator, or an admin for a shared event (the user confirmed admins keep clean-up rights). The actions are hidden from everyone else, on the web and via the API's `can_edit` / `can_delete`.
- **UI:**
  - a Private/Shared choice in the event form;
  - a lock icon on private calendar chips and on the event page;
  - an All / Mine / Shared filter on the calendar, mirrored by the API's `show` parameter.
- **Calendar import:** each proposed event gets a Private/Shared choice, defaulting to Shared, like the event form.
- **API:** `EventResource` gains `visibility` and `is_private`; `EventRequest` accepts `visibility`.

## Impact

- **Migration:** `add_visibility_to_events_table`, additive.
- **Code:**
  - Model: `Event`.
  - Policy and requests: `EventPolicy`, `EventRequest`.
  - Services: `EventService`, `DashboardService`, `EventImportService`.
  - Controllers and requests: `CalendarController`, `ConfirmEventImportRequest`.
  - API: `Api\V1\EventController`, `EventResource`, `docs/api-v1.md`.
  - Views: the calendar, event form and show page, the import review page, and the meeting-note show page.
