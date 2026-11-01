## Context

The web change (`tasksheet-background-pdf-export`) built the whole export pipeline: `TasksheetExportService` plans day-range chunks and dispatches a `Bus::batch` of `RenderTasksheetExportChunk` jobs, `MergeTasksheetExport` merges the parts with FPDI, `TasksheetExportPolicy` keeps every export private to its requester, and the web `TasksheetExportController` exposes index / store / status / download / retry / destroy. The API's `TasksheetController::report` still loads every row and runs dompdf in the request.

This change is only the HTTP surface. Everything below the controller is reused unchanged.

## Goals / Non-Goals

**Goals:** the API can no longer render a large PDF inline; the API's export endpoints match the web routes one for one; the same access rules apply with the same status codes; a desktop client has a simple, documented polling loop.

**Non-Goals:** push delivery, new formats, any change to the service, policy or model.

## Decisions

1. **Same routes as the web, under `/api/v1`.** `GET tasksheet/exports` (list own), `POST tasksheet/exports` (create), `GET tasksheet/exports/status?ids=` (several statuses), `GET tasksheet/exports/{export}/download`, `POST tasksheet/exports/{export}/retry`, `DELETE tasksheet/exports/{export}`. One addition, `GET tasksheet/exports/{export}`, gives the `Location` header a real target and lets a client poll a single export without building an `ids` list. It is guarded by the policy's existing `view` ability.
2. **`202 Accepted` for create and retry.** The export is recorded, but the PDF does not exist yet. The body is the export resource; `Location` points at the show endpoint. On a `sync` queue the export may already be `ready` in that same response, and a client should treat that as a normal first poll result.
3. **The old GET answers `410 Gone`.** It does not create an export. A GET must stay safe to retry and prefetch, and creating a queued job from one would let an HTTP retry or a link preview start real work. The web kept its old URL as a redirect to a prefilled form because KPI spreadsheets link to it; nothing links to the API URL, and no API client exists in this repo yet, so a clear refusal costs nothing. The body says what to call instead and a `Link: <…/tasksheet/exports>; rel="successor-version"` header names it. It answers before validation or authorization, since it no longer does anything either would protect.
4. **Refusals use the API's existing codes.** The running-export cap and "only a failed export can be retried" are domain rules, so they are `422` with a message, the same as a project that cannot be deleted (the docs reserve `429` for throttling with `Retry-After`). A member asking for a team or another member is `403` from `TasksheetExportPolicy@create`. Someone else's export is `403` on show, download, retry and delete, matching the web, and is silently omitted from `status` and the list. A download before the file is ready is `404`, as on the web.
5. **The resource is the polling payload.** `TasksheetExportResource` holds the same status fields the web `status` JSON returns (`status`, `status_label`, `progress`, `chunks_done`, `chunks_total`, `rows_total`, `merging`, `error`, `completed_at`), with `download_url` pointing at the API download route, plus the selection (team, member, `date_from`, `date_to`, `filters`, `filter_labels`, `includes_feedback`) and timestamps. The list and `status` return the same resource, so a client renders one shape everywhere. The parity test pins the shared status fields to the web's values.
6. **The list carries capacity.** `GET tasksheet/exports` adds `meta.has_capacity` and `meta.max_running`, so a client can disable its Export button instead of learning about the cap from a `422`.
7. **`ids` parsing matches the web.** `status` accepts `ids=1,2` or `ids[]=1&ids[]=2`, drops non-numeric values, de-duplicates, and caps at 50. The parsing is a few lines in the API controller rather than a service change, because the task keeps the service unchanged and the rule is about request shape, not domain logic.

## Risks / Trade-offs

- **Breaking change to the report URL.** Acceptable only because no client exists yet. A client written against the old docs gets a `410` with a message naming the replacement, not a silent wrong answer.
- **Polling load.** A client that polls tightly adds requests. The docs ask for a 2 s interval while anything is running and stopping once nothing is, the same cadence as the web page; the general 120/minute throttle bounds a misbehaving client.
