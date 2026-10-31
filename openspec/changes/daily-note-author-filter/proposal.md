## Why

The Notes list mixes everyone's shared notes with your own and the ones sent specifically to you, filterable only by date. Once a team is writing daily, "what did Ada post last week" means paging through everybody's notes a day at a time.

## What Changes

- The Notes list gains an **author filter** — narrow it to the notes one person wrote, combinable with the existing single-day and date-range filters and cleared by the same Clear action.
- The author list offers **only people whose notes the viewer can actually see**, so every option returns something and the control never hints that someone has notes the viewer may not read.
- The filter narrows *within* what the viewer may already see. It is not a way to reach anyone's private notes.

## Capabilities

### Modified Capabilities

- `daily-notes`: adds an author filter to the notes list, composing with the existing date filters and scoped by the existing visibility rules.

<!-- Delta specs are written as ADDED requirements, matching the repo convention: main specs under openspec/specs/ have not been synced yet, so there is no synced requirement text for a MODIFIED block to amend. -->

## Impact

- **Backend**: `NoteService::visibleTo()` accepts an `author` filter applied after the visibility scope; a new `authorsVisibleTo()` supplies the dropdown's options; `NoteController@index` reads and passes the filter.
- **UI**: an author select in the notes filter bar.
- **API**: `GET /v1/notes` accepts the same `author` param, keeping the desktop client in step.
- **Docs**: `docs/api-v1.md` notes the new param.
- **Database**: none — `notes.user_id` already carries the author.
- **No breaking changes**: the filter is optional and the unfiltered list is unchanged.

## Non-Goals

- No multi-author selection, no "everyone except me", and no full-text search over note bodies.
- No change to note visibility, recipients, or who may edit or delete a note.
