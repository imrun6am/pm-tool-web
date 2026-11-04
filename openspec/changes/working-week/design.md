## Context

Carbon's `isWeekend()` and `isWeekday()` assume Saturday and Sunday unless told otherwise. `config/planning.php` already holds `weekend_days`, read directly by two services.

## Goals / Non-Goals

**Goals:** one answer to "is this a working day?", everywhere, from one setting.

**Non-Goals:** changing where a week starts, per-team working weeks, or editing stored records.

## Decisions

- **A small helper rather than Carbon's global `setWeekendDays`.** The global setter changes behaviour for anything calling Carbon, including libraries, and is invisible at the call site. An explicit call also makes a future rule, such as alternate Saturdays, a change in one place.
- **Weeks still run Monday to Sunday.** A week in a report is a bucket, not a working pattern, and performance scores are stored against Monday-anchored weeks.
- **The calendar grid still starts on Sunday.** That's display only.
- **Figures recompute.** Expected tasksheet days are worked out on read, so past numbers follow the new rule with no migration. That's the point of the change, and it's the one thing people will notice.
- **Stored off-days stay.** Leads marked those on purpose, including Saturdays.
- **Alternate days off are a date and a 14-day cycle**, counted either side of the date. Tasksheet rows show Saturdays worked on Jul 25, 2026 and every 14 days after, with off-days on the Saturdays between, so the default is Aug 1, 2026. The rule applies in both directions, so past figures are right too.
- **A date that doesn't parse counts as no rule**, because this check runs on most pages.
- **A setting naming all seven days counts as no weekend**, because several callers search for the next working day and would never finish.
- **Tests pin Saturday and Sunday in `phpunit.xml`.** Hundreds of fixtures use fixed dates, and they test other things. `WorkingWeekTest` and the capacity and recurring tests set the weekend themselves.

## Risks / Trade-offs

- **The alternate Saturday follows a fixed 14-day cycle.** A holiday that moves the pattern needs a new date in the setting, or an off-day on the release. Off-days don't feed tasksheet expectations, so a one-off holiday still reads as a miss, as it did before.
- **One calendar for everyone.** A team on a different Saturday would be wrong; the data shows one shared pattern.
- Sundays were never expected before, so missed Sundays now count against tasksheet rates.
