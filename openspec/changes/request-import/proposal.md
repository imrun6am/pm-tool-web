## Why

Bug reports and asks usually already exist somewhere: an email, a chat message, a spreadsheet, a screenshot. Retyping each one into the Requests form is slow, and one document often holds several. People want to hand Claude the source, check what it found, and send it in one go.

## What Changes

- **Import on the Requests page.** Upload a file, drop one anywhere on the page, paste a screenshot, or paste text. Same types and limits as calendar import, minus `.ics`: PDF, PNG, JPG, GIF, WebP, DOCX, XLSX, CSV, TXT, MD, up to 20 MB (images 5 MB); pasted text up to 100,000 characters.
- **Background reading.** A queued job sends PDFs and images to Claude whole, and converts everything else to text read in chunks. Progress is saved per chunk, so a retry resumes.
- **One input, many proposals.** Claude proposes as many requests as the input holds: title, details, team, priority and type. Team and type can only come from what the sender may choose.
- **Review, then send.** Each proposal can be edited or unticked. Nothing reaches `triage_items` until the person confirms. A confirmed proposal remembers its request, so it can't be sent twice.
- **Same rules as a hand-made request.** Confirmed rows pass the form's own validation and go through `TriageService::submit`, so team scoping and notifications can't drift.
- **Source link.** Triage items had no attachments, so each one now records the import it came from. The reporter and whoever may decide the request can open the original. The import itself (its other proposals and status) stays private to the person who started it.
- **Admin switch.** New `request_import` feature through `AiGateway`, Sonnet by default, logged and configurable at `/ai/usage`. When off, the import buttons disappear and the normal form still works.
- **API.** The same flow under `/api/v1/requests/imports`.

## Capabilities

### New Capabilities
- `request-import`: creating requests from a file, image or pasted text, with Claude's proposals reviewed before anything is sent.

### Modified Capabilities

<!-- None (no archived specs). -->

## Impact

- New: `triage_imports` table and `triage_items.triage_import_id`, `TriageImport`, `TriageImportPolicy`, `TriageImportService`, `RequestExtractor`, `ExtractTriageRequests`, web and API controllers, `triage/imports/show`, `triage/_import`.
- Changed: `TriageItemRequest` exposes its rules for the review form, `TriageService` gains `teamsFor` and loads the source, `TriageItemPolicy` gains `viewSource`, `TriageItemResource` adds `source_name`, `config/ai.php`, `docs/api-v1.md`.
