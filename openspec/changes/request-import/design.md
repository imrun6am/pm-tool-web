## Context

Calendar import already solves upload → background read → review → confirm. Requests need the same shape with a different result: triage items instead of events. Calendar import predates `AiGateway` and calls the SDK directly, so its usage isn't logged. This feature must go through the gateway.

## Goals / Non-Goals

**Goals:**
- Hand over a file, screenshot or text and get one or more editable proposals.
- A request from an import is indistinguishable from a hand-made one in permissions and notifications.

**Non-Goals:**
- A general attachment system for triage items or tasks.
- Carrying the source link onto the task when a request is accepted.
- Several files in one import.
- Automatic clean-up of old imports (calendar imports don't have it either).

## Decisions

**Its own table, sharing the readers.** `triage_imports` mirrors `event_imports` without the calendar columns. `DocumentTextConverter`, `TextChunker` and `EventImportService::pastedText` are reused rather than copied. Merging the two import tables would tie two unrelated review flows together for little gain.

**Through `AiGateway` with inline files.** The gateway takes content blocks, so PDFs and images go as base64, as `DocumentInsightGenerator` does. A 20 MB PDF is about 27 MB encoded, under the API's request limit. The worker's PHP memory limit is raised for those files only, because the encoded copies don't fit in the default 128 MB alongside the framework.

**One code path for sending.** `confirm()` calls `TriageService::submit` for each row. `TriageItemRequest::fields()` provides the rules for both forms, so a Developer can't send to another team by editing the review form.

**Claude can only suggest what the sender may choose.** The prompt lists only the sender's teams, and the answer's team and type are matched by name against those lists. Anything else becomes "Not sure". Team is re-validated at confirm, in case membership changed.

**Private import, shared source.** `TriageImportPolicy` allows only the person who started the import, including against admins. The original file is opened through a separate route on the request, allowed to the reporter and to whoever may decide it. The review page says so before anything is sent. Deleting an import removes the file and the link; requests already sent stay.

**Review stays open when the feature is off.** Reading the stored proposals costs nothing, so switching Claude off stops new imports and retries, not review of finished ones.

**Failures.** The queue retries what might recover. Non-retryable answers and a 400, 401 or 403 from the API fail at once. Anything unexpected shows a generic message, not the exception text.

## Risks / Trade-offs

- [The whole file is visible to deciders, even if only part of it became a request] → Stated on the review page; deleting the import removes the file.
- [A big import could flood a lead's inbox] → `confirm()` runs inside `NotificationService::batch()`, so a decider gets one grouped row. One request still gets the ordinary notice.
- [Proposals over the cap of 100 are dropped] → The review page says how many were left out and suggests splitting the file. The cap keeps the form inside PHP's `max_input_vars`.
