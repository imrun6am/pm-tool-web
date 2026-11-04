## Context

`release_id` and `team_id` already accept `none` through `ReadsTaskFilters::idOrNoneFilter` and `TaskService::whereIdOrNone`. The task form's assignee rules live in `TaskRequest` and `KeepsTasksInViewerTeams`. The searchable dropdown enhances any `<select>` on the page, one controller each. The board's script already updates cards in place with `fetch` and toasts.

## Goals / Non-Goals

**Goals:**
- List unassigned cards, alone or with other filters.
- Assign, change or clear from the card without leaving the board.
- One set of assignee rules, enforced on the server, shared by web and API.

**Non-Goals:**
- `assignee_id=none` on `GET /tasks` or the release task lists.
- Bulk assigning.
- Closing the gap that lets `PUT /tasks/{id}` assign a deactivated user.

## Decisions

**`assignee_id=none` rather than a new parameter.** It's the shape `release_id` and `team_id` use, it stays in the URL through the filter form, and the existing "Clear" and quick-add `back()` keep working. It's an option in the Assignee select rather than a separate toggle, which would take width from the laptop header row.

**A lazily built picker, not a `<select>` per card.** Who can be picked depends on each card's release. Rendering options on every card costs (cards × people) DOM nodes, a query per release, and a controller with a forced layout per card, on exactly the boards that are already crowded. So the card renders a plain button. A click fetches the list, inserts a `<select>` with the app's standard classes, and opens it. When the panel closes, the select is removed. Embedding a release-to-members map in the page was rejected because it would repeat the server's rules in the browser. The cost is one small request per open.

The list is also an API endpoint, because a desktop client needs the same answer.

**`searchable-select.js` gets two small events.** `ss:open` on a select opens its panel, and `ss:close` fires when the panel closes. An inline script can't import the module, and clicking the generated trigger would depend on its markup. With no enhancement, the native select still works.

**`AssignTaskRequest extends TaskRequest`.** It keeps only the parent's `assignee_id` rules, so the team, teammate and unchanged-assignee checks are the same code, and `authorize()` is the same `update` check. Two additions:
- `targetRelease()` becomes `protected` and is overridden to return the task's own release. In the parent, a request can name a different release, because it's also changing the link. Here nothing else changes, so honouring `release_id` would let a client skip the team check.
- `present` and an active-user check, since the rest of the rule set allows a missing key and a deactivated user. An unchanged assignee skips the active check.

**`BoardService::assign()` updates the column directly, not through `TaskService::update()`.** That method defaults a missing status to `todo`, so a partial update would reset the card's column. Going through Eloquent keeps the activity log and the assignment notification.

**`TaskService::assignableUsers()` is split.** The pool moves to a private method. A new `cardAssignees()` adds only the task's current assignee. The existing method also adds subtask assignees, which suits the task page's subtask rows but would show a developer people from other teams, and would offer people who then fail validation.

**The web response carries rendered HTML.** The `x-avatar` tones and initials stay in one place: the server re-renders `partials/board-assignee` and the script swaps the button. It also returns `assignee_id` and `visible`, so the script can decide whether the card stays. The API returns a `TaskResource`, as `move` does.

**The script applies one rule for leaving.** The card stays only if it's still visible and matches the active filter: any assignee with no filter, none under Unassigned, the same person under a named assignee. This also covers "My tasks", and a developer giving away a cross-team task they could only see as its assignee.

**An 18rem column minimum on `lg`.** This supersedes the 12.5rem minimum from `board-task-delete`, which fit all five columns on a laptop but made cards too narrow to read comfortably. The columns stay `flex-1`, so they still share spare width on a wide screen. 18rem sits below the original fixed 19rem so a laptop shows one more part-column. Below `lg` the 17rem width stays. Sideways scrolling already lives in `.board-scroller`, and the page itself doesn't move.

## Risks / Trade-offs

- [A laptop no longer shows all five columns at once] → The next column peeks in, the scrollbar stays in view, and a card's Move menu still reaches any column.

- [The dropdown opens slightly after the click] → The button shows a busy state. The request is one indexed query.
- [The list goes stale while the panel is open] → The server validates again on save, and a refusal shows its message and leaves the card alone.
- [A viewer can assign, because `TaskPolicy::update` allows them] → This follows the existing rule. Project memory already lists viewer permissions as an open question, and this change doesn't decide it.
