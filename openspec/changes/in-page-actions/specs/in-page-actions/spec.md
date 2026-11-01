## ADDED Requirements

### Requirement: Actions update only their region
A form that creates, updates, deletes or toggles a record inside a live region SHALL be submitted in the background. Only that region SHALL be replaced with the server-rendered result. The rest of the page SHALL stay as it was, including scroll position, open drawers and UI state outside the region.

#### Scenario: Deactivating a user
- **WHEN** an admin clicks "Deactivate" on a row in the users list
- **THEN** the users region re-renders with that user marked deactivated and the summary counts updated
- **AND** the page does not reload, and the scroll position is unchanged

#### Scenario: Pinning a quick link
- **WHEN** a user pins a link in the open quick-links drawer
- **THEN** the link moves to the pinned position inside the drawer
- **AND** the drawer stays open

#### Scenario: Creating a note
- **WHEN** a user writes a note and clicks "Add note"
- **THEN** the note appears in the feed under today's date and the composer is cleared

### Requirement: Links inside a region navigate the whole page
Links and GET forms inside a live region SHALL perform a normal soft navigation of the whole page, updating the URL.

#### Scenario: Pagination inside a region
- **WHEN** the user clicks the next-page link below the notes feed
- **THEN** the whole page navigates to page 2 and the URL changes

### Requirement: Feedback for region actions
A region submission SHALL show the top progress bar while in flight. Any success, error or warning flash set by the server SHALL appear as a toast once the region updates.

#### Scenario: Success toast
- **WHEN** a quick link is deleted from the drawer
- **THEN** the "Link deleted." toast appears

### Requirement: Validation errors in a region
When the server rejects a region submission with validation errors, the region SHALL re-render with the inline field errors and the user's previous input.

#### Scenario: Empty note
- **WHEN** a user submits the note composer with an empty body
- **THEN** the composer re-renders open with the body error shown under the editor

### Requirement: Whole-page fallback
If the page that a region submission redirects to does not contain that region, the client SHALL render the redirected page as a whole-page soft visit.

#### Scenario: Region missing after redirect
- **WHEN** an action inside a region redirects to a page without that region
- **THEN** the browser shows the redirected page as a normal soft navigation

### Requirement: Scroll kept on same-page actions outside regions
A form outside any live region that redirects back to the same page SHALL keep the scroll position. When that page renders validation errors, the first invalid field SHALL be scrolled into view.

#### Scenario: Completing a release
- **WHEN** a lead scrolls down a release page and clicks "Mark complete"
- **THEN** the page updates without a reload and stays at the same scroll position
