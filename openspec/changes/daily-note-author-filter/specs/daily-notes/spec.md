## ADDED Requirements

### Requirement: Filter notes by author
The notes list SHALL support filtering to the notes written by one person, combinable with the existing single-day and date-range filters and cleared by the same action that clears them. The filter SHALL narrow the notes the viewer may already see and SHALL NOT widen them.

#### Scenario: Filter to one author
- **WHEN** a viewer filters the notes list by a person
- **THEN** only that person's notes, among those the viewer may see, are listed

#### Scenario: Filter to your own notes
- **WHEN** a viewer filters the list by themselves
- **THEN** only their own notes are listed, including their private ones

#### Scenario: Combine with a date filter
- **WHEN** a viewer filters by an author together with a day or a date range
- **THEN** only that author's notes within that span are listed

#### Scenario: Clearing restores the list
- **WHEN** a viewer clears the filters
- **THEN** the full list of notes they may see is listed again

### Requirement: The author filter respects note visibility
The author filter SHALL NOT reveal a note the viewer is not otherwise permitted to see. Filtering by a person SHALL return only that person's notes that are already visible to the viewer.

#### Scenario: Another person's private notes stay hidden
- **WHEN** a viewer filters by a colleague who has written private notes
- **THEN** those private notes are not listed

#### Scenario: Shared and addressed notes are listed
- **WHEN** a viewer filters by a colleague who has written shared notes and notes addressed specifically to the viewer
- **THEN** both are listed

#### Scenario: An unknown author yields nothing
- **WHEN** a viewer filters by a person who has no notes visible to them
- **THEN** the list is empty rather than an error

### Requirement: Author options reflect what the viewer can see
The author filter SHALL offer only people who have written notes the viewer may see, and SHALL keep offering a person whose account has since been deleted while their notes remain visible.

#### Scenario: Only relevant authors are offered
- **WHEN** a viewer opens the author filter
- **THEN** it lists the authors of notes visible to them, and not people whose notes they cannot see

#### Scenario: The viewer is offered when they have notes
- **WHEN** a viewer who has written at least one note opens the author filter
- **THEN** they are offered as an option

#### Scenario: A departed author stays selectable
- **WHEN** an author's account has been deleted but their shared notes remain visible
- **THEN** they are still listed as an author option, by name
