## 1. Backend

- [x] 1.1 Migration `visibility` (default `shared`); `Event` constants, cast, `isPrivate()`, extended `scopeVisibleTo`; private events skip activity logging
- [x] 1.2 `EventPolicy` update/delete creator-only; `EventRequest` + API accept `visibility`; `EventResource` exposes it
- [x] 1.3 `EventService::inWindow` `show` filter (all/mine/shared); `DashboardService` meetings use the scope
- [x] 1.4 Calendar import: per-row visibility (default shared)

## 2. Frontend

- [x] 2.1 Private/Shared choice in the event form; lock icon on chips and the event page; All/Mine/Shared filter
- [x] 2.2 Review rows in the calendar import get the Private/Shared choice

## 3. Tests

- [x] 3.1 Private events hidden from others (web + API, admin included), visible to attendees; creator-only edit/delete; default shared; filter; imports
- [x] 3.2 `php artisan test` and `vendor/bin/pint --test`
