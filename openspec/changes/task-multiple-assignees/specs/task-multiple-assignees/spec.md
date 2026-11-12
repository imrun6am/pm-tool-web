## ADDED Requirements

### Requirement: A task can have several assignees
A task, top-level or subtask, SHALL hold any number of assignees from zero to ten. One of them SHALL be the lead assignee, exposed as `assignee_id`: the first person chosen, kept while they stay assigned, and otherwise the next remaining assignee in the order they were added. `assignee_id` SHALL be null exactly when the task has no assignees.

#### Scenario: Two people on one task
- **WHEN** a lead saves a task with assignees Ana and Ben
- **THEN** the task has both as assignees and Ana as the lead

#### Scenario: The lead is removed
- **WHEN** the assignees of a task led by Ana become just Ben
- **THEN** Ben is the lead

#### Scenario: Subtasks too
- **WHEN** a user saves a subtask with two assignees
- **THEN** the subtask has both

### Requirement: Existing assignments carry over
The migration SHALL copy every task's `assignee_id`, trashed tasks included, into the assignee list, and SHALL leave `assignee_id` unchanged.

#### Scenario: Migrating
- **WHEN** the migration runs on a database where a task is assigned to Ana
- **THEN** that task's assignees are exactly Ana and its lead is Ana

### Requirement: Assigning on the web and through the API
The task form, the task panel and the board card SHALL let a user choose several assignees. The API SHALL accept `assignee_ids` as the full list on task create, update, subtask create and the board assign endpoint, and SHALL return `assignee_ids` and `assignees` on tasks next to `assignee_id` and `assignee`. When only `assignee_id` is sent, null SHALL clear every assignee, the current lead's id SHALL change nothing, and any other id SHALL make that person the only assignee. When both are sent, `assignee_ids` SHALL win and `assignee_id` SHALL choose the lead if it's in the list.

#### Scenario: API with assignee_ids
- **WHEN** a client sends `PATCH /api/v1/tasks/{id}` with `assignee_ids: [ana, ben]`
- **THEN** the response has `assignee_ids` with both and `assignee_id` equal to the lead

#### Scenario: An older client re-saves a task
- **WHEN** a client sends the task's current `assignee_id` with a new title, and no `assignee_ids`
- **THEN** the title changes and every assignee stays

#### Scenario: An older client reassigns
- **WHEN** a client sends `assignee_id` for someone who is not the lead
- **THEN** that person becomes the only assignee

#### Scenario: Web and API agree
- **WHEN** the same assignee list is saved through the web form and through the API
- **THEN** the task ends up with the same assignees and lead

#### Scenario: Board card
- **WHEN** a user adds a second person to a card from the board
- **THEN** the card shows both avatars and the first assignee stays

### Requirement: Assignment rules apply to every added person
Every newly added assignee SHALL pass the rules a single assignee passes today: the person exists; on a task linked to a release, the person is on the release's team; a Developer or QA may add only themselves or a current teammate; and on the board and in bulk edits, the person's account is active. People already assigned SHALL always pass, so they can stay. A task SHALL NOT take more than ten assignees.

#### Scenario: Developer adds someone from another team
- **WHEN** a developer adds a person who isn't their teammate
- **THEN** the system answers 422 and the assignees don't change

#### Scenario: Keeping someone who moved team
- **WHEN** a developer saves a task whose existing co-assignee has since left the team
- **THEN** the save passes and that person stays assigned

#### Scenario: Release team
- **WHEN** a lead adds someone who isn't on the release's team to a release task
- **THEN** the system answers 422

### Requirement: Filters and My tasks match any assignee
`assignee_id=X` on the board, the task list, a release's tasks and the API SHALL match a task when X is any of its assignees. My tasks and the Developer/QA default view SHALL show every task the viewer is assigned to, lead or not. `assignee_id=none` SHALL match tasks with no assignees.

#### Scenario: Co-assignee's My tasks
- **WHEN** Ben, a co-assignee who isn't the lead, opens the board on My tasks
- **THEN** the shared card is listed

#### Scenario: Unassigned
- **WHEN** a user filters on `assignee_id=none`
- **THEN** tasks with any assignee are left out

### Requirement: Developers and QA see tasks they are assigned to
A Developer or QA SHALL see a task from another team when they are any of its assignees, as they do today when they are its single assignee.

#### Scenario: Co-assigned on another team's task
- **WHEN** a QA user from team B is added to a team A task
- **THEN** the QA user can open the task and finds it in My tasks

### Requirement: Each new assignee is notified once
Saving assignees SHALL notify each person added, once, unless they added themselves. It SHALL tell each person removed that they were taken off. People who stay assigned SHALL NOT be notified about the assignment, whatever else the save changed. Every other task notice SHALL go to all assignees except the actor.

#### Scenario: Adding a second person
- **WHEN** a lead adds Ben to a task already assigned to Ana
- **THEN** Ben gets one assigned notice and Ana gets none

#### Scenario: Re-saving
- **WHEN** the task is saved again with the same assignees
- **THEN** nobody gets an assigned notice

#### Scenario: Removing
- **WHEN** Ben is removed
- **THEN** Ben is told he was taken off and Ana hears nothing

#### Scenario: A comment
- **WHEN** someone comments on a task with two assignees
- **THEN** both assignees are notified

### Requirement: Analytics credit every assignee without double-counting totals
Per-person counts SHALL credit each assignee with the whole task. Per-person estimated hours SHALL split a task's estimate evenly between its assignees. Team, release and workspace totals SHALL count each task once.

#### Scenario: Shared completion
- **WHEN** a task with Ana and Ben is completed
- **THEN** both Ana's and Ben's completed counts include it, and the team's completed count goes up by one

#### Scenario: Shared estimate
- **WHEN** an open 8-hour task has two assignees
- **THEN** each one's planned load includes 4 hours
