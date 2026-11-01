## Why

Finishing work is the best moment in a task tool, and today it passes without notice: a card changes column and a plain "Moved to Done" toast appears. A small, warm acknowledgement makes the moment feel good and builds the habit of closing tasks, which also keeps the board accurate. It has to stay personal and quiet: no points, no rankings, nothing anyone else sees.

## What Changes

- When **you** move a task from an open status to **Done** (by board drag, the task page, the release tasks panel or the API), you get a **celebration toast**:
  - a short message picked from a varied, friendly set that names the task;
  - a brief sparkle on the check icon.
- When that was **your last open assigned task**, the message says so instead ("All clear — nothing left on your plate").
- Moving a task to Archive, or reopening one, doesn't trigger it.
- The sparkle animation is skipped under `prefers-reduced-motion`. The toast still shows, without motion.
- **Preference:** "Celebrate finished tasks" (on by default) on the Profile page and through `PUT /api/v1/me/preferences`.
  - This adds a general `users.preferences` JSON column, which `onboarding-checklist` also uses.
- **Delivery:**
  - For a redirecting request, the celebration is flashed to the session (`celebrate`) and rendered as a toast by `layouts/toasts`. In-page live regions pick it up through the existing `#flash-toasts` bridge.
  - For a JSON request (board drag, API), it's sent in an `X-Celebrate` response header. A fetch hook in `engagement.js` turns that header into a toast, and desktop clients can use the same header.

## Capabilities

### New Capabilities
- `task-done-celebration`: the completion moment, its message rules, motion and the preference.

## Impact

- **Database:** `users.preferences` (nullable JSON).
- **Backend:**
  - `App\Services\PreferenceService` (typed get/set with defaults);
  - `App\Services\CelebrationService` (decides whether and what to say);
  - `App\Support\PendingCelebration` (request-scoped);
  - `ShareCelebration` middleware, pushed onto the `web` and `api` groups by `EngagementServiceProvider`;
  - the task observer gains a completion hook.
- **Web:**
  - `layouts/toasts` gets a `celebrate` type;
  - `app.css` gets a sparkle keyframe guarded by reduced motion;
  - profile preferences partial and route.
- **API:** `PUT /api/v1/me/preferences`; `AuthUserResource` gains `preferences`.
- **Tests:** transitions, message rules, preference off, flash vs header delivery, API.
