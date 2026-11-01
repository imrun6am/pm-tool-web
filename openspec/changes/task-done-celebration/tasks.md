## 1. Preferences

- [x] 1.1 Migration: `users.preferences` JSON; `preferences` array cast on `User`
- [x] 1.2 `PreferenceService` (defaults, get, set, validation of keys)
- [x] 1.3 Profile "Preferences" partial and `PUT /profile/preferences`; `PUT /api/v1/me/preferences`; `preferences` in `AuthUserResource`

## 2. Celebration

- [x] 2.1 `CelebrationService` (messages, all-clear rule, preference check)
- [x] 2.2 `PendingCelebration` (scoped) and task observer completion hook
- [x] 2.3 `ShareCelebration` middleware (flash for redirects, `X-Celebrate` header otherwise), pushed to `web` and `api`
- [x] 2.4 `layouts/toasts`: `celebrate` type, from the session flash and the `#flash-toasts` payload
- [x] 2.5 `engagement.js` fetch hook; sparkle CSS with a reduced-motion guard

## 3. Verification

- [x] 3.1 Feature tests: transitions, archive/reopen silent, all-clear, preference off, flash vs header, API preference update
- [x] 3.2 Browser check: board drag, task page
