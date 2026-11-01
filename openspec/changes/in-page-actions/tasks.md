## 1. Infrastructure

- [ ] 1.1 Add `components/live-region.blade.php` (turbo-frame, `target="_top"`, `data-live-region`)
- [ ] 1.2 Region submit hook in `app.js`: point non-GET forms inside a region at it
- [ ] 1.3 Toast bridge: render `#flash-toasts` JSON in `layouts/toasts`, dispatch toasts on `turbo:frame-render`
- [ ] 1.4 Progress bar for region submissions; Alpine teardown before region render; frame-missing → whole-page visit
- [ ] 1.5 `turbo-refresh-scroll=preserve` meta; scroll the first invalid field into view after a same-page refresh
- [ ] 1.6 CSS: `turbo-frame` block display, busy dimming

## 2. Convert first batch

- [ ] 2.1 Notes: composer + feed in one region (create, edit, delete)
- [ ] 2.2 Users index: region around stats + table (activate/deactivate, delete)
- [ ] 2.3 Competencies index: region around the table (toggle, delete via the confirm modal)
- [ ] 2.4 Quick-links drawer: region around the drawer body (add, edit, pin, delete); the add-form state lives inside the region
- [ ] 2.5 Release tasks panel: region around the panel (add task/subtask, status, delete)

## 3. Verification

- [ ] 3.1 Feature tests: each converted page renders its region, and a Turbo-style submission redirects back to the referrer, where the region shows the new state and the `#flash-toasts` payload
- [ ] 3.2 Browser: each converted action updates in place (no body swap), with a toast and inline validation errors
- [ ] 3.3 `npm run build`, `vendor/bin/pint --test`, `php artisan test`
