## ADDED Requirements

### Requirement: Grouped sidebar navigation
The system SHALL render primary navigation as a vertical sidebar for every authenticated page that uses the app layout. Menu items SHALL be organized into labelled groups, in this fixed order:

1. **Overview**: Dashboard
2. **Planning**: Releases, Projects, Teams
3. **Work**: Board, Tasksheet, Calendar
4. **Collaboration**: Notes, Meetings, Activity
5. **Administration**: Performance, Users

The Overview group SHALL render without a visible heading. Every other group SHALL show its label as a small uppercase heading above its items.

#### Scenario: Full-access lead sees every group in order
- **WHEN** an admin, CTO, tech lead, or team lead loads any app page
- **THEN** the sidebar shows the Overview, Planning, Work, Collaboration, and Administration groups in that order
- **AND** each group lists its items in the order defined above

#### Scenario: Sidebar present across pages
- **WHEN** an authenticated user loads the dashboard, a release detail page, or the board
- **THEN** each page renders the same sidebar navigation

### Requirement: Role-scoped menu items
The sidebar SHALL show only the items the current user's role may access, and these rules SHALL match the access rules that exist today:

- Developers and QA (limited access) SHALL see only Dashboard, Board, Tasksheet, Calendar, Notes, and Meetings.
- Performance SHALL be shown only to lead roles.
- Users SHALL be shown only to users who can manage users.
- A group with no visible items SHALL NOT render, and neither SHALL its heading.

Hidden items SHALL NOT appear anywhere in the page markup, including tooltips, `aria-label`s, and data attributes.

#### Scenario: Developer sees only work surfaces
- **WHEN** a developer or QA user loads any app page
- **THEN** the sidebar shows Dashboard, Board, Tasksheet, Calendar, Notes, and Meetings
- **AND** the page markup does not contain the labels "Projects", "Teams", "Releases", "Activity", "Performance", or "Users" as navigation entries
- **AND** the Planning and Administration group headings do not render

#### Scenario: Viewer sees planning but not administration
- **WHEN** a viewer loads any app page
- **THEN** the sidebar shows the Overview, Planning, Work, and Collaboration groups
- **AND** the Administration group does not render

#### Scenario: Lead sees administration
- **WHEN** a lead-role user loads any app page
- **THEN** the Administration group shows the Performance and Users items

### Requirement: Active item indication
The system SHALL mark the item that matches the current route as active. Each item SHALL declare the route-name patterns it owns, and an item SHALL be active when the current route matches any of them, so nested pages keep their parent item highlighted. For example, `events.*` belongs to Calendar, and `releases.*` belongs to Releases, including release detail and edit pages.

The active item SHALL be visually distinct from the other items: a highlighted background, an accent indicator, and stronger text. It SHALL carry `aria-current="page"`. Exactly one item at most SHALL be active at a time.

#### Scenario: Top-level page is active
- **WHEN** a user is on the board page
- **THEN** the Board item has `aria-current="page"` and the active styling
- **AND** no other item has `aria-current`

#### Scenario: Nested page keeps parent active
- **WHEN** a user views a calendar event detail page (`events.show`)
- **THEN** the Calendar item is marked active

#### Scenario: Page outside the menu
- **WHEN** a user is on their profile page
- **THEN** no sidebar item is marked active

### Requirement: Distinct item icon and color identity
Every menu item SHALL display a unique icon that no other menu item uses. Every menu item SHALL also have an assigned accent color from the design system's palette, used to tint its icon. Icons SHALL be decorative (`aria-hidden="true"`), and the item's text label SHALL provide its accessible name.

#### Scenario: No shared icons
- **WHEN** the sidebar renders for a lead (all items visible)
- **THEN** no two items render the same icon

#### Scenario: Performance has its own icon
- **WHEN** the sidebar renders for a lead
- **THEN** the Performance item's icon differs from the Activity item's icon

### Requirement: Collapsible desktop sidebar
On viewports at the `lg` breakpoint and wider, the sidebar SHALL be permanently visible and SHALL offer a control that collapses it to an icon-only rail and expands it again.

When collapsed:
- item labels and group headings SHALL be visually hidden;
- each item's label SHALL remain available to assistive technology;
- each item's label SHALL appear as a tooltip on hover and keyboard focus.

The collapsed or expanded preference SHALL persist in the browser across page loads. If browser storage is unavailable, the sidebar SHALL default to expanded without errors.

#### Scenario: Collapse and persist
- **WHEN** a desktop user activates the collapse control and then navigates to another page
- **THEN** the sidebar is still collapsed on the new page

#### Scenario: Labels remain accessible when collapsed
- **WHEN** the sidebar is collapsed
- **THEN** each item still exposes its label as its accessible name
- **AND** hovering or focusing an item shows its label in a tooltip

#### Scenario: Storage unavailable
- **WHEN** browser storage throws or is empty
- **THEN** the sidebar renders expanded and the page shows no script errors

