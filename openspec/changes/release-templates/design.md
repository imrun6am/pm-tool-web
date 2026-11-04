## Goals / Non-Goals

**Goals:** start a release with its usual tasks, dated from its plan.

**Non-Goals:** subtasks or assignees in templates, since people vary per release; saving an existing release as a template; copying phases or dates.

## Decisions

**Applying goes through `TaskService`,** so tasks get the release's team and everything else a normal task does.

**Due dates come from the phase's end date,** the only date a template can know. Tasks with no phase stay undated.

**Applying twice is harmless.** Titles the release already has are skipped, so a template can be applied after a release has started, or a new template task added later.

**Removing a template keeps tasks already made.** There's no link back from a task to its template.

**Leads only,** matching who plans releases.
