> Implemented and verified. 85 tests pass (6 new). Live smoke confirmed privacy: shared notes are
> visible to all, and no user (including admin) can see another user's private notes.

## 1. Data, model, policy

- [x] 1.1 Migration: `notes` (user_id cascade, date, body, visibility) + indexes
- [x] 1.2 `Note` model: fillable, `date` cast, `author` relation, `visibleTo(User)` scope, visibility constants/labels
- [x] 1.3 `NotePolicy`: update/delete = author only (auto-discovered)

## 2. Request, controller, routes

- [x] 2.1 `NoteRequest`: body required (max), visibility in {private, shared}, date required
- [x] 2.2 `NoteController`: index (by day, own + shared), store, update (policy), destroy (policy)
- [x] 2.3 Routes under `auth`: `GET notes`, `POST notes`, `PUT notes/{note}`, `DELETE notes/{note}`

## 3. UI

- [x] 3.1 Notes day view: date nav (prev/next/today + picker), add-note card (body + private/shared toggle), notes list with author + visibility badge + inline edit + delete (confirm modal), empty state
- [x] 3.2 Nav "Notes" link (desktop + mobile)

## 4. Seed, test, verify

- [x] 4.1 Seeder: one shared + one private demo note for today
- [x] 4.2 Feature tests: create private/shared; empty rejected; day view shows own + shared but hides others' private; author-only edit/delete
- [x] 4.3 Migrate, build, run full suite, live smoke, then check off tasks and `openspec validate`

## Revision: share-with (specific visibility) + paginated all-notes list

- [x] R.1 `note_user` pivot; `Note` gains `recipients()`, `specific` visibility, `isSpecific()`/`isVisibleTo()`, and `scopeVisibleTo` includes specific-shared-with-me
- [x] R.2 `NoteRequest` validates `recipients`; `NoteController@index` lists all visible notes newest-first, paginated, with day + range filters; store/update sync recipients only for specific notes
- [x] R.3 Notes index rewritten (add form with visibility + "Share with" picker via `<x-multi-select>`, filter bar, paginated cards); `_card` shows date, visibility badge, and shared-with list, with recipient picker in inline edit
- [x] R.4 Feature tests: default all-notes list, specific-note visibility (recipients/author only, not leads), recipient sync, recipients dropped when not specific
