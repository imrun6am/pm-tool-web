## 1. Plan text
- [x] 1.1 Keep list nesting in `PlanToBoardService::plainText()`, with flat text unchanged

## 2. Extractor
- [x] 2.1 Return `subtasks` from `PlanTaskExtractor`, with schema, prompt and caps
- [x] 2.2 Pass the member's existing subtasks and the other open main tasks to the AI

## 3. Service
- [x] 3.1 Create main tasks and subtasks, attaching to open main tasks on the board
- [x] 3.2 Dedupe across levels and per parent
- [x] 3.3 Add `attempt()` for the shared outcome and message

## 4. Surfaces
- [x] 4.1 Use `attempt()` in the web action and update the drawer hint
- [x] 4.2 Add `PUT /api/v1/tasksheet/entries/board` and document it

## 5. Verification
- [x] 5.1 Feature tests for each scenario, including web and API parity
- [x] 5.2 One real AI call to check the schema and prompt
- [x] 5.3 Pint, the full test suite, and a browser check on a scratch database
