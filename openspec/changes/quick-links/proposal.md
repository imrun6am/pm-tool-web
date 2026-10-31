## Why

People keep the links they need all day — staging URLs, CI dashboards, docs, ticket boards — in browser bookmarks or pinned chat messages, invisible to the tool and to teammates. A quick-links drawer puts them one click away on every page, and lets useful links be shared with the whole team while personal ones stay private.

## What Changes

- New **quick links**: a saved link with a label, URL, and a `private` or `shared` visibility (same model as daily notes' visibility).
- A **side drawer** available on every page (toggle button in the navbar): slides in from the right, lists the user's own links plus everyone's shared links, opens targets in a new tab.
- Links can be **added, edited, and deleted from inside the drawer**; only the author manages their links. After a save the drawer re-opens so the flow stays in place.
- A link MAY be **attached to a release**: the release details page gains a "Links" section in the main content area (not the sidebar) listing that release's links (visibility rules still apply) with an inline add form; release-linked links carry a release badge in the drawer.
- Available to **every authenticated role** — but **developers/QA are private-only**: they can add only private links and see only their own links; shared links (theirs to add, others' to see) don't exist for them. Sharing is for full-access roles.

## Capabilities

### New Capabilities
- `quick-links`: Creating, listing, editing, and deleting quick links; private/shared visibility rules; the ever-present side drawer UI.

### Modified Capabilities

<!-- None. No existing capability's requirements change. -->

## Impact

- **Database**: new `quick_links` table (`user_id`, `release_id` nullable, `label`, `url`, `visibility`).
- **Backend**: `QuickLink` model, `QuickLinkController` (store/update/destroy), `QuickLinkRequest`, `QuickLinkPolicy`; a view composer supplying drawer data to every page.
- **Routes**: three `quick-links` routes in the collaboration section of `routes/web.php`.
- **UI**: drawer partial included from the app layout + navbar toggle button.
- **No breaking changes**; no existing pages alter behavior.
