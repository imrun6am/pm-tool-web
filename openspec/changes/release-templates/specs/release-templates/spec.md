## ADDED Requirements

### Requirement: Leads manage templates
Leads SHALL be able to add and remove templates with unique names, and add and remove their tasks, each with an optional phase, priority and estimate. Others SHALL get a 403.

#### Scenario: Duplicate name
- **WHEN** a lead adds a template with a name already used
- **THEN** the system refuses it

### Requirement: Applying to a release
A lead SHALL be able to add a template's tasks to a release. Each task SHALL be due when its phase ends, and titles the release already has SHALL be skipped.

#### Scenario: Apply
- **WHEN** a lead applies a template with a QA task
- **THEN** the release has that task, due when its QA phase ends

#### Scenario: Apply again
- **WHEN** the same template is applied again
- **THEN** no duplicates are created

### Requirement: Removing a template
Removing a template SHALL keep tasks it already added.

#### Scenario: Remove
- **WHEN** a lead removes a template
- **THEN** releases keep their tasks
