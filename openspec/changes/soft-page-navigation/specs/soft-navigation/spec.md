## ADDED Requirements

### Requirement: Navigation without full page reloads
Same-origin links and forms on pages that use the app layout SHALL load the next page in the background and swap it into the current document, without a full browser reload. The current page SHALL stay visible until the next page is ready, so the window never shows a blank page in between.

#### Scenario: Clicking a sidebar link
- **WHEN** a signed-in user clicks "Releases" in the sidebar on the dashboard
- **THEN** the dashboard stays on screen until the releases page has loaded
- **AND** the releases page replaces it without a blank white frame
- **AND** the URL, the document title and the sidebar's active item update to Releases

#### Scenario: Command palette jump
- **WHEN** the user opens ⌘K, types "board" and presses Enter
- **THEN** the board page loads the same way, without a full reload

### Requirement: Top progress bar
A thin progress bar in the brand accent color SHALL run along the top edge of the viewport while a page visit or form submission is in flight, and SHALL complete and fade out when the new page renders.

#### Scenario: Visit shows the bar
- **WHEN** the user navigates to another page
- **THEN** a progress bar appears at the top of the viewport above all page chrome
- **AND** it disappears once the new page is shown

#### Scenario: Form submission shows the bar
- **WHEN** the user submits a form, for example saving a note
- **THEN** the progress bar runs until the redirected page renders

### Requirement: Smooth page transition with reduced-motion support
When the browser supports view transitions, the old and new page content SHALL crossfade. When the user prefers reduced motion, or the browser has no support, the new page SHALL appear immediately, without animation.

#### Scenario: Reduced motion
- **WHEN** the operating system has "reduce motion" enabled
- **THEN** pages swap without a crossfade animation

### Requirement: Interactive components survive navigation
Alpine components, Trix editors, the "Enhance with Claude" control, the board's drag-and-drop, the command palette, toasts and the confirmation modal SHALL work on every page reached by soft navigation, exactly as after a full page load. Global listeners SHALL NOT pile up across visits.

#### Scenario: Board drag-and-drop after navigating
- **WHEN** the user navigates from the dashboard to the board and drags a card to another column
- **THEN** the card moves, the change is saved, and exactly one "Moved to …" toast appears

#### Scenario: Trix and Enhance on a form reached by navigation
- **WHEN** the user navigates to "New note"
- **THEN** the Trix editor is editable and the "Enhance with Claude" button enables once text is typed

#### Scenario: No duplicate listeners
- **WHEN** the user has visited ten pages and then submits a form marked `data-confirm`
- **THEN** exactly one confirmation modal is shown and the form submits once on confirm

### Requirement: Flash messages and validation errors after form submission
Success, error and warning flashes SHALL show as toasts on the page that a form submission redirects to. Validation errors and old input SHALL render inline, as they do today.

#### Scenario: Successful save
- **WHEN** the user saves a meeting note and the server redirects with a success flash
- **THEN** the redirected page shows the success toast

#### Scenario: Validation failure
- **WHEN** the user submits a form with a missing required field
- **THEN** the form page re-renders with the inline error summary and the previously entered values

### Requirement: Downloads, logout and external links keep native behaviour
Download links (KPI reports, release documents, the tasksheet PDF), the logout form, links that open a new tab and links to other origins SHALL NOT be intercepted. They SHALL behave as a normal browser request.

#### Scenario: Downloading a release document
- **WHEN** the user clicks a document on a release page
- **THEN** the browser downloads the file and the current page stays as it was

#### Scenario: Logging out
- **WHEN** the user clicks "Log out"
- **THEN** the session ends and the browser does a full load of the destination page with a fresh CSRF token

### Requirement: History and scroll restoration
The browser's back and forward buttons SHALL move between visited pages, showing fresh server data. The scroll position of the page being returned to SHALL be restored. Forward navigations SHALL start at the top of the new page.

#### Scenario: Back button
- **WHEN** the user scrolls down the releases list, opens a release, then presses Back
- **THEN** the releases list is shown again at the scroll position they left it
