## Context

Actions answer with `back()`, and live regions swap in the matching part of the redirected page. From a panel, `back()` means the page behind it, which has no such region, so the panel would fall back to a full page visit.

## Goals / Non-Goals

**Goals:**
- Reuse the existing actions, requests and partials, so the panel can't drift from the task page.
- Keep the panel open through saves and validation errors.

**Non-Goals:**
- A panel on every page that links to a task, such as dashboards and search.
- Editing release, team or phase in the panel. The full page is one click away.
- Subtask editing in the panel, which lists and links them.

## Decisions

**A Turbo Frame holds the panel, with a live region per part.** The panel page is the same markup idea as the task page, and the existing region handling, toasts and validation scrolling all apply.

**A marker sends the answer back to the panel.** The panel adds `_panel` to its forms through the `formdata` event, so partials stay unaware of it, and `KeepTaskPanelOpen` rewrites the redirect to `tasks.panel`. It only accepts digits, and only redirects to an app route, which does its own authorization, so the marker can't open a task out of reach or redirect elsewhere.

**Refresh on close.** The board's in-place script doesn't know about panel edits. A refresh when the panel closes is simple and always right.

**The panel address alone goes to the task page,** since it has no layout of its own.

## Risks / Trade-offs

- [Someone else's change to the task isn't seen while the panel is open] → Same as any open page; reopening shows it.
