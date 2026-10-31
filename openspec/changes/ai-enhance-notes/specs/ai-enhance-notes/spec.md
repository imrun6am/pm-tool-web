## ADDED Requirements

### Requirement: Enhance button on note and meeting editors
An "Enhance with Claude" button SHALL appear with the rich-text editor in:
- the new-note composer;
- each note's inline editor;
- the meeting-note create and edit form.

Clicking it SHALL send the editor's current content to the server and replace the editor's content with the enhanced version. It SHALL NOT save the note or meeting.

#### Scenario: Polish before saving
- **WHEN** a user writes rough notes in the composer and clicks "Enhance with Claude"
- **THEN** the editor shows the polished version, and nothing is saved until they press "Add note"

#### Scenario: While editing an existing note
- **WHEN** a user opens an existing note's inline editor and clicks "Enhance with Claude"
- **THEN** that editor's content is replaced with the enhanced version, and the note is unchanged until they press Save

### Requirement: Faithful, organized output
The enhanced text SHALL keep the original meaning, facts, names, numbers, dates and links, and SHALL NOT add information that wasn't in the input. It SHALL fix spelling and grammar, and organize the content into short paragraphs, bullet lists and headings. For meetings, it SHALL use sections such as Summary, Discussion, Decisions and Action items only when the content supports them, keeping owners and due dates where they are stated. The output SHALL contain only editor-supported HTML (h1, div, br, strong, em, del, a, ul, ol, li, blockquote, pre), and the server SHALL sanitize it before returning.

#### Scenario: Meeting minutes
- **WHEN** rough minutes mention "Sara will send the API spec by Friday"
- **THEN** the enhanced version lists that under Action items with Sara and Friday preserved

### Requirement: Undo
After an enhancement, the user SHALL be able to restore the previous content with one click on "Undo". The editor's own undo SHALL also revert the replacement.

#### Scenario: Undo enhancement
- **WHEN** the user clicks "Undo" after enhancing
- **THEN** the editor shows exactly the content from before the enhancement

### Requirement: States and limits
The button SHALL be disabled while the editor is empty and while a request is running; the running state SHALL read "Enhancing…". The endpoint SHALL require authentication, accept `kind` (`note` or `meeting`), `body` (at most 20,000 characters, with visible text), and an optional `title` and `type`. It SHALL be rate-limited per user (10 requests per minute).

#### Scenario: Empty editor
- **WHEN** the endpoint receives a body with no visible text
- **THEN** it responds 422 and no Claude request is made

#### Scenario: Too many requests
- **WHEN** a user sends more than 10 enhance requests within a minute
- **THEN** further requests receive 429

### Requirement: Failures
When Claude is not configured, unreachable, declines, or returns unreadable output, the endpoint SHALL respond with an error status and a human-readable message, and the editor content SHALL stay unchanged.

#### Scenario: Missing API key
- **WHEN** `ANTHROPIC_API_KEY` is not set
- **THEN** the endpoint responds 503 with "Claude isn't configured", and the editor keeps the user's text
