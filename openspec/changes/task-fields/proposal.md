## Why

Tasks carry a title, status, assignee and dates, and little else. People can't say what matters most, tell a bug from a feature, compare time spent with what was expected, or quote a task quickly in chat or in a pull request.

## What Changes

- Tasks gain an optional priority (urgent, high, medium, low), shared labels and an estimate in hours.
- Every task gets a short key such as `PM-123`, derived from its id. Search finds a task by it.
- Cards show priority, labels and the key. The task page edits priority, labels and estimate.
- The board and `GET /tasks` filter by priority and label, and the board's select all names those filters too.
- The time panel shows the estimate beside the time logged, in red once time goes over it.
- A new page and API let leads manage the label list.

## Capabilities

### New Capabilities
- `task-fields`: priority, labels, estimates and task keys, with their filters.

### Modified Capabilities
<!-- None: `openspec/specs/` has no base specs yet. -->

## Impact

- **Data**: `tasks.priority`, `tasks.estimate_minutes`, new `labels` and `label_task`, with four starter labels.
- **Backend**: `Task`, `Label`, `LabelService`, `TaskService`, `BoardService`, `TaskSearchService`, `TaskRequest`.
- **Frontend**: board filters and cards, task page properties, new `labels/index` page.
- **API**: additive fields, filters and `/labels` endpoints, documented in `docs/api-v1.md`.
