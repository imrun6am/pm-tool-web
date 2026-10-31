## Why

People keep working notes each day — reminders, decisions, blockers. Some are personal; some are worth sharing with the team. The tool has no place for that. This adds daily notes with per-note visibility: private (author only) or shared (everyone).

## What Changes

- Add a **Notes** page organized by day, with previous/next-day navigation and a "today" shortcut.
- Any signed-in user can **add a note for a day** with a visibility of **private** or **shared**.
- A day shows the current user's **own notes** (private and shared) plus **everyone's shared notes**; private notes are never visible to anyone but their author.
- The **author** can edit or delete their own notes; others cannot.
- Add a **Notes** link to the navigation.

## Capabilities

### New Capabilities
- `daily-notes`: Per-day notes with private/shared visibility; authors manage their own notes, shared notes are visible to all, private notes only to their author.

### Modified Capabilities
<!-- None. New, self-contained capability. -->

## Impact

- **Database**: new `notes` table (author, date, body, visibility).
- **Model**: `Note` (belongs to author; `visibleTo` scope). Not added to the activity log, so private notes stay private.
- **Auth**: note routes under `auth`; a `NotePolicy` limits edit/delete to the author.
- **UI**: a day-based Notes page (add/edit/delete, visibility toggle) and a nav link.
- **Seed data**: a couple of demo notes (one shared, one private).
