## 1. Shared Pieces

- [x] 1.1 `x-stat` KPI cell component and the `xl` avatar size

## 2. Settings (Profile)

- [x] 2.1 Full-width two-column layout with a sticky section nav (scroll-spy, `aria-current="true"`) and mobile tabs
- [x] 2.2 Account overview card: avatar, name, email, role, teams, member since
- [x] 2.3 Profile and password sections at full width (two fields per row), plus the danger zone; error bags, the "Saved." flash and the delete modal unchanged

## 3. Dashboard-Style Pages

- [ ] 3.1 Performance index and performance member: the KPI rows become `stat-strip` + `x-stat`; the member page gets breadcrumbs and the shared header
- [ ] 3.2 Tasksheet, team page and project page: the KPI rows become `stat-strip` + `x-stat`
- [ ] 3.3 Calendar: standard header with month navigation and New event in its actions, today highlighted, calmer weekday headings

## 4. Detail Pages

- [ ] 4.1 Task detail: breadcrumbs, main column (title, description, subtasks, comments) and a properties panel with the save action
- [ ] 4.2 Event detail: breadcrumbs, details panel, attendees with avatars, meeting notes ("Write meeting note" kept)
- [ ] 4.3 Release detail: breadcrumbs, status badge in the header, sentence-case side-panel labels

## 5. Tests & Verification

- [ ] 5.1 Feature tests: settings section anchors and overview; task and event breadcrumbs; `stat-strip` on the team page; release breadcrumbs
- [ ] 5.2 Run `php artisan test`: the full suite passes with no edits to existing tests
- [ ] 5.3 Run `npm run build`; take headless screenshots at 1440 and 390 of every touched page, with no overflow and no JS errors
