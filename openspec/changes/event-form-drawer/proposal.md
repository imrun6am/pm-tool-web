## Why

Adding or editing a calendar event currently opens a separate full page, which takes the user away from the month grid or the event they were looking at. A right-side drawer keeps them in context. It matches how quick links already work, and it fits the app's new soft-navigation feel.

## What Changes

- Creating an event happens in a **right-side drawer on the calendar**. "New event" opens it empty, and clicking a day opens it with that date filled in.
- Editing an event happens in the **same drawer on the event's page**, which the Edit button opens with the event's current values.
- On a validation failure, the user returns to the same page with the drawer reopened, their input kept and the errors shown inline.
- After a successful create, the user lands on the calendar month of the new event, with an "Event created." toast. After an update, the user lands on the event page with "Event updated." (unchanged).
- The `events.create` and `events.edit` URLs remain as deep links. They now redirect to the calendar (or the event page) with the drawer open. The standalone create and edit pages are removed. **BREAKING** (web UI only): those two URLs no longer render their own page.
- The API is unchanged.

## Capabilities

### New Capabilities
- `event-form-drawer`: create and edit calendar events in a slide-over drawer, without leaving the calendar or the event page.

### Modified Capabilities
<!-- None as a formal delta: calendar-events is not yet archived into openspec/specs. -->

## Impact

- **Controllers**: `EventController` (create/edit redirects, store redirect) and `CalendarController@index` (passes the form data).
- **Views**: new `events/_drawer.blade.php`. `events/form.blade.php` is restyled as a stacked layout for the drawer. `calendar/index.blade.php` and `events/show.blade.php` open the drawer. `events/create.blade.php` and `events/edit.blade.php` are removed.
- **Tests**: `tests/Feature/CalendarEventTest.php` is updated for the new redirects, and drawer coverage is added.
