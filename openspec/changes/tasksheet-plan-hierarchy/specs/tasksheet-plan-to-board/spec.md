## MODIFIED Requirements

### Requirement: Add plan tasks to the board
The tasksheet row editor SHALL offer an "Add to board" action beside "Save row". The action SHALL:
1. save the row with the submitted values, exactly as "Save row" would;
2. send the row's plan text, keeping list nesting, and its tickets as context, to the AI, which returns a list of main tasks, each a title with an optional description and a list of subtasks;
3. create one board task per returned main task in the selected release, or in no release, with status `todo`, `assignee_id` set to the row's member, `created_by` set to the acting user, and positions appended after the release's existing tasks;
4. create each returned subtask under its main task, with the same status, assignee and creator, and the main task's release and team.

#### Scenario: Plan becomes assigned tasks
- **WHEN** a lead uses "Add to board" on Devin's row, whose plan lists three unrelated pieces of work, choosing release "Checkout v2.5"
- **THEN** the row is saved, three To Do tasks assigned to Devin are created in "Checkout v2.5", and a success message reports "Added 3 tasks to the board for Devin"

#### Scenario: Main task with subtasks
- **WHEN** the plan has a main task with two nested items under it
- **THEN** one top-level task and two subtasks of it are created, the board shows one card with subtask progress, the task page lists both subtasks, and the message reports "Added 1 task and 2 subtasks"

### Requirement: Duplicate protection
Editing a tasksheet row and using "Add to board" again SHALL never duplicate work. The AI SHALL receive the member's open main tasks in the selected release with their subtasks, and SHALL leave out any plan item that describes the same work, even when it is worded differently or is a continuation. As a safety net, the system SHALL also skip:
- a returned main task whose title matches, ignoring case and whitespace, one of the member's open tasks at either level or an earlier item in the same response;
- a returned subtask whose title matches a subtask of the same parent at any status, its parent, or one of the member's open main tasks.

Done and archived main tasks SHALL NOT count as existing. The same subtask title MAY appear under different parents. The success message SHALL report how many items were skipped as already on the board.

#### Scenario: Pressing twice
- **WHEN** "Add to board" is used twice with the same plan, which has main tasks and subtasks
- **THEN** the second run creates nothing and reports them as already on the board

#### Scenario: Edited plan
- **WHEN** a member rewords their plan's existing lines, adds one new line, and uses "Add to board" again
- **THEN** only the new line becomes a task, and the reworded lines are reported as skipped

#### Scenario: Finished work in the plan again
- **WHEN** the plan repeats work whose only matching main task is done or archived
- **THEN** a new task is created for it, with its subtasks

#### Scenario: Subtask already finished under an open main task
- **WHEN** a subtask is done, its main task is still open, and the same plan is added again
- **THEN** the subtask is not created again

#### Scenario: Card from before subtasks were kept
- **WHEN** a sub-item matches one of the member's open top-level cards
- **THEN** it is skipped and the card stays where it is

### Requirement: Preconditions and failures
The action SHALL fail validation when the plan is empty or the row is a full-day leave. When the AI is not configured, unreachable, returns an error, declines, or returns unreadable output, the row SHALL still be saved, no tasks SHALL be created, and the user SHALL see an error saying no tasks were added. On the API that response SHALL be a 503 with the saved entry and the same message.

#### Scenario: Missing API key
- **WHEN** `ANTHROPIC_API_KEY` is not set and a user uses "Add to board"
- **THEN** the row is saved and the user sees an error that AI isn't configured and no tasks were added

## ADDED Requirements

### Requirement: Attaching to a main task on the board
When a returned main task with subtasks matches an open main task on the board, the system SHALL add only the new subtasks to that task and SHALL NOT create or change the main task. Matches SHALL be sought among the member's own work first, then among the other open main tasks in the same release, or in the row's team when no release is chosen, that the acting user may see. If the match is one of the member's subtasks, the new subtasks SHALL go to its parent, because nesting is one level. A main task without subtasks SHALL NOT match a teammate's task.

#### Scenario: Main task already on the board
- **WHEN** the plan lists a new item under a main task the member already has open
- **THEN** one subtask is added to that task and no new card appears

#### Scenario: Main task owned by a teammate
- **WHEN** the plan lists an item under a main task assigned to a teammate in the same release
- **THEN** the subtask is added to that task, assigned to the row's member, and the main task's assignee does not change

#### Scenario: Main task not on the board
- **WHEN** the plan lists an item under a main task that is not on the board, or only as done or archived
- **THEN** the main task is created too, with the item as its subtask

#### Scenario: Another team's task
- **WHEN** no release is chosen and another team has an open task with the same title
- **THEN** that task is not used, and a new main task is created

### Requirement: Same behaviour on the API
`PUT /api/v1/tasksheet/entries/board` SHALL accept the same fields, apply the same validation and permissions, create the same tasks and return the same message as the web action, along with the saved entry, the created tasks and the counts.

#### Scenario: Same plan on both surfaces
- **WHEN** the same plan is added through the web and through the API
- **THEN** both create the same main tasks and subtasks and report the same message
