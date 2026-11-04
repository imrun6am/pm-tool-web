## Context

A task appears on boards and in every count and report the moment it exists. Requests shouldn't until someone has agreed to do them.

## Goals / Non-Goals

**Goals:**
- One place for incoming work, with a clear decision and an answer for the sender.

**Non-Goals:**
- Email or chat intake. Mattermost comes later and can feed this.
- Merging duplicates, or reopening a declined request.
- Formal response-time reports. The flag is enough for now.

## Decisions

**A separate table, not a task status.** A status would need excluding from dozens of queries, and one miss would leak unvetted work into a report. Accepting creates an ordinary task through `TaskService`, so it behaves like any other.

**The reporter files the task.** `created_by` is who asked, so credit and comment notifications go to them.

**Who decides.** Org-level leads decide anything. A team lead decides their own team's and anything with no team, so unassigned requests can't get stuck.

**A decision is final** and answers 409 afterwards, which also stops two leads creating two tasks.

**A reason is required to decline,** so a request never just disappears.

**Assignee rules match tasks:** active, and on the release's team if there is one.

## Risks / Trade-offs

- [With no team set, every org-level lead is notified] → Accepted; people choosing "Not sure" need someone to see it.
