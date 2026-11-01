## Context

The synchronous report (`TasksheetController::report`) does three unbounded things in one request: `reportRows()` loads every row, `tasksheet.report` renders them into one HTML string, and dompdf builds one layout tree for it. The admin KPI report system already solves the same "big export, small requests" problem for KPI reviews: a record row tracks progress, a `Bus::batch` runs one small job per unit, a final job writes the file to the `local` disk, and an index page lists reports with Download / Delete. This change reuses that shape.

Access rules that must not change (from `tasksheet-standup-verification-and-reports`):

- Team-wide (or any non-self) export is lead-only; a member may export only their own rows (`member` = self).
- `feedback` is included only when the **requesting** user is a lead.

## Goals / Non-Goals

**Goals:** return from the export click immediately; bound peak memory per job regardless of range or team size; show live progress; keep the access rules exactly; keep the old URL working.

**Non-Goals:** API conversion (follow-up), new formats, emailed delivery.

## Decisions

1. **Chunk by day ranges, not by offset.** The planner runs one `GROUP BY date` count over the export's filtered query, then packs consecutive days into chunks of at most `chunk_rows` rows (default 75, `config/tasksheet.php`). Measured on the dev dataset, dompdf costs about 40 MB plus 0.75 MB per row, so 75 rows keeps a job near 95 MB, under `queue:work`'s default `--memory=128`. The old single render of all 1,383 rows took 126 s and peaked at 2.6 GB. A chunk is a pair of date bounds, so a row saved while the export runs lands in exactly one chunk: there is no `OFFSET` to skip or repeat rows. Day boundaries are also where the report already prints a day heading, so chunks never split a heading from its rows.
2. **An oversized day is split by member.** A single day can exceed the budget (an "all teams" export from a lead). That day's distinct `user_id`s are fetched (bounded by headcount) and sliced into ranges of `chunk_rows`, giving chunks of `(day, day, first_user, last_user)`. A continuation chunk repeats the day heading, which reads correctly at the top of a new page.
3. **One job per chunk, in a batch; then one merge job.** Mirrors `AnalyseKpiMember` → `BuildKpiReport`. Each chunk job loads ≤ one chunk of rows, renders it with the existing `tasksheet.report` template (header only on part 1), and writes `tasksheet-exports/{id}/parts/NNNN.pdf`. Separate jobs mean each has its own timeout and retries, and `queue:work`'s memory limit can recycle the worker between them. The batch does **not** allow failures: a PDF missing a chunk is wrong, so the first permanent failure cancels the batch.
4. **Merge with FPDI.** `setasign/fpdi` (with `setasign/fpdf`) is the maintained, pure-PHP way to import pages from existing PDFs; it parses dompdf's classic xref output without a commercial add-on and needs no system binary (unlike `qpdf`/`gs`), so CI and MAMP behave the same. Merging copies already-laid-out page content streams, so its memory tracks the size of the finished file, not the HTML layout cost that made the old path explode. A one-chunk export skips the merge and moves the part into place.
5. **Progress counts the merge as a step.** `progress = chunks_done / (chunks_total + 1)`, so the bar reaches 100% only when the file is ready, and the label can say "Merging pages" in between.
6. **Failure handling.** Chunk jobs: `tries = 3`, `backoff = [10, 30]`, `timeout = 120`. Merge job: `tries = 2`, `timeout = 300`. A job's `failed()` marks the export failed with a trimmed error message and deletes `tasksheet-exports/{id}/`. A chunk that finishes after the export was failed or deleted removes its own part. Retry re-plans from scratch.
7. **Feedback is snapshotted and re-checked.** `include_feedback` is stored from `TasksheetService::includesFeedbackFor($requester)` at request time. The chunk job also requires the requester still to be a lead, and the download policy refuses a feedback-bearing file to a requester who is no longer a lead.
8. **Exports are private to the requester, leads included.** A lead does not see other people's exports. The file carries the requester's own feedback visibility, and anyone allowed a given export can regenerate it in seconds, so sharing adds risk without adding capability. Admins get no override either: there is nothing an admin could download from someone else's export that they cannot generate themselves. Download also re-checks the export rule, so a demoted lead loses their old team-wide files.
9. **Polling, not push.** The Exports page polls `GET tasksheet/exports/status?ids=…` every 2 s while any listed export is pending or processing, and stops when none are. The endpoint is scoped to the requester's own rows, so foreign ids are silently omitted. No websockets, no new infrastructure.
10. **The old GET redirects instead of rendering.** It applies the same authorization (403 parity), then redirects to the Exports page with the form prefilled. A GET does not create records, so KPI-spreadsheet evidence links stay safe to click.
11. **Limits and retention.** At most 3 running exports per user, so one person cannot flood the shared worker. Exports are `Prunable` after 14 days (`model:prune`, scheduled daily), deleting their files in `pruning()`.

## Risks / Trade-offs

- **Fonts per part.** Each part embeds its own DejaVu subset, so a merged file is larger than a single-pass render (1,383 rows: 26 MB from 21 parts against 14 MB from 10). `chunk_rows` trades this against per-job memory.
- **Merge memory still grows with output size.** FPDF buffers the output document. That is bounded by the file the user downloads, far below dompdf's layout cost: merging the 26 MB file above peaked at 62 MB. A multi-hundred-MB export would need a streaming merger (`qpdf`).
- **Queue timeouts vs `retry_after`.** The database queue's `retry_after` (90 s default) is below the merge timeout. With the user's single worker this is harmless (a reserved job is not picked twice). With several workers, set `DB_QUEUE_RETRY_AFTER` above 300, as the KPI jobs already require.