### Requirement: Mobile off-canvas navigation
On viewports narrower than `lg`, the sidebar SHALL be hidden by default. It SHALL open as an off-canvas drawer from a menu button in the top bar. The open drawer SHALL show the same groups and items as the desktop sidebar over a dimmed backdrop. It SHALL close when the user:
- activates the close button,
- clicks the backdrop,
- presses Escape, or
- follows a navigation link.

The menu button SHALL expose `aria-expanded`, reflecting whether the drawer is open.

#### Scenario: Open and close the drawer
- **WHEN** a mobile user taps the menu button
- **THEN** the navigation drawer slides in with a backdrop and the button reports `aria-expanded="true"`
- **WHEN** the user presses Escape or taps the backdrop
- **THEN** the drawer closes and the button reports `aria-expanded="false"`

### Requirement: Sidebar workspace header and Create action
Following the convention of modern project-management tools, the top of the sidebar SHALL show:
- a workspace header with the brand mark and the application name, linking to the dashboard;
- a primary **Create** button below the header.

The Create button SHALL open a menu of creation shortcuts, and that menu SHALL list only actions the user is authorized to perform:
- "New release" for users who can manage releases;
- "New event" for all users;
- "New meeting note" for all users.

When the sidebar is collapsed, the workspace header SHALL show only the brand mark, and the Create button SHALL show only a plus icon with a tooltip.

#### Scenario: Release manager sees New release
- **WHEN** a lead-role user opens the Create menu
- **THEN** the menu includes "New release", linking to the release creation page

#### Scenario: Non-manager does not see New release
- **WHEN** a developer, QA, or viewer user opens the Create menu
- **THEN** the menu does not include "New release"
- **AND** the menu still includes "New event" and "New meeting note"

### Requirement: Command palette
The system SHALL provide a command palette for keyboard-first navigation. It SHALL be opened by any of:
- `⌘K` on macOS,
- `Ctrl+K` on other platforms,
- a search trigger in the top bar.

The palette SHALL list the user's visible navigation items and Create actions. It SHALL filter the list as the user types, using a case-insensitive match on the label and group. Arrow keys SHALL move the selection, Enter SHALL go to the selected entry, and Escape SHALL close the palette. The palette SHALL offer only entries that the current user's sidebar and Create menu would show. It SHALL search navigation only, not records.

#### Scenario: Open with keyboard shortcut
- **WHEN** a user presses `⌘K` (or `Ctrl+K`) on any app page
- **THEN** the command palette opens with the search input focused

#### Scenario: Filter and navigate
- **WHEN** the user types "cal" and presses Enter
- **THEN** the browser navigates to the Calendar page

#### Scenario: Role-scoped entries
- **WHEN** a developer opens the command palette
- **THEN** it does not list Projects, Teams, Releases, Activity, Performance, Users, or "New release"

### Requirement: Top utility bar
The system SHALL render a slim top bar above the page content that contains:
- the mobile menu button (below `lg` only);
- the command-palette search trigger, showing the keyboard shortcut hint;
- a Quick links button that opens the quick-links drawer;
- the user menu, with the user's name, role badge, email, Profile link, and Log Out action.

The quick-links drawer SHALL be opened from this button, and the floating right-edge handle SHALL be removed. The top bar and any sticky page sub-header SHALL remain pinned while the page scrolls.

#### Scenario: Search trigger opens the palette
- **WHEN** a user clicks the search trigger in the top bar
- **THEN** the command palette opens

#### Scenario: Quick links opened from the top bar
- **WHEN** any authenticated user activates the Quick links button
- **THEN** the quick-links drawer opens
- **AND** no floating right-edge quick-links handle is rendered

### Requirement: Semantic color system
The UI theme SHALL define named color tokens that the application shell uses instead of ad-hoc palette values:
- `brand`: the primary accent;
- a sidebar surface token set: background, text, muted text, hover, and active. In line with modern project-management tools, this SHALL be a light, neutral surface separated from the light-gray content canvas by a hairline border;
- status tokens: `success`, `warning`, `danger`, `info`.

Sidebar, top bar, and navigation components SHALL use these tokens. Text and icons in the navigation SHALL meet WCAG 2.1 AA contrast (4.5:1 for text, 3:1 for icons) against their background in every state: default, hover, and active.

#### Scenario: Navigation contrast
- **WHEN** the sidebar renders in its default, hover, and active states
- **THEN** item label text has a contrast ratio of at least 4.5:1 against the sidebar background
- **AND** icons have a contrast ratio of at least 3:1

### Requirement: Accessible navigation chrome
The sidebar SHALL be a `<nav>` landmark with an accessible label. Group headings SHALL be associated with their item lists. All interactive navigation controls SHALL be keyboard-operable and SHALL show a visible focus indicator. Sidebar and drawer transitions SHALL be suppressed when the user prefers reduced motion.

#### Scenario: Keyboard traversal
- **WHEN** a keyboard user tabs through the page from the top
- **THEN** focus reaches every visible sidebar item in group order, each with a visible focus ring

#### Scenario: Reduced motion
- **WHEN** the user's system requests reduced motion
- **THEN** opening the mobile drawer and collapsing the sidebar happen without animated transitions
