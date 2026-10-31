## 1. Board Page

- [ ] 1.1 Summary strip: task count, % done, segmented status progress bar
- [ ] 1.2 "My tasks" toggle (`aria-pressed`) in the filter bar, keeping `release_id`
- [ ] 1.3 Status-colored columns: accent, tinted header with count pill, drop-target highlight, dashed empty drop zone, restyled "Add a card"
- [ ] 1.4 Collapsible Archive rail, persisted in localStorage (try/catch), still a drop target

## 2. Cards

- [ ] 2.1 Card redesign: project accent, release chip, phase chip, due pill (overdue/today/upcoming), subtask mini bar, comment count, `x-avatar`, outline icons, hover lift
- [ ] 2.2 "Move to" status select per card (keyboard accessible; visible on hover/focus, always on touch)

## 3. Script

- [ ] 3.1 Shared `persist()` with restore-on-failure, error and success toasts, and count and empty-state sync; the move menu wired to it; drop-target classes during drag

## 4. Tests & Verification

- [ ] 4.1 Feature tests: summary numbers, My tasks toggle URL and pressed state, move-menu options per card, overdue pill
- [ ] 4.2 Run `php artisan test`: full suite green with `KanbanBoardTest` unchanged
- [ ] 4.3 Run `npm run build`; headless check at 1440 and 390: drag-and-drop and move-menu persist, a forced failure restores the card, archive toggle persists, no JS errors
