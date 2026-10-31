## ADDED Requirements

### Requirement: Board summary
The board SHALL show, above the columns:
- the number of tasks in the current view;
- the percentage of those tasks that are done;
- a segmented progress bar with one segment per non-empty status, in that status's color.

#### Scenario: Summary reflects the view
- **WHEN** the filtered board shows 4 tasks, 1 of them done
- **THEN** the summary reads "4 tasks" and "25% done", and the bar has a segment for each non-empty status

### Requirement: My tasks quick filter
The board SHALL offer a "My tasks" toggle. When it is on, the board is filtered to `assignee_id` equal to the current user, keeping any `release_id`. When it is on, it SHALL appear pressed, and clicking it again SHALL remove the assignee filter.

#### Scenario: Toggle on
- **WHEN** a user clicks "My tasks" while viewing release 3
- **THEN** the board reloads with `release_id=3&assignee_id=<their id>` and the toggle is pressed

### Requirement: Column presentation
Each status column SHALL have:
- a status-colored accent;
- its label, with a count of its cards;
- a visible drop-target highlight while a card is dragged over it;
- a dashed "Drop tasks here" zone when it is empty.

The Archive column SHALL start collapsed into a narrow rail showing its label and count. It SHALL expand on click, and the expanded or collapsed choice SHALL persist in the browser.

#### Scenario: Archive collapsed by default
- **WHEN** a user opens the board for the first time
- **THEN** the Archive column is a collapsed rail with its count, and clicking it expands the column

### Requirement: Card presentation
Each card SHALL show:
- its title, linking to the task;
- its release name;
- a project-color accent;
- the phase, as a colored chip;
- the due date, styled as overdue (danger), due today (warning) or upcoming;
- subtask progress as a count and a mini bar;
- the comment count;
- the assignee's avatar and name.

Cards SHALL use icons rather than emoji.

#### Scenario: Overdue card
- **WHEN** a card's due date has passed and the task is not done
- **THEN** its due pill reads "Overdue" with the date, in the danger tone

### Requirement: Accessible moving and feedback
Each card SHALL include a "Move to" status control usable by keyboard, which moves the card to the end of the chosen column. A move, by drag or by the control, SHALL be saved through the existing `board.move` endpoint, and the column counts SHALL update. A successful move SHALL show a confirmation toast. A failed move SHALL show an error toast and restore the card's previous position.

#### Scenario: Move with the keyboard
- **WHEN** a user focuses a card's "Move to" control and chooses "Done"
- **THEN** the card appears at the end of the Done column, the counts update, the move is saved, and a toast confirms it

#### Scenario: Failed move
- **WHEN** the server rejects a move
- **THEN** the card returns to its original column and position, and an error toast is shown
