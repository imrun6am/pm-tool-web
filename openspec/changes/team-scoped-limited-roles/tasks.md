## 1. Shared Rules

- [x] 1.1 `User::LIMITED_ROLES`; `teammatesOf` includes team leads; `User::scopeVisibleTo`.
- [x] 1.2 `Release::scopeVisibleTo($user, $keep)` / `isVisibleTo`; `ReleasePolicy::view`.
- [x] 1.3 `LimitsToViewerTeams` concern; `VisibleRelease` and `VisiblePerson` validation rules.

## 2. Releases

- [x] 2.1 `EnsureReleaseIsVisible` (`release-visible`) on the open release route groups in `routes/web.php` and `routes/api/v1/releases.php`.

## 3. Tasksheet

- [x] 3.1 `TasksheetService::teamsFor` returns only their own teams for limited roles. This covers the web sheet, API sheet and exports picker.

## 4. Calendar and Events

- [x] 4.1 `Event::scopeVisibleTo` / `isVisibleTo`; `EventPolicy::view`; `EventService::inWindow(…, $viewer)`.
- [x] 4.2 Calendar, event page, API index and show, event import review: scoped lists and pickers. `EventRequest` and `ConfirmEventImportRequest` use the rules.

## 5. Meeting Notes and Notes

- [x] 5.1 `MeetingNote` everyone-visibility goes through `LimitsToViewerTeams`. Index filters, create and edit pickers, `linkableReleases($note, $viewer)`, `create?event=` limited to visible events. `MeetingNoteRequest` checks the release, attendees and event.
- [x] 5.2 `Note` shared visibility is limited to teammates; recipient picker and `NoteRequest` recipients are scoped.

## 6. Quick Links

- [x] 6.1 `QuickLinkService::attachableReleases($viewer)` from the drawer composer; `QuickLinkRequest` release check.

## 7. Tests and Docs

- [x] 7.1 `CrossTeamScopingTest`: releases (web and API, 403s), pickers, cross-team links/attendees/recipients rejected, tasksheet fallback, calendar and events, meeting notes, notes, and leads unaffected.
- [x] 7.2 Existing tests: the developers involved now share a team.
- [x] 7.3 `docs/api-v1.md`: a "Beyond tasks" section.
