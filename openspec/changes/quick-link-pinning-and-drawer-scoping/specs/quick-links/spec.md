## ADDED Requirements

### Requirement: Pin a quick link
The system SHALL allow a user to pin and unpin any quick link that user is permitted to see, including links shared by other users. A pin SHALL be recorded per viewer: pinning or unpinning a link SHALL NOT change what any other user sees. The system SHALL NOT require ownership of a link in order to pin it, and SHALL NOT grant any editing ability through pinning.

#### Scenario: Pin your own link
- **WHEN** a user pins one of their own links
- **THEN** the link is marked as pinned for that user

#### Scenario: Pin a link shared by someone else
- **WHEN** a user pins a link shared by another user
- **THEN** the link is pinned for that user only, and the link's author sees no change

#### Scenario: Unpin
- **WHEN** a user unpins a link they had pinned
- **THEN** the link returns to its unpinned position for that user

#### Scenario: Pinning is not editing
- **WHEN** a user pins a link authored by someone else
- **THEN** the user still cannot edit or delete that link

#### Scenario: Cannot pin an invisible link
- **WHEN** a user attempts to pin a link they are not permitted to see
- **THEN** the system refuses the action

### Requirement: Quick-links drawer ordering
The quick-links drawer SHALL order the links it shows as pinned first, then private, then shared, with the most recently added first within each band. This ordering SHALL apply to both the viewer's own links and the links shared by others.

#### Scenario: Pinned links lead
- **WHEN** a user opens the drawer with at least one pinned link
- **THEN** the pinned links appear above the unpinned ones

#### Scenario: Private above shared
- **WHEN** a user has both private and shared links of their own, none pinned
- **THEN** the private ones appear above the shared ones

#### Scenario: A pin outranks the private/shared split
- **WHEN** a user pins one of their shared links while holding unpinned private links
- **THEN** the pinned shared link appears above the unpinned private links

#### Scenario: Recency within a band
- **WHEN** a user holds several links within the same band
- **THEN** the most recently added appears first

### Requirement: Completed-release links leave the drawer
The quick-links drawer SHALL NOT list a link attached to a release that has been completed. The system SHALL NOT delete such links, and SHALL list them again — retaining any pin — if the release is reopened. Links attached to no release SHALL be unaffected.

#### Scenario: Release completes
- **WHEN** a release with attached quick links is completed
- **THEN** those links no longer appear in the drawer

#### Scenario: Release is reopened
- **WHEN** a completed release with attached quick links is reopened
- **THEN** those links appear in the drawer again, with any pin intact

#### Scenario: Unattached links are unaffected
- **WHEN** a release is completed
- **THEN** links attached to no release, and links attached to other ongoing releases, still appear in the drawer

#### Scenario: The link is not destroyed
- **WHEN** a link's release is completed
- **THEN** the link still exists and is still reachable from that release's details page

### Requirement: Release details keep their links after completion
The release details page SHALL continue to list the quick links attached to a release after that release is completed, so the links remain part of the release's record and remain actionable by their author.

#### Scenario: Completed release still lists its links
- **WHEN** a user views a completed release that has attached quick links
- **THEN** the links card lists them

#### Scenario: Author can still act on the link
- **WHEN** the author views a completed release holding a link they created
- **THEN** they can still reach that link to edit or delete it
