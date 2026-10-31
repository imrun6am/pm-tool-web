## ADDED Requirements

### Requirement: Standard form page layout
Every create and edit page SHALL render:
- an `x-page-header` with breadcrumbs (`<Section> / New <thing>` or `<Section> / <record> / Edit`) and a one-line description;
- the form in a centered content column no wider than `max-w-5xl`;
- an `x-form-actions` bar.

This applies to projects, teams, users, events, meeting notes, performance competencies, releases and the profile page.

The releases form keeps its two-column layout, with sections plus an "At a glance" side panel, inside the same header and action-bar frame.

#### Scenario: Form width is readable
- **WHEN** the "New meeting note" page renders at 1440px wide
- **THEN** the form column is at most 64rem wide and centered

#### Scenario: Edit breadcrumbs
- **WHEN** a lead opens the edit page for project "Checkout"
- **THEN** the breadcrumbs read "Projects / Checkout / Edit", and the first two crumbs link to the projects list and the project page

### Requirement: Sectioned fields
Fields SHALL be grouped into `x-form-section` blocks. Each block has a title and a short description, beside its fields on desktop and stacked on mobile. Related short fields, such as type and date, SHALL sit side by side in a grid instead of stretching to the full width.

#### Scenario: Meeting note sections
- **WHEN** the meeting-note form renders
- **THEN** it shows a "Details" section (title, type, date, release), an "Access" section (visibility, attendees) and a "Notes" section (body editor)

### Requirement: Field labelling and errors
Every field SHALL have a visible `<label>` tied to its control. Optional fields SHALL be marked "Optional". Required fields SHALL keep the `required` attribute. When a field has a validation error:
- the control SHALL get `aria-invalid="true"` and `aria-describedby` pointing at the error message;
- the message SHALL render directly below the field;
- the existing validation summary at the top of the page SHALL remain.

#### Scenario: Error wiring
- **WHEN** a project is submitted without a name
- **THEN** the name input has `aria-invalid="true"` and `aria-describedby` referencing an element that contains the error text

#### Scenario: Old input preserved
- **WHEN** any form fails validation
- **THEN** every field, including rich-text bodies and multi-select choices, shows the values the user entered

### Requirement: Sticky action bar
The form's buttons SHALL sit in `x-form-actions`. On edit pages that support deletion, the bar's leading side SHALL hold the Delete action, using the existing confirm dialog. Its trailing side SHALL hold Cancel, a link back to the list or record, and the primary submit button, labelled with a verb such as "Create project" or "Save changes". The bar SHALL stay visible at the bottom of the viewport while a long form scrolls.

#### Scenario: Long form keeps actions visible
- **WHEN** a user scrolls the middle of the release form on a 900px-tall screen
- **THEN** the Cancel and Save buttons remain visible

#### Scenario: Submit labels
- **WHEN** the new-team page renders
- **THEN** the primary button reads "Create team"
- **WHEN** the edit-team page renders
- **THEN** it reads "Save changes"
