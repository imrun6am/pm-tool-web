## 1. Tokens
- [x] 1.1 `tailwind.config.js`: blue brand scale, shadows, sidebar tokens, `info`, scan `app/Support`
- [x] 1.2 `App\Support\Palette` (tones, aliases, domains) + unit test
- [x] 1.3 Hard-coded indigo in shared chrome → brand (brand mark, chart defaults, prose links)

## 2. Components
- [x] 2.1 `Navigation`: `tone` per item, `color` from palette, `activeItem()`
- [x] 2.2 `x-page-header` domain icon tile; `x-stat`, `stat-tile`, `x-action-btn`, `x-empty-state` via palette

## 3. Sidebar
- [x] 3.1 Sidebar markup + CSS redesign (tiles, active state, headings, footer hint); rail and drawer intact

## 4. Motion and header
- [x] 4.1 Sliding `nav-pill` via view transitions; eased hover; fading labels on collapse
- [x] 4.2 Frosted top bar, page-header band + section glow, content rise-in; no animation on refresh or under reduced motion

## 5. Verification
- [x] 4.1 `php artisan test`, `pint --test`, browser as admin / team lead / developer, desktop + phone
