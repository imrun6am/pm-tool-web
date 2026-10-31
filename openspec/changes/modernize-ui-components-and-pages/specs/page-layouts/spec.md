## ADDED Requirements

### Requirement: Standard list page anatomy
Each list page SHALL be built, top to bottom, from:
1. a page header: title, an optional description, and its primary action;
2. an optional summary row;
3. an `x-filter-bar` when the page has filters;
4. the content (a table, list or grid);
5. an `x-empty-state` when there is nothing to show;
6. pagination, when the page has more than one page of results.

This applies to dashboard, releases, users, activity, performance, tasksheet, board, notes and meeting notes. These pages SHALL NOT use labelled multi-row filter cards with an "Apply" button.

When a filter matches nothing, the empty state SHALL explain that and offer a link that clears the filters. When the list is empty with no filters applied, it SHALL offer the page's create action, where the user is allowed to create.

#### Scenario: Filtered empty state
- **WHEN** the meeting-notes list is filtered and nothing matches
- **THEN** the empty state reads "No meeting notes match these filters" and offers "Clear all filters"

#### Scenario: Users page shows the table early
- **WHEN** a lead opens the users page at 1440×900
- **THEN** the filter bar and the first row of the users table are visible without scrolling

### Requirement: Notes feed
The Notes page SHALL include:
- a filter bar with the author, single-day and date-range filters, keeping their existing `author`, `date`, `from` and `to` parameters;
- a collapsed composer reading "Write a note…" that expands into the full editor (date, visibility, recipients) when clicked;
- the visible notes as a single-column feed, newest first, grouped under day headings such as "Today", "Yesterday" or "Wed, Oct 28".

Each note SHALL show the author's avatar, name, time, visibility badge and body. A note's author SHALL get Edit and Delete actions. The composer SHALL start expanded when the previous submission failed validation.

#### Scenario: Grouped by day
- **WHEN** a user has notes dated today and yesterday
- **THEN** the feed shows a "Today" heading above today's notes and a "Yesterday" heading above yesterday's, newest first within each

#### Scenario: Composer re-opens on error
- **WHEN** a note is submitted with an empty body
- **THEN** the page re-renders with the composer expanded and the body error shown

#### Scenario: Filters keep working
- **WHEN** the author select in the filter bar changes
- **THEN** the page reloads with `?author=<id>` and shows only that author's notes visible to the viewer

### Requirement: Meeting notes list
The meeting-notes index SHALL show a filter bar with search, type, release, author, attendee and meeting-date range, keeping the existing parameter names. It SHALL list the notes as rows in one card. Each row SHALL show:
- the type badge;
- the title, with a one-line plain-text excerpt;
- the release chip, or "General";
- the meeting date;
- the author's avatar and name;
- the attendee count;
- an "Attendees only" marker where it applies.

Each row SHALL link to the note.

#### Scenario: Row content
- **WHEN** a meeting note titled "Planning minutes" with 3 attendees exists
- **THEN** its row shows "Planning minutes", its type badge, its date and "3 attendees", and links to the note

### Requirement: Meeting note detail
The meeting-note page SHALL use a document layout:
- breadcrumbs back to Meeting notes;
- a large title, with a meta line giving type, date and author;
- Edit and Delete actions for permitted users;
- the body as readable prose, at most about 75 characters per line;
- a side panel listing the release, visibility, the linked event and the attendees with their avatars.

Comments, where present, stay below the body.

#### Scenario: Detail side panel
- **WHEN** a user opens a meeting note that has attendees and a linked event
- **THEN** the side panel lists each attendee with an avatar and shows "Linked to event" with the event title
