## 1. Setup

- [x] 1.1 Add `@hotwired/turbo` (^8.0) to package.json and install
- [x] 1.2 Import Turbo in `resources/js/app.js` and set the progress-bar delay to 0
- [x] 1.3 Add the `turbo-cache-control` (no-cache) and `view-transition` meta tags to `layouts/app.blade.php`

## 2. Progress bar and transitions

- [x] 2.1 Style `.turbo-progress-bar` in `app.css` with the brand gradient, above all overlays
- [x] 2.2 Add view-transition CSS: a short root crossfade, the sidebar on its own layer, and no animation under reduced motion
- [x] 2.3 Suspend smooth `scroll-behavior` during Turbo renders

## 3. Component lifecycle

- [x] 3.1 `appShell`: remove the media listener and clear the html scroll lock in `destroy()`
- [x] 3.2 `toaster`: remove the window `toast` listener in `destroy()`
- [x] 3.3 `confirmer`: remove the document submit listener in `destroy()`; re-submit confirmed forms via `requestSubmit()`
- [x] 3.4 `settingsNav`: disconnect the observer and remove the scroll listener in `destroy()`
- [x] 3.5 `aiEnhance`: remove the document Trix listeners in `destroy()`
- [x] 3.6 `commandPalette.go()`: navigate with `Turbo.visit`
- [x] 3.7 Destroy the outgoing `<body>`'s Alpine tree on `turbo:render` (Alpine never tears down the swapped-out body itself)
- [x] 3.8 Declare each teardown field in component data (undeclared `this.x` from nested components lands on `appShell`)

## 4. Opt-outs

- [x] 4.1 Add `data-turbo="false"` to the KPI report, release document and tasksheet PDF downloads
- [x] 4.2 Add `data-turbo="false"` to the logout form(s)

## 5. Verification

- [x] 5.1 Browser: navigate dashboard → releases → board → calendar → notes → meeting notes → tasksheet → performance with no blank flash and the bar visible
- [x] 5.2 Browser: board drag-and-drop, Trix + Enhance, ⌘K, confirm modal, flash toast after save, validation errors, downloads, logout, back/forward scroll
- [x] 5.3 `npm run build`, `vendor/bin/pint --test`, `php artisan test`
