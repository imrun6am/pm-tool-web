## Context

Status changes reach the database through several controllers and services, including the board's JSON `PATCH /board/tasks/{task}`, whose inline script shows its own "Moved to …" toast. Web writes otherwise answer with redirects and session flashes that become toasts (`layouts/toasts`), including inside live regions.

## Decisions

### 1. Detect in the model, deliver in middleware
- **Detect:** the Task `updated` observer sees a status change from an open status to `done` with an authenticated actor. It asks `CelebrationService::for($task, $actor)` for a message (null when the preference is off) and stores it in the request-scoped `PendingCelebration`.
- **Deliver:** after the controller responds, `ShareCelebration` takes over. A redirect gets `session()->flash('celebrate', …)`. Any other response gets an `X-Celebrate` header (JSON, `rawurlencode`d so it stays ASCII-safe).

No controller, service or board markup changes.

### 2. Client hook
`engagement.js` wraps `window.fetch` once. If a response carries `X-Celebrate`, it dispatches the existing `toast` event with type `celebrate`. Turbo's own fetches pass through unchanged. Their redirect targets carry the session flash instead of the header.

### 3. Message rules
- **All clear:** if the task is assigned to the actor and the actor has no other open, counted, assigned tasks left, the message is the "all clear" one.
- **Otherwise:** a random message from a fixed list, with the task title truncated to 60 characters.

The list lives in `CelebrationService::MESSAGES`, so tests can assert membership.

### 4. Preferences column
Preferences go in one `users.preferences` JSON column, cast to an array, rather than a table per setting. `PreferenceService::DEFAULTS` holds the known keys and their defaults (`celebrations => true`, `onboarding_dismissed_at => null`). Unknown keys are refused.

### 5. Motion
The sparkle is a CSS keyframe on pseudo-elements around the toast's icon. Under `@media (prefers-reduced-motion: reduce)` the animation is `none`. The toast's own enter transition also gets `motion-reduce:transition-none`.

## Non-Goals
- Streaks, points or badges. Personal analytics belong to `member-personal-analytics`.
- Sound.
- Celebrating someone else's completion in front of other people.
