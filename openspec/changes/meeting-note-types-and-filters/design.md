## Context

`MeetingNote` (from `meeting-notes`, extended by `meeting-note-attendees`) has a title, meeting date, rich body, nullable `release_id` / `event_id`, `created_by`, an attendee pivot, and a `visibility` of `everyone` | `attendees` enforced by `scopeVisibleTo()`.

Three facts shape this change:

- **Enumerations in this codebase are model constants**, not lookup tables: `Event::TYPES` / `Event::TYPE_COLORS`, `Task::STATUSES`, `Release::PHASES`, `Note::VISIBILITIES`, `MeetingNote::VISIBILITIES` — all `key => label` maps with an optional parallel colour map.
- **`Api\V1\MetaController` publishes every one of those maps in a single `/v1/meta` call**, precisely so a desktop client renders its pickers and type filters from the server list rather than shipping its own copy. A new enum reaches installed clients on their next request.
- **`MeetingNoteService::visibleTo()` is the single query behind both the Blade index and `Api\V1\MeetingNoteController@index`.** `tests/Feature/Services/WebApiParityTest.php` asserts that the API is not a second implementation, so any filter added to the service reaches both surfaces at once.

## Goals / Non-Goals

**Goals:**
- Classify a meeting note by type, with the type list defined server-side and changeable without a migration or a client release.
- Leave every existing note exactly as it is, under a default type that reads the same as today.
- Give the Meeting Notes list the filters real use demands: type, author, attendee, text search — composable with the existing release and date filters.
- Paginate the list now that filters invite browsing.
- Keep web and API in parity by adding all of it in the service layer.

**Non-Goals:**
- No runtime type management (no admin CRUD for meeting types, no per-team type sets) — see decision 1.
- No change to visibility, attendees, release/event linkage, or the Trix + sanitizer pipeline.
- No saved/shareable filter presets, and no filtering on the release-details or event-details cards (they stay scoped summaries with a "view all" link into the filtered index).
- No search engine or FULLTEXT index — see decision 6.

## Decisions

1. **Type is a `TYPES` const on the model, not a lookup table.** Follows the `Event::TYPES` precedent exactly, and `MetaController::options()` already consumes that shape. Adding "Retro" later is one line. Rejected: (a) a `meeting_types` table with CRUD — nothing in the request calls for end users defining types at runtime, and it would drag in admin UI, ordering, and a retire-vs-reassign story for types already in use; (b) a PHP enum — every other enumeration here is a `key => label` const map, and `MetaController` depends on that shape.

2. **Seed set and default.** `meeting` => "Meeting" (default), `sync_up` => "Sync Up", `day_end` => "Day End", `progress_monitoring` => "Progress Monitoring", `initial_handover` => "Initial Handover", `initial_qa_feedback` => "Initial QA Feedback", `final_handover` => "Final Handover". A parallel `TYPE_COLORS` map gives each a badge colour, mirroring `Event::TYPE_COLORS`.

   **The order of the constant is meaningful** — it is what the form selector, the type filter, and every API client's picker render in, since `MetaController::options()` emits a list precisely so the server's ordering survives serialization. Hence the grouping: the recurring cadence meetings first (Sync Up, Day End, Progress Monitoring), then the delivery milestones in the order they actually happen (Initial Handover → Initial QA Feedback → Final Handover). Adding a type means choosing where it belongs in that sequence, not just appending it.

3. **Existing data takes the column default, not a backfill script.** The migration adds `type` as `string` with default `'meeting'`, so every existing row is a "Meeting" the moment the column exists. The default keeps its current label, so no note appears to have been re-categorized. `type` is also the form default for new notes, including notes created from a calendar event — the event's own type is a *calendar* type (`meeting`/`review`/`release`/…) and is not the same vocabulary, so it is deliberately not mapped across.

4. **Retiring a type degrades gracefully.** `typeLabel()` falls back to `ucfirst()` and `typeColor()` to slate when a stored value is not in the const, matching `Event`. So removing an entry leaves old notes readable rather than blank — but they stop being reachable from the type filter, which lists the const only. Prefer **relabeling** an entry (same key, new label) over removing it; removal is for types with no history worth browsing.

5. **Author and attendee are separate filters.** `author` matches `created_by`; `attendee` matches the pivot via `whereHas('attendees')`. Both take a user id and both compose with everything else. Rejected: one "person" filter matching either — it cannot express "notes X wrote" separately from "meetings X sat in", which is the more common of the two questions.

6. **Search is a `LIKE` over title and body.** This reverses the original `meeting-notes` non-goal ("no full-text search"), deliberately: the list is now a browsing surface and the thing people search for lives in the minutes, not the title. The term is matched against `title` and the sanitized `body`, with wildcards escaped so a literal `%` or `_` cannot widen the match. Because `body` is sanitized HTML, a term can in principle match inside markup — acceptable given the sanitizer's narrow tag allowlist and the small note volume.

   **The escape character is `!`, named in an explicit `ESCAPE` clause — deliberately not a backslash.** MySQL (production) treats `\` as the default LIKE escape; SQLite (the test suite) has no default escape character at all. A backslash-escaped term therefore means two different things on the two drivers, and the failure is invisible from the test suite alone: searching `50%` matches nothing on SQLite while behaving correctly on MySQL. Naming the escape character in the SQL makes the behaviour identical on both, and is the reason the search predicate uses `whereRaw` rather than `where(..., 'like', ...)`. Rejected: MySQL FULLTEXT (noisy over HTML, and needs its own index maintenance) and Scout (a whole search dependency for a table this size).

7. **Filters run *after* `visibleTo()`, never around it.** The attendee filter in particular must not become a disclosure channel: filtering by a colleague returns only the notes the *viewer* is already allowed to see, so an attendees-only note stays hidden even when the person you filtered by attended it.

8. **All filters compose in one service query.** `MeetingNoteService::visibleTo(User $viewer, array $filters)` grows `type`, `author`, `attendee`, and `search` alongside the existing `release`, `from`, `to`. Each is applied with `->when()`, so any combination works and both the web and API index inherit it. The service keeps returning a builder; the callers decide how to page it.

9. **The index paginates.** It currently calls `->get()`. It moves to `->paginate()` with `withQueryString()` so filters survive page navigation; the API index already goes through `ApiController::paginate()` and needs no change beyond passing the new params through.

10. **One GET filter form.** Type / release / written-by / attended-by selects, the date range, and a search box in a single form with Apply and a single Clear that drops every param. The type select drives a coloured badge on each card, on the show page, and on the release-details and event-details meeting-note cards — those cards display the badge but gain no filters of their own.

## Risks / Trade-offs

- [Search matches sanitized HTML, so a term could hit markup rather than prose] → Escape `%`/`_`, search `title` and `body` together, accept the narrow false-positive rate; the sanitizer's allowlist keeps the markup surface small.
- [Adding a type needs a code deploy, not a settings screen] → Deliberate, and consistent with every other enumeration in the app. `MetaController` means desktop clients still pick up a new type without their own release, so the deploy cost is one line and one server push.
- [Retiring a type strands its old notes outside the filter] → Documented in decision 4; relabeling is the recommended path, and stranded notes stay readable and searchable.
- [More filter permutations to keep correct] → They all funnel through the single `visibleTo()` builder, and the parity test plus per-filter feature tests cover the combinations.
- [Pagination changes the index's response shape for anything reading `$notes` as a plain collection] → Blade iteration is unaffected; only the index view and its empty-state branch need touching.
