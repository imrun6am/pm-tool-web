## Context

Task fields gave every task a key, which makes linking tasks easy to do without a search widget. The inbox already routes task notifications through one service.

## Goals / Non-Goals

**Goals:**
- Show stuck work and tell the people who can act on it.
- Keep team scoping: a developer never learns about another team's tasks from a link.

**Non-Goals:**
- Dependency types beyond "waits for", or date scheduling from dependencies.
- Preventing a blocked task from moving. People know their work; the badge informs them.
- A board toggle for the blocked filter. The header row is full; saved views will cover it.

## Decisions

**Blocked has two sources.** A flag covers outside causes and needs a reason. Dependencies cover other tasks. `Task::isBlocked()` combines them, and is false for Done and Archive.

**Lists avoid a query per task.** They load `has_open_blockers` through `withExists`; `isBlocked()` uses it when present and queries only for a single task.

**Links are filtered for the viewer, the blocked state is not.** `loadLinks` drops tasks the viewer can't see, while `isBlocked()` counts them. A developer sees that their task is stuck, not what another team is working on.

**A hidden task reads as missing.** The error for a task the caller can't see matches the one for a task that doesn't exist.

**Loops are checked by walking what the blocker waits for,** one level per query, stopping at tasks already seen.

**Notifications come from the observer,** so every path behaves the same. A changed reason doesn't notify again, since `blocked_at` is kept. "Ready" goes only when nothing else holds the task, including its own flag.

## Risks / Trade-offs

- [Blocking can be left on after the cause clears] → Finishing a task clears it, and any editor can clear it.
