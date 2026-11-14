## Context

`daily-team-standup` stored each standup as a meeting note with no author and an empty body, plus a `daily_standups` row holding the generated listing. With the feature gone, the notes it already made need somewhere to keep their content.

## Decisions

### Keep generated notes as ordinary notes
The migration copies the listing (counts, who was on leave or hadn't filled in, the AI summary when there was one, and each person's did/today) into the note's `body` as note HTML, after which any text people wrote is kept under "Meeting notes". The table is then dropped. Considered instead: deleting the notes. That loses the record, and people can still delete them one by one.

### Type goes back to Meeting
No one wrote a `standup` note by hand, so the type is removed from `MeetingNote::TYPES` and converted notes become `meeting`. A note that still had an unknown type would render with a fallback label anyway.

### Visibility becomes attendees only
The notes were visible to whoever could see the team's tasksheet. With the team link gone, everyone-visibility would show one team's tasksheet text more widely. Attendees only keeps them with the team members listed on the note and with leads.

### `created_by` stays nullable
The converted notes never had an author, and naming one now would credit someone with a note they didn't write. Views and the API already handle a null author. With no author nobody can edit them, but a lead can still delete them.

### Not restored on rollback
`down()` recreates an empty `daily_standups` table. Converted notes are left as they are.

## Risks / Trade-offs

- **People analytics counts their attendees again.** Those were the people expected at the standup, minus anyone on leave or marked as missing it, so the count is close to real attendance. → Accepted.
