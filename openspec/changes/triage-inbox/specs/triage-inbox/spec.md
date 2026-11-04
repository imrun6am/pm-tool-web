## ADDED Requirements

### Requirement: Anyone can send a request
Any signed-in user SHALL be able to send a request, and see what they've sent and its outcome. Developers and QA SHALL only send to their own teams. A request SHALL NOT appear on boards or in task counts.

#### Scenario: Send
- **WHEN** a viewer sends a request
- **THEN** it's stored as pending and listed under what they've sent

#### Scenario: Other team
- **WHEN** a developer names another team
- **THEN** the system refuses it

### Requirement: Leads decide
Leads SHALL see waiting requests they may decide, oldest first. Org-level leads SHALL decide any request; a team lead SHALL decide their own team's and those with no team. Others SHALL get a 403. A decision SHALL be final.

#### Scenario: Second decision
- **WHEN** someone decides a request already decided
- **THEN** the system answers 409 and changes nothing

### Requirement: Accepting creates a task
Accepting SHALL create a task with the request's details, filed by the reporter, with the team, release, assignee, priority and due date the lead chose. The assignee SHALL be active and on the release's team if there is one.

#### Scenario: Accept
- **WHEN** a lead accepts a request
- **THEN** a task exists, linked to the request, and the reporter is told

### Requirement: Declining gives a reason
Declining SHALL require a reason, which the reporter SHALL see.

#### Scenario: No reason
- **WHEN** a lead declines with no reason
- **THEN** the system refuses it and the request stays pending

### Requirement: People are told
A new request SHALL notify the team's lead, or org-level leads if it has no team or lead, and never the sender. Requests waiting over the configured time SHALL be flagged.

#### Scenario: Long wait
- **WHEN** a request has waited over 48 hours
- **THEN** it's flagged in the queue
