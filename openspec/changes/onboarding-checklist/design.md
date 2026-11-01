## Decisions

### 1. Derived, not tracked
Each step is an `exists()` query on data the app already records (tasks, comments, tasksheet entries, releases). There are no event hooks and no "seen" flags beyond the single dismissal timestamp. Existing users who already use the app see their steps already done, and the card hides itself when all of them are.

### 2. Service returns domain values
`OnboardingService::checklist(User)` returns a collection of steps, each with `key`, `label`, `description`, `done` and `action` (a route name, or `quick-capture`). `isVisible(User)` returns false when the user has dismissed the card or every step is done. The partial and the API resource both read the same values.

### 3. Placement
The partial is a single `@include('partials.onboarding-checklist')` at the top of each dashboard's content. It renders nothing when the card isn't visible, so the dashboards stay owned by their changes (`member-personal-analytics` is rewriting "My day" right now).

### 4. Empty-state look
The icon sits on a 56px rounded tile with a brand-tinted gradient and a faint offset second tile behind it. Tone variants (`brand` default, `emerald`, `amber`, `rose`, `sky`) are written out in full so Tailwind keeps them. The props `icon`, `title` and `description`, and the slot, are unchanged.

## Non-Goals
- Product tours, tooltips walking through the UI, or progress synced between devices beyond what the data implies.
- Per-page bespoke illustrations.
