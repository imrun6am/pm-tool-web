## 1. Board Page

- [x] 1.1 Summary strip: task count, % done, segmented status progress bar
- [x] 1.2 "My tasks" toggle (`aria-pressed`) in the filter bar, keeping `release_id`
- [x] 1.3 Status-colored columns: accent, tinted header with count pill, drop-target highlight, dashed empty drop zone, restyled "Add a card"
- [x] 1.4 Collapsible Archive rail, persisted in localStorage (try/catch), still a drop target

## 2. Cards

- [x] 2.1 Card redesign: project accent, release chip, phase chip, due pill (overdue/today/upcoming), subtask mini bar, comment count, `x-avatar`, outline icons, hover lift
- [x] 2.2 "Move to" status select per card (keyboard accessible; visible on hover/focus, always on touch)

## 3. Script

- [x] 3.1 Shared `persist()` with restore-on-failure, error and success toasts, and count and empty-state sync; the move menu wired to it; drop-target classes during drag

## 4. Tests & Verification

- [x] 4.1 Feature tests: summary numbers, My tasks toggle URL and pressed state, move-menu options per card, overdue pill
- [x] 4.2 Run `php artisan test`: full suite green with `KanbanBoardTest` unchanged
- [x] 4.3 Run `npm run build`; headless check at 1440 and 390: drag-and-drop and move-menu persist, a forced failure restores the card, archive toggle persists, no JS errors

## 5. Follow-up Fixes

- [x] 5.1 Fix: vertical wheel/trackpad scrolling over the columns didn't scroll the page. The board scroller used `.scroll-area` (`overscroll-behavior: contain` on both axes); it now uses `.board-scroller`, which contains only horizontal overscroll.
- [x] 5.2 Full-height board on lg (`<x-app-layout full-height>`): the columns stretch to the viewport and scroll their own cards (`.board-cards`), so the horizontal scrollbar is always visible. Phones keep normal page scroll. The move menu refocuses with `preventScroll`.
- [x] 5.3 Fix: in full-height columns, flexbox shrank the cards (`overflow-hidden` lets a flex item's automatic minimum height fall to 0), squashing them and spilling their content. Cards and the empty drop zone are now `flex-none`, so the column scrolls instead (checked: 0 of 23 cards squashed at 1440×900, 1440×700 and 390px).
