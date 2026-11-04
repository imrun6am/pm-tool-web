## 1. Backend

- [x] 1.1 Add `AiTransport::converse`, `AiGateway::converse` and the `assistant` feature key
- [x] 1.2 Add the migrations, models and `AssistantActionPolicy`
- [x] 1.3 Add `FormRules`, the tool contract and the read tools
- [x] 1.4 Add the action tools (event, task, tasksheet, update, assign, comment, time, note, release status)
- [x] 1.5 Add `AssistantAgent`, the controller and routes

## 2. Frontend

- [x] 2.1 Add the launcher, its lazy loader and the Turbo-permanent mount
- [x] 2.2 Add the panel, draft cards, streaming and all states
- [x] 2.3 Add the shortcut to the shortcut sheet

## 3. Verification

- [x] 3.1 Add feature tests for permissions and confirmation per action
- [x] 3.2 Make real API calls to confirm the tool schemas are accepted
- [x] 3.3 Check in a real browser as a Developer and a lead on a scratch database
- [x] 3.4 Run `php artisan test` and `vendor/bin/pint --test`
