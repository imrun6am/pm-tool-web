## Why

The quick-links drawer is one flat, newest-first list, and it degrades as it fills up:

- The private bookmarks someone opens every day get pushed down by anything they shared more recently — recency is a poor proxy for usefulness.
- A link attached to a release keeps showing long after that release has shipped, so the drawer silently accumulates dead context.
- There is no way to keep the two or three links you actually live in at the top.

## What Changes

- **Pin** — any link a user can see, their own or a teammate's shared one, can be pinned. Pins are **per-viewer**: pinning a shared link changes nothing for anyone else.
- **Drawer ordering** becomes **pinned → private → shared**, newest-first within each band. A deliberate pin outranks the private/shared split.
- **Completed-release scoping** — a link attached to a release drops out of the drawer once that release is completed, and comes back if the release is reopened. Nothing is deleted and the pin survives.
- The **release details "Links" card is deliberately unaffected**: a completed release still lists its links. That page is where they stay contextually relevant, and it is where an author can still reach a link whose release has completed.

## Capabilities

### Modified Capabilities

- `quick-links`: adds per-viewer pinning, a defined drawer ordering, and completed-release scoping of the drawer listing. The release-details links card keeps its current behaviour.

<!-- Delta specs are written as ADDED requirements, matching the repo convention: main specs under openspec/specs/ have not been synced yet, so there is no synced requirement text for a MODIFIED block to amend. -->

## Impact

- **Database**: new `quick_link_user` pivot (`quick_link_id`, `user_id`, timestamps, unique pair) recording who pinned what — mirrors the existing `meeting_note_user` and `event_user` pivots.
- **Backend**: `QuickLink` gains a `pinnedBy()` belongsToMany and `isPinnedBy()`; `QuickLinkService::visibleTo()` excludes links whose release has completed and applies the pinned → private → shared → newest ordering; a pin/unpin action on `QuickLinkController` (web + API) authorized by *visibility*, not ownership; `QuickLinkResource` exposes `is_pinned`.
- **UI**: a pin toggle on every drawer row in both sections, with pinned rows visually marked.
- **Docs**: `docs/api-v1.md` — the quick-links rows gain the pin endpoint and note the drawer's ordering and release scoping.
- **No breaking changes**: nothing is pinned by default, no link is deleted, and the only visible shift for an existing user is the drawer's order.

## Non-Goals

- No pin ordering *among* pinned links (no drag-to-reorder) — pinned links stay newest-first.
- No cap on how many links a user may pin.
- No change to who may create, edit, or delete a link, and none to the limited-role (developer/QA) private-only rule.
