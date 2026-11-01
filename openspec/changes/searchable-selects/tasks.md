## 1. Build
- [x] 1.1 `searchable-select.js`: enhancer, panel, filter, keyboard, ARIA, sync hooks, observer, Turbo hooks, validation message
- [x] 1.2 `.ss-*` styles: trigger parity with field/filter selects, panel, bottom sheet, reduced motion
- [x] 1.3 Import in `app.js`

## 2. Polish
- [x] 2.1 Avatars and colour dots (`#ss-swatches`), clear control, brand highlight, fade-out close
- [x] 2.2 `.menu-panel` / `.menu-item` / `.menu-label` across `x-dropdown`, the bell, the export popover and `x-multi-select`; ↑/↓ in `x-dropdown`

## 3. Verification
- [x] 2.1 Feature test: the server contract for selects is unchanged
- [x] 2.2 Browser: filter bar, release form x-model, quick capture dialog, required validation, keyboard, phone sheet
- [x] 2.3 `php artisan test`, `pint --test`
