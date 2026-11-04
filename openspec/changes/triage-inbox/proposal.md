## Why

Bugs and requests reach the team through chat and hallway conversations, get lost, and have no owner. Adding them straight to the board fills it with unvetted work. There's no place for "someone should look at this".

## What Changes

- A new Requests page lets anyone send a bug or request, with optional team, priority and type.
- Leads see what's waiting, oldest first, flagged after 48 hours. Accepting creates a task, filed by the person who sent it. Declining needs a reason.
- The team lead, or org-level leads when no team is set, hear about new requests. The sender hears the decision.
- Matching API endpoints.

## Capabilities

### New Capabilities
- `triage-inbox`: sending requests and deciding on them.

### Modified Capabilities
<!-- None: `openspec/specs/` has no base specs yet. -->

## Impact

- **Data**: new `triage_items`.
- **Backend**: `TriageItem`, `TriageItemPolicy`, `TriageService`, three requests, a notification, web and API controllers.
- **Frontend**: new `triage/index`, a sidebar item, inbox kinds.
- **API**: new `/requests` endpoints.
