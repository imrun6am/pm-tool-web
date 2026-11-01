## 1. Drawer

- [x] 1.1 Restyle `events/form.blade.php` for the drawer (no card, sections stacked)
- [x] 1.2 Create `events/_drawer.blade.php` (slide-over, `open-event-drawer` listener, date prefill, `_drawer` marker, reopen on errors or `new`/`edit` flag)

## 2. Wiring

- [x] 2.1 `CalendarController@index`: pass releases, active users and a new Event for the drawer
- [x] 2.2 `calendar/index.blade.php`: include the drawer; "New event" and day cells open it
- [x] 2.3 `EventController@show` + `events/show.blade.php`: edit drawer for users who may update; Edit opens it
- [x] 2.4 `EventController`: create/edit redirect into the drawer, store redirects to the calendar month
- [x] 2.5 Remove `events/create.blade.php` and `events/edit.blade.php`

## 3. Tests & verification

- [x] 3.1 Update `CalendarEventTest` for the new redirects; add drawer rendering and validation-reopen tests
- [x] 3.2 `php artisan test`, `vendor/bin/pint --test`, `npm run build`, and a browser check of create/edit/validation
