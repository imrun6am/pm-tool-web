## Context

Tasks were hard-deleted, with subtasks and comments removed by the model and everything else by foreign keys. Every read goes through Eloquent, so a global scope can hide trashed tasks everywhere at once.

## Goals / Non-Goals

**Goals:**
- Any delete can be undone for 30 days, with nothing lost.
- A trashed task reads as gone to everyone but admins on the trash page.

**Non-Goals:**
- Soft delete for other models. Comments and time wait with their task.
- A trash for individual comments or time entries.

## Decisions

**`SoftDeletes` on `Task`.** The global scope covers relations, counts and reports. Raw exists rules don't see it, so they gained `whereNull('deleted_at')`.

**Subtasks go together and come back together.** `trashed_with_parent` marks the ones that went with their parent, so a subtask deleted earlier on its own doesn't come back by surprise. Restoring a subtask brings back a trashed parent, or the subtask would have nowhere to show.

**Comments stay until purge.** The model only deletes them on a force delete. Foreign keys take time, history, labels and links at the same point.

**Automatic removal through `model:prune`,** already scheduled daily. `Task` is `Prunable`, which force-deletes through the model.

**Undo restores by id and refreshes.** The board's script doesn't keep removed cards, and a refresh puts them in their right place.

**Admins only.** It matches who can delete.

## Risks / Trade-offs

- [A trashed task keeps its release link and may name an old team] → Restoring re-reads the team from the release.
- [Purge writes a second "Deleted" line to the activity log] → Accepted: the log shows both moments.
- [Task-level database size grows for 30 days] → Small for this team.
