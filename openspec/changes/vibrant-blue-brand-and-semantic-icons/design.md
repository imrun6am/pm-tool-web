## Decisions

### 1. Retune `brand-*`, don't rename it
More than 250 `brand-*` classes are already in views. Changing the scale's values recolors the whole app at once and keeps every page edit-free. Text-bearing steps were contrast-checked:

| step | hex | on white | on brand-50 |
|---|---|---|---|
| 600 | `#0b63f6` | 5.09 | 4.67 |
| 700 | `#0a50d0` | 6.6 | 6.0 |

`500` (`#2f7cf8`) is for fills, gradients and focus rings only, never for text.

### 2. Palette as a PHP token map
A PHP class (rather than CSS variables) because the tones are chosen server-side in Blade and `Navigation`. Full class strings live in `app/Support/Palette.php`, and Tailwind scans that file. Unknown tones fall back to `neutral`, so a typo never renders an uncolored icon.

### 3. Page-header icon from the active nav item
`Navigation::activeItem()` returns the first catalogue item whose route patterns match. Visibility isn't checked, because this is decoration on a page the user already reached. Pages outside the menu (profile, notifications) can pass `icon` and `tone`. Passing `:icon="false"` turns the tile off.

## Non-Goals
- Dark mode.
- Recoloring role badges or user-chosen project/team colors. Those are identity colors, not semantics.
