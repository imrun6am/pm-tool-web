## Why

The user asked:

> make the login page modern, enhanced in left side write something about this project and in right the login form

Today sign-in is a small card on a grey page, and it says nothing about what PM Tool is or why it's worth opening every day.

## What Changes

- **`layouts/guest` becomes a split screen.**
  - **Left (lg+):** a branded story panel in the new vibrant blue. It has:
    - a headline and a one-paragraph pitch;
    - an abstract release-timeline illustration (HTML/CSS, decorative, `aria-hidden`);
    - four benefit points: release planning with conflict warnings, daily work in one place, progress and gentle nudges, Claude built in;
    - a note that accounts come from a team lead.
  - **Right:** the form.
  - **Below `lg`:** the story collapses to a compact brand header.
- **Login form:**
  - labelled email and password with leading icons;
  - a show/hide password toggle (`aria-pressed`, `aria-controls`);
  - "Keep me signed in";
  - "Forgot password?" beside the password label;
  - inline errors wired with `aria-invalid`/`aria-describedby`;
  - a loading state on submit, with a spinner that respects reduced motion;
  - a note that there's no public sign-up.
- **Forgot, reset and confirm password** use the same field styles and loading state. The session status message becomes a success callout.
- **Behaviour is unchanged:** Breeze `LoginRequest`, rate limiting, the status message after a reset, and no registration link.

## Impact

- **Views:** `layouts/guest`, `auth/{login,forgot-password,reset-password,confirm-password}`, `components/auth-session-status`, `components/icon` (+ `envelope`, `eye`, `eye-slash`).
- **CSS:** `.auth-hero`, `.auth-timeline`.
- **Tests:** `tests/Feature/Auth/*` keep passing.
