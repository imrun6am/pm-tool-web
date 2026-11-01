## Why

A new teammate's first screen is a row of zeroes and "You're all caught up". It doesn't tell them what the app is for or what to do first. A short checklist that ticks itself off as they actually use the app turns that first visit into a guided start. Small improvements to the shared empty-state component make the empty pages feel inviting rather than broken.

## What Changes

- **"Get started" card** at the top of both dashboards (the lead timeline and "My day"). Steps depend on role and are checked off from real data, not clicks:
  - **Add your first task:** you created a task. The button opens quick capture.
  - **Finish a task:** a task assigned to you is Done.
  - **Join the conversation:** you posted a comment.
  - **Log today's tasksheet** (Developer/QA): you have a tasksheet entry. **Plan a release** (leads): a release exists.
  - Viewers get the first three.
- The card shows progress ("2 of 4"), a keyboard tip line (⌘K, C, ?) and a **Dismiss** button. It disappears for good once every step is done or once it's dismissed. Dismissing is stored in `users.preferences.onboarding_dismissed_at`.
- **API:** `GET /api/v1/onboarding` returns `{visible, steps[]}`, and `POST /api/v1/onboarding/dismiss` dismisses it.
- **Friendlier empty states:** `x-empty-state` gets a soft illustrated badge (a layered tinted tile behind the icon) and an optional `tone`. That restyles all 45 empty states at once, with no page edits.

## Capabilities

### New Capabilities
- `onboarding-checklist`: the dashboard getting-started checklist and its API.

## Impact

- **Backend:** `App\Services\OnboardingService`; `OnboardingController` (web dismiss) and `Api\V1\OnboardingController`; routes in `routes/engagement.php` and `routes/api/v1/engagement.php`.
- **Views:**
  - new `partials/onboarding-checklist`, included with one line each in `dashboard.blade.php` and `dashboard-member.blade.php`;
  - `components/empty-state` (visual only, props backwards-compatible).
- **Depends on:** `users.preferences` from `task-done-celebration`, and quick capture (`/tasks/new`).
- **Tests:** steps per role, auto-completion, dismiss on web and API, visibility.
