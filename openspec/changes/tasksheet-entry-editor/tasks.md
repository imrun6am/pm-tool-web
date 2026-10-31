## 1. Row Editor

- [x] 1.1 Teleported slide-over drawer per row: header (avatar, team, date, filled badge), sticky footer, close on Cancel/Escape/backdrop, scroll lock
- [x] 1.2 Attendance cards with the full-day leave inert-and-notice behaviour; half day keeps the fields editable
- [x] 1.3 Color-coded plan/result/tickets/comment sections, metric cards, lead-only feedback panel
- [x] 1.4 Reopen with old input and the first error after a failed save
- [x] 1.5 Display row: avatar, status badges, tinted metric pills, "Fill in" vs "Edit"

## 2. Theme Fix

- [x] 2.1 Move the Trix overrides outside `@layer components` so the build keeps them

## 3. Tests & Verification

- [x] 3.1 Feature tests: drawer markup and buttons, reopening after an error with old input, no feedback for members
- [x] 3.2 Browser check: open/fill/save, leave inerts the fields, Cancel/Escape release the scroll lock, no Alpine errors
- [x] 3.3 Run `php artisan test`: the full suite passes
