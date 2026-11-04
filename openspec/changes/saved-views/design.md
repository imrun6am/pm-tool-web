## Context

Both task pages keep their filters in the URL, so a view is just a stored set of query parameters.

## Goals / Non-Goals

**Goals:**
- One click to a saved set of filters, with a shared list for teams.

**Non-Goals:**
- Remembering the last filters automatically. A bare URL also means "cleared", so a redirect to the last filters would break clearing and "My tasks". Saved views cover the need without that surprise.
- Renaming. Saving under the same name replaces a view, and removing is one click.
- Views scoped to a team.

## Decisions

**Filters are kept to a per-page list.** Anything else is dropped on save, so a view can't carry stray parameters into a URL.

**Applying a view is a normal link.** The server applies the filters and team scoping as usual, so a shared view can't show a developer anything they couldn't already reach.

**Sharing is for leads,** to keep the shared list short and trusted. A shared view belongs to everyone, so any lead can remove it; a personal view stays with its owner.

## Risks / Trade-offs

- [A view naming a deleted team or release shows an empty list] → The filter shows blank and can be cleared, and the view removed.
