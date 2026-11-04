## Context

`ReleaseRiskGenerator` already computes the facts and has the same three statuses. A status update is what a person decides, so it gets its own table rather than reusing `AiInsight`.

## Goals / Non-Goals

**Goals:** a kept, visible record of how leads see a release, with little effort to write.

**Non-Goals:** editing or deleting updates (post a new one), scheduled reminders, posting to Mattermost (Phase 4).

## Decisions

- **Append-only.** History that can be rewritten isn't history. The note is limited to 2,000 characters.
- **Notify only on a change into at risk or off track.** A repeat "still at risk" and routine "on track" notes would teach people to ignore the inbox. Going from at risk to off track does notify. Recipients are the team's lead and active org-level leads, never the author.
- **Developers and QA read, but aren't notified.** They get the card on the release page. Notifying everyone would be noise.
- **Claude drafts only.** The draft fills the form in the browser. An unexpected status from Claude falls back to `at_risk`, the same as the risk brief.
- **The latest badge lives in the side column, above Claude's panels**, in a live region so posting updates in place.
- **Status colors with an icon and a label**, never color alone.

## Risks / Trade-offs

- A status can go stale. The card shows when it was posted, so the age is visible.
- Deleting a release deletes its updates. Removing a user keeps the update with no author, shown as "Former user".
