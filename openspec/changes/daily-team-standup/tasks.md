## 1. Data

- [x] 1.1 Add the migration: `daily_standups`, nullable `meeting_notes.created_by`
- [x] 1.2 Add `DailyStandup`, the `standup` type, the note's `standup` relation and team-based visibility

## 2. Generating

- [x] 2.1 Add `DailyStandupService`: content, fingerprint, attendees, AI block, deletion rule
- [x] 2.2 Add the `standup:generate` command and schedule it
- [x] 2.3 Keep generated notes out of people analytics and Ask AI

## 3. Editing and access

- [x] 3.1 Add `DailyStandupPolicy`; let leads and team members edit generated notes
- [x] 3.2 Lock identifying fields and record hand edits of attendees in `MeetingNoteService`
- [x] 3.3 Allow an empty body on a generated note in `MeetingNoteRequest`

## 4. Pages and API

- [x] 4.1 Show the listing on the note page, list card and search; trim the edit form
- [x] 4.2 Add the web generate action and the tasksheet link
- [x] 4.3 Add the `standup` block, `POST /standups` and `docs/api-v1.md`

## 5. Verify

- [x] 5.1 Add `DailyStandupTest`
- [x] 5.2 Run it for real on a scratch database and check it in a browser
- [x] 5.3 Run `php artisan test` and `vendor/bin/pint --test`
