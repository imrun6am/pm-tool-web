## 1. Remove generation

- [x] 1.1 Delete `DailyStandupService`, `GenerateDailyStandups`, `StandupRun` and the `standup:generate` schedule entry
- [x] 1.2 Delete the web and API `standups.store` routes, both controllers, `GenerateStandupRequest`, `DailyStandupPolicy`, `DailyStandupResource` and the tasksheet link

## 2. Meeting notes

- [x] 2.1 Remove the `standup` relation, type and team visibility from `MeetingNote`
- [x] 2.2 Remove the standup branches from the policy, request, service, resource, controllers and views
- [x] 2.3 Remove the standup exclusions from `PeopleAnalyticsService` and `WorkspaceAsk`

## 3. Data

- [x] 3.1 Add the migration that keeps generated notes as ordinary notes and drops `daily_standups`
- [ ] 3.2 Run it on dev MySQL (needs the user's go-ahead)

## 4. Docs and verify

- [x] 4.1 Update `docs/api-v1.md` and the README
- [x] 4.2 Replace `DailyStandupTest` with `DropDailyStandupsMigrationTest`
- [x] 4.3 Run `php artisan test` and `vendor/bin/pint --test`
