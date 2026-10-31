## Context

`tasksheet/_row.blade.php` renders one `<tbody x-data="{ editing }">` per member. The display row toggled to an inline edit row (a `<tr>` spanning every column). The tests pin these strings:
- the feedback wording, for leads only;
- "Absent — not filled";
- "Half day";
- the leave labels;
- "Not added on the operating day".

## Decisions

### 1. A teleported slide-over drawer
The drawer lives in the row's action cell as `<template x-teleport="body">`. Alpine 3.15 keeps the row's `editing`/`leave` scope, and teleporting escapes the table's `overflow-x-auto` box and stacking context.

The row keeps a single `<tr>`: the old inline edit `<tr>` is removed. `x-effect` toggles `overflow-hidden` on `<html>` while the drawer is open.

### 2. Attendance cards
Radio inputs (`name="leave_type"`, `x-model="leave"`) styled as cards. `FULL_DAY_LEAVE_TYPES` comes from the model through `@js`, so the inert or dim logic follows the source of truth. Inert fields still submit, which is harmless because the server clears them for full-day leave, as it did before.

### 3. Old input
`$isOldRow` is true when the error bag is non-empty and `old('user_id')`/`old('date')` match this row. Field values then come from `old()`, and the drawer starts open with `$errors->first()` shown.

### 4. Trix theme outside `@layer`
Tailwind v3 purges `@layer components` rules whose classes it can't find in the content files. Trix's `.trix-button*` classes only exist at runtime, so the themed rules were dropped from the build. The block now sits after the layers as plain CSS. `@apply` still works there, and nothing gets purged.

**Gotcha:** Blade directives such as `@js` are not compiled inside component tag attributes (`<x-icon x-bind:class="…">`), so those use `{{ Js::from() }}`.

## Risks / Trade-offs

- **Many drawers in the DOM** (one per member).
  - *Accepted:* team sizes are small, and the drawers are hidden until opened.

## Migration Plan

Views and CSS only. Run `npm run build`. Rollback is a revert.
