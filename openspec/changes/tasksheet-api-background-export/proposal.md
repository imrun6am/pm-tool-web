## Why

`tasksheet-background-pdf-export` moved the web tasksheet PDF into queued, chunked jobs, but left `GET /api/v1/tasksheet/report` rendering synchronously with dompdf, deferred until the desktop client had a polling contract. That endpoint has the same failure mode the web one had: a team-wide quarter, or an "all teams" export from a lead, can exhaust a PHP worker's memory or time limit inside one API request. The desktop client is the stated consumer of `/api/v1`, so it should get the same safe export path, and the API should never render a large PDF inline.

## What Changes

- **Queued exports over the API.** New endpoints under `/api/v1/tasksheet/exports` mirror the web `tasksheet.exports.*` routes: create, list own, status, show, download, retry, delete. They reuse `TasksheetExportService`, `TasksheetExportPolicy` and the `TasksheetExport` model unchanged, so the chunking, the access rules and the feedback rule cannot drift between surfaces.
- **A polling contract.** Create and retry answer `202 Accepted` with the export resource and a `Location` header pointing at the export. The client polls `GET /tasksheet/exports/{id}` (or `GET /tasksheet/exports/status?ids=` for several) until `status` is `ready` or `failed`, then follows `download_url`.
- **A V1 resource.** `TasksheetExportResource` carries the status, progress and error alongside the selection (team, member, date range, row filters) so a client can list exports without a second lookup.
- **The old report endpoint is retired.** `GET /api/v1/tasksheet/report` now answers `410 Gone` with a message and a `Link` header naming `POST /api/v1/tasksheet/exports`. It renders nothing and creates nothing.

## Capabilities

### Modified Capabilities

- `api-resources`: the tasksheet PDF over the API becomes a queued export with create, list, status, download, retry and delete endpoints; the synchronous report endpoint is retired.

<!-- Delta specs are written as ADDED requirements, matching the repo convention: openspec/specs/ has not been synced, so there is no requirement text for a MODIFIED block to amend. -->

## Impact

- **Backend**: new `Api\V1\TasksheetExportController`, `TasksheetExportResource`; `Api\V1\TasksheetController::report` loses its dompdf render. No service, policy, model or migration changes.
- **Routes**: `routes/api/v1/tasksheet.php` gains the `api-v1.tasksheet.exports.*` routes.
- **Docs**: `docs/api-v1.md` Tasksheet section documents the export endpoints and the retired report.
- **Clients**: breaking for anything calling `GET /tasksheet/report` and expecting a PDF. No client exists in this repo yet, so nothing in-tree breaks.

## Non-Goals

- No push notification when an export finishes; v1 clients poll, as for everything else.
- No change to who may export what, to feedback inclusion, to chunking, or to retention.
- No pagination of the exports list: like the web page it returns the requester's 25 most recent, and exports are pruned after 14 days.
