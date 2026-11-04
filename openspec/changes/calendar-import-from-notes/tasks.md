## 1. Backend

- [x] 1.1 Migration: `origin_type`, `origin_id`, `reference_date` on `event_imports`
- [x] 1.2 `EventImport`: `INPUT_NOTE`, `origin()`, `isFromNote()`, label and wording helpers
- [x] 1.3 `EventImportService`: `noteText`, `startFromNote`, `written_on` context, snapshot removal
- [x] 1.4 `ClaudeEventExtractor`: written date in the prompt; source name treated as data
- [x] 1.5 `NotePolicy::view`; `EventImportPolicy` respects the source note
- [x] 1.6 Controller actions, feature gate and routes on the `calendar-import` limiter

## 2. Frontend

- [x] 2.1 Meeting note panel with "Find calendar events" and a no-text message
- [x] 2.2 Daily note card action, shown to every reader and leaving the live region
- [x] 2.3 History row and import page name the note and link back to it

## 3. Tests & Verification

- [x] 3.1 Feature tests with the fake extractor: both note types, permissions, empty case, nothing created before confirm
- [x] 3.2 Full PHPUnit suite and Pint pass
- [x] 3.3 Check the flow in the browser

## 4. Found while checking

- [x] 4.1 Review form: unticking one row reset the selected count to 0 and disabled the confirm button (`$root` resolved to the row's own `x-data`). It now uses `$refs`.

## 5. File and paste imports respect the switch

- [x] 5.1 Upload and paste requests refuse what needs Claude when the feature isn't available; `.ics` data is still accepted
- [x] 5.2 `process()` checks the switch before the first Claude call
- [x] 5.3 Import page explains the off state and narrows the file picker to `.ics`
- [x] 5.4 Tests: refused uploads and pastes, `.ics` still works, queued import fails without Claude, retry after switching back on
- [x] 5.5 Accept real `.ics` uploads: their content can be detected as `text/x-vcalendar` (extension `vcs`), which the `mimes` rule refused
