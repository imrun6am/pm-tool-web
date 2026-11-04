## Why

The board shows where cards are, not whether work is flowing. Too much in review or a card stuck for two weeks looks the same as a healthy board. Status history now makes this measurable.

## What Changes

- Leads can set a WIP limit per column for a team. The column shows `3 / 5` and turns red when over, and moving into a full column warns. It never blocks.
- Cards that have sat in a status for 3 days or more show how long, turning amber at 7 and red at 14.
- Cards can be grouped within columns by person or by priority. The choice is in the URL, so saved views can keep it.

## Capabilities

### New Capabilities
- `board-flow-controls`: WIP limits, card age and grouping on the board.

### Modified Capabilities
<!-- None: `openspec/specs/` has no base specs yet. -->

## Impact

- **Data**: new `wip_limits`.
- **Backend**: `WipLimit`, `BoardService`, `Task::daysInStatus`, request, controllers.
- **Frontend**: board header and summary row, card age badge, board script.
- **API**: new `PUT /board/wip-limits`; column `limit` and task `days_in_status` on `GET /board`.
