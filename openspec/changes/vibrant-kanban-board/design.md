## Context

`board/index.blade.php` renders one column per `Task::STATUSES` entry. An inline script implements HTML5 drag-and-drop: it PATCHes `board.move` with `{status, ordered_ids}` and reloads the page on failure. `partials/board-card.blade.php` renders each card with emoji metadata. Global toasts are available through `window.dispatchEvent(new CustomEvent('toast', {detail: {type, message}}))`.

## Goals / Non-Goals

**Goals:** a vibrant but restrained look with status color accents; clear card metadata; drag feedback; a keyboard path for moving cards; non-destructive error handling.

**Non-Goals:** swimlanes, WIP limits, inline editing of card fields, any backend change.

## Decisions

### 1. Status palette
One map per status gives a dot, an accent, a column tint and a pill color:
- todo: slate
- in_progress: blue
- in_review: amber
- recheck: orange
- done: emerald
- archive: slate

Literal class strings keep Tailwind's scanner happy. Columns are `bg-{c}-50/60` with a 3px `border-t-{c}-500` top accent. Status colors already used by the dashboard donut are the same hues.

### 2. Summary
Computed in the view: `$columns->map->count()`, the total, and `done / total`. The segmented bar uses the same `$statusFill` hex map as the dashboard donut.

### 3. Move menu
A native `<select aria-label="Move to status">` per card, keeping keyboard and screen-reader behavior for free. It is visually a small "Move" chip that is visible on card hover and focus-within, and always visible on touch screens (`[@media(hover:none)]`).

On change, the script:
1. remembers the card's parent and next sibling;
2. appends the card to the target column;
3. calls `persist()`.

The same `persist()` serves drag-and-drop, and it now:
- takes a `restore` callback, which the select path also uses;
- on `!ok` or a network error, puts the card back, updates the counts and fires an error toast, instead of `location.reload()`;
- on success, fires a toast ("Moved to Done") and syncs the select's value.

### 4. Drag feedback
`dragenter`/`dragover` add `.is-drop-target` to the column (`ring-2 ring-brand-400 bg-brand-50/40`). `dragleave` (checking `relatedTarget` containment) and `drop` remove it. The dragged card gets `opacity-50 rotate-1`.

### 5. Collapsed archive
Alpine `x-data` on the archive column, with `collapsed` read from and written to `localStorage['rp.board.archive']` inside try/catch, defaulting to collapsed. Collapsed, the column is `w-12`, showing a vertical label and count (`[writing-mode:vertical-rl]`). Its list stays in the DOM, hidden, so moves into Archive via the menu still work, and dropping onto the rail is allowed.

### 6. My tasks
A link-styled toggle (`aria-pressed`) that builds the query with `release_id` kept and `assignee_id` toggled. It sits in the filter bar next to the selects.

## Risks / Trade-offs

- **A select on every card** adds markup weight: 6 options × N cards.
  - *Accepted:* boards here hold tens of cards, not thousands.
- **A hidden archive list** could confuse drag users.
  - *Mitigation:* the rail itself is a drop target and shows the count.

## Migration Plan

Views and CSS only. Run `npm run build`. Rollback is a revert.
