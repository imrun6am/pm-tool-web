## Decisions

### Claude picks from a closed list of people
Candidates come from `TaskService::cardAssignees()`, so a suggestion can never be someone the viewer couldn't pick by hand. Claude answers with ids, and any id outside the list is dropped. Open-task counts and leave shown beside a suggestion come from the database, not from Claude. Accepting calls the existing assign endpoint, which validates again.

### Suggestions don't use performance data
The prompt gets recent task titles, open and overdue counts, and upcoming leave as dates. It never gets scores, lead feedback or the leave type. Recent titles are limited to tasks the viewer may see, so a reason can't reveal other teams' work.

### Drafts add to what's already written
A tasksheet editor or the closing notes may already hold text, so drafts are appended at the end and never replace it. In Trix this is one undo step.

### A result draft only claims what the board shows
The evidence is the day's status moves and comments, read from the activity log and comments. Plan items without evidence come back in `unconfirmed` and are shown beside the editor, not inserted, because the work may have happened off the board.

### Retrospective numbers are calculated by the app
Done, open, on-time, late and reworked counts come from `TaskCompletions` and are written into the draft by PHP, so Claude can't misstate them. Only meeting notes shared with everyone are used. Leads can read attendees-only notes, but closing notes are read by everyone on the release, so those notes would leak through the draft.

## Risks

- Task titles and notes are written by team members. They are wrapped as data, and the output is structured JSON.
- Suggestions favor people with history on similar titles. Claude is told to name load and leave, and the person assigning makes the decision.
