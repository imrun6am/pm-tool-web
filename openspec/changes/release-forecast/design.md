## Context

A single projected date is usually wrong and sounds certain. Sampling real past weeks gives a spread that reflects how uneven the team's pace actually is, and needs nothing beyond task statuses.

## Goals / Non-Goals

**Goals:** an honest range from data we already have, on every open release.

**Non-Goals:** predicting new scope, planning around leave or off-days, forecasting per person.

## Decisions

- **Monte Carlo over the team's last 12 full weeks.** 2,000 runs, each drawing a random past week until the open tasks are used up. The results give the 50th, 85th and 95th percentile dates, and the share of runs that land by the planned end.
- **The current week is left out** because it's incomplete.
- **Weeks before the team's first finished task are left out**, so a new team isn't slowed by weeks when it didn't exist. Quiet weeks after that do count: they really happened.
- **At least 3 weeks with finished work**, otherwise no forecast. Fewer than that isn't a pattern.
- **Seeded per release**, so a reload shows the same dates until the facts change. The generator is reseeded afterwards so nothing else inherits the seed.
- **Team throughput, not release throughput.** A release in its first week has no pace of its own, and the team is who does the work.
- **Worded as a likelihood.** The card says "a likelihood, not a promise" and states what it assumes. Claude's prompt says the same.

## Risks / Trade-offs

- **It assumes no more scope.** The burn-up above it shows whether scope is growing, and the card says so.
- **It ignores leave and off-days.** Past weeks already contain some of each, so it's roughly right on average, but it won't foresee a planned holiday.
- **Teams sharing work across releases**: throughput is per team, so two releases at once each assume the team's full pace. The existing double-booking warning covers that case.
