## ADDED Requirements

### Requirement: Vibrant blue brand
The app's primary color SHALL be a vibrant blue. Brand-colored text and white text on brand buttons SHALL meet WCAG AA contrast (4.5:1).

#### Scenario: Primary button
- **WHEN** any page renders a primary button
- **THEN** it uses the blue `brand-600`, and its label contrast is at least 4.5:1

### Requirement: Semantic icon colors
Icons SHALL take their color from one shared palette:
- each product area has one hue, used in the sidebar, command palette, page header and empty states;
- status icons use success (emerald), warning (amber), danger (rose) and info (blue).

#### Scenario: Same area, same hue
- **WHEN** a user opens Calendar
- **THEN** the sidebar icon, the page-header icon and the page's empty-state icon all use the calendar hue (rose)

#### Scenario: Destructive action
- **WHEN** a table row shows a delete action
- **THEN** its hover color is the danger tone

### Requirement: Sidebar presentation
The sidebar SHALL:
- show each item's icon in a tile of its area hue;
- mark the active item with a tinted row, an accent bar and a solid icon tile;
- show group headings while expanded;
- keep the collapsed icon rail (with tooltips) and the mobile drawer.

The items shown per role SHALL be unchanged.

#### Scenario: Developer sidebar
- **WHEN** a developer opens the board
- **THEN** the sidebar lists only their work surfaces, and Board is marked active with a solid sky icon tile

### Requirement: Menu change motion
When the user moves between pages from the menu, the active highlight SHALL move smoothly from the previous item to the new one, and the page content SHALL fade and rise in. A same-page refresh SHALL NOT animate the content. With reduced motion requested, none of these animations SHALL play.

#### Scenario: Clicking through the menu
- **WHEN** a user on Notes clicks Meetings
- **THEN** the highlight slides from Notes to Meetings, and the Meetings page fades up into place

#### Scenario: Reduced motion
- **WHEN** the system requests reduced motion
- **THEN** the page swaps without the slide or rise animations
