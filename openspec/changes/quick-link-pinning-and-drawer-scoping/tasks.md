## 1. Database & Model

- [x] 1.1 Migration: `quick_link_user` pivot (`quick_link_id`, `user_id`, timestamps, unique pair, both FKs `cascadeOnDelete`)
- [x] 1.2 `QuickLink`: `pinnedBy()` belongsToMany with `withTimestamps()`; `isPinnedBy(User)` using the loaded relation when available, a targeted query otherwise
- [x] 1.3 Run the migration and verify the pivot's unique pair rejects a duplicate pin

## 2. Drawer Query

- [x] 2.1 `QuickLinkService::visibleTo()`: exclude links whose release is completed — links with no release always pass; leave `QuickLink::scopeVisibleTo` untouched so the release-details card keeps listing them (design decision 3)
- [x] 2.2 `QuickLinkService::visibleTo()`: order pinned → private → shared → newest, using a pin-existence expression scoped to the viewer, so both partitions inherit it
- [x] 2.3 Eager-load the viewer's pin state so the drawer does not issue a query per row

## 3. Pin Action

- [x] 3.1 Route: `POST quick-links/{quickLink}/pin` (web) and the API equivalent, both toggling
- [x] 3.2 `QuickLinkPolicy@view` (or an equivalent predicate) reusing the model's visibility rule — authorize the toggle on *visibility*, never on ownership (design decision 5)
- [x] 3.3 `QuickLinkController@pin` (web): toggle the pivot, redirect back with the drawer open
- [x] 3.4 `Api\V1\QuickLinkController@pin`: toggle and return the resulting `is_pinned`

## 4. UI

- [x] 4.1 Pin toggle on each row of "My links" — pinned state visually distinct, not only on hover
- [x] 4.2 Pin toggle on each row of "Shared by others"
- [x] 4.3 Pinned rows marked (icon/badge) so the ordering reads as intentional rather than arbitrary

## 5. API & Docs

- [x] 5.1 `QuickLinkResource`: expose `is_pinned` for the requesting user
- [x] 5.2 `docs/api-v1.md`: add the pin endpoint; document the drawer's ordering and that completed-release links leave the drawer but stay on the release page

## 6. Tests & Verification

- [x] 6.1 Feature tests: pin/unpin own link; pin another user's shared link; the author sees no change from someone else's pin
- [x] 6.2 Feature tests: pinning does not confer edit/delete; pinning a link the user cannot see is refused
- [x] 6.3 Feature tests: drawer order is pinned → private → shared → newest, including a pinned shared link outranking an unpinned private one
- [x] 6.4 Feature tests: completing a release removes its links from the drawer; reopening restores them with the pin intact; unattached links unaffected
- [x] 6.5 Feature test: the completed release's own details page still lists its links (the boundary in decision 3)
- [x] 6.6 API tests: pin toggle returns `is_pinned`; `GET /v1/quick-links` matches the drawer's order and scoping
- [x] 6.7 Run the full test suite and fix regressions
