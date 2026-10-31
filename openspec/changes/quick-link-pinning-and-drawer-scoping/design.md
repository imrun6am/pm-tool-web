## Context

A `QuickLink` is a saved bookmark with a `label`, `url`, an optional `release_id`, and a `visibility` of `private` | `shared`. It surfaces in two distinct places, reached by two *different* code paths — a distinction this change depends on:

- **The drawer** (a slide-over on every page, plus `GET /v1/quick-links`) renders `QuickLinkService::partitionedFor()`, which calls the service's own `visibleTo()` and then partitions by author into "My links" and "Shared by others".
- **The release-details links card** (web and API) queries `$release->quickLinks()->visibleTo($user)` directly — the *model* scope `QuickLink::scopeVisibleTo`, not the service method.

The service method is used by nothing but `partitionedFor()`. So the drawer and the release card can be given different listing rules without either one special-casing the other.

Two existing rules stay in force: `QuickLinkPolicy` grants edit/delete to the author alone (leads included), and limited roles (developer/QA) are private-only — they never see others' shared links, which the model scope enforces.

## Goals / Non-Goals

**Goals:**
- Let a user pin any link they can see, without that pin leaking to other viewers.
- Give the drawer a deliberate, documented order instead of raw recency.
- Keep the drawer current by dropping links whose release has finished, reversibly.
- Leave the release-details card, the policy, and the role rules exactly as they are.

**Non-Goals:**
- No manual ordering within the pinned band, no pin limit.
- No change to creation, editing, deletion, or the limited-role restriction.
- No new visibility level, and no notion of a "team pin" (a pin everyone sees).

## Decisions

1. **Pinning is a per-viewer pivot (`quick_link_user`), not a `pinned_at` column.** A shared link is *one row seen by many people*, so a column would make one person's pin everyone's — the wrong semantics for the "Shared by others" section, which is exactly where pinning is most useful. The pivot mirrors `meeting_note_user` and `event_user`, both already in the schema, and carries `timestamps()` so "recently pinned" is available later if it is ever wanted.

2. **Order is pinned → private → shared, newest-first within each band.** Applied in the service's `visibleTo()` before the partition, so both drawer sections inherit it: "My links" reads pinned, then private, then shared; "Shared by others" is all shared, so it reads pinned, then newest. An explicit pin outranks the private/shared split because pinning is a deliberate act and recency is not — a pinned shared link sits above an unpinned private one, by choice.

3. **Completed-release scoping lives in the service, not the model scope.** This is the load-bearing decision. The model's `scopeVisibleTo` answers *"may this user see this link at all?"* — an access question, and completing a release is not a revocation of access. The service answers *"what belongs in the drawer right now?"* — a relevance question. Putting the filter in the model scope would empty the links card on every completed release's own page, which is the one place those links still matter. Keeping the two questions in separate layers is what lets the drawer declutter without the release page losing its record.

4. **Hidden, never deleted — and reversible.** Completion sets `releases.completed_at`; `ReleaseService::reopen()` clears it. So a link attached to a completed release simply stops matching the drawer query, and reappears intact — pin included — the moment the release is reopened.

   The accepted cost: while the release stays completed, the author cannot reach that link *from the drawer* to edit or delete it. Decision 3 is what keeps this from being a trap — the link is still listed, and still actionable, on the release's own details page.

5. **Pin is authorized by visibility, not ownership.** `QuickLinkPolicy` deliberately restricts `update`/`delete` to the author; pinning must not inherit that, or the shared section could never be pinned. The pin action authorizes on "can this user see this link" — the same predicate the model scope already encodes — so a user can pin what they can read and nothing else. Pinning is not a write to the link; it is a write to the *user's* relationship with it.

6. **Toggle over separate pin/unpin verbs.** One `POST /quick-links/{link}/pin` that flips the pivot row, matching how the drawer's other row actions already post and redirect back. The API returns the resulting `is_pinned` so a client never has to guess which way the toggle went.

7. **Limited roles need no special handling.** Pins ride on top of the existing `visibleTo` model scope, which already narrows developers and QA to their own links. A limited-role user therefore can only ever pin their own links — an emergent consequence of the existing rule, not a second rule to maintain.

## Risks / Trade-offs

- [A pinned link whose release completes still vanishes from the drawer] → Correct but potentially surprising: relevance scoping outranks pinning. The pin is preserved and returns with the release, and the link stays reachable on the release page meanwhile.
- [Ordering by a pin-existence expression on every drawer render] → The drawer is a small, per-user list and the pivot is indexed on its unique pair; negligible.
- [Pivot rows accumulate for links that are later deleted] → The FK cascades on delete, same as the other pivots.
- [Two listing rules for one model could drift] → Mitigated by decision 3 naming the boundary explicitly (access vs. relevance) and by tests asserting both surfaces: the drawer hides a completed release's link while the release page still shows it.
