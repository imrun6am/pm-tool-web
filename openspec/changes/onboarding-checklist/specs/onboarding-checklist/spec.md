## ADDED Requirements

### Requirement: Role-aware getting-started checklist
Each dashboard SHALL show a "Get started" card listing steps for the viewer's role. Each step SHALL be marked done from the viewer's actual data:
- "Add your first task": created a task.
- "Finish a task": an assigned task is Done.
- "Join the conversation": posted a comment.
- "Log today's tasksheet": has a tasksheet entry. Developer and QA only.
- "Plan a release": any release exists. Leads only.

#### Scenario: New developer
- **WHEN** a developer with no activity opens their dashboard
- **THEN** the card lists four steps, none done, with progress "0 of 4"

#### Scenario: Step completes itself
- **WHEN** that developer adds a task and returns to the dashboard
- **THEN** "Add your first task" is shown as done and progress reads "1 of 4"

### Requirement: The card goes away
The card SHALL NOT be shown once every step is done, or after the viewer dismisses it. A dismissal SHALL persist across sessions and devices.

#### Scenario: Dismiss
- **WHEN** a user clicks Dismiss on the card
- **THEN** the card is not shown on later visits

#### Scenario: Experienced user
- **WHEN** a user who has done every step opens a dashboard
- **THEN** no card is rendered

### Requirement: Onboarding over the API
The API SHALL return the caller's checklist (`visible`, plus steps with `key`, `label`, `done`) and SHALL allow dismissing it.

#### Scenario: Desktop dismiss
- **WHEN** a client posts to `/api/v1/onboarding/dismiss`
- **THEN** `GET /api/v1/onboarding` returns `visible: false`, and the web dashboard hides the card too
