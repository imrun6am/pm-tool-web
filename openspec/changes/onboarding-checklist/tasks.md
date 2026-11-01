## 1. Backend

- [x] 1.1 `OnboardingService` (steps per role, `isVisible`, `dismiss` via `PreferenceService`)
- [x] 1.2 Web dismiss route and controller; API `GET /onboarding`, `POST /onboarding/dismiss`

## 2. UI

- [x] 2.1 `partials/onboarding-checklist` (progress, steps, tips, dismiss), included in both dashboards
- [x] 2.2 `components/empty-state` illustrated badge with `tone`

## 3. Verification

- [x] 3.1 Feature tests: steps per role, auto-done, dismiss on web and API, hidden when complete
- [x] 3.2 Browser check: new developer dashboard, lead dashboard
