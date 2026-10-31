## ADDED Requirements

### Requirement: Competency catalog
The system SHALL provide a catalog of performance competencies. Each competency SHALL have a stable key, a name, an optional description, a category (Behavioral, Technical, Delivery, or Growth), a role scope (developer, QA, or both), a cadence (daily or weekly), a weight, an active flag, and a display order. A competency SHALL apply to a member when its role scope is `both` or matches the member's role.

#### Scenario: Catalog defines a competency
- **WHEN** a competency exists with role scope developer and cadence weekly
- **THEN** it applies to developers on the weekly evaluation and not to QA members

#### Scenario: Shared competency applies to both roles
- **WHEN** a competency has role scope `both`
- **THEN** it applies to both developer and QA members for its cadence

### Requirement: Seeded default competencies
The system SHALL seed a default set of competencies on install so evaluation is usable without manual setup. The default set SHALL include, at minimum: Code Quality (technical, developer), Problem Solving (technical, both), Task Completion (delivery, both), Understanding & Requirements (delivery, both), Behavior & Professionalism (behavioral, both), Communication & Collaboration (behavioral, both), Learning Progress (growth, both), Ownership & Discipline (behavioral, both), Test Thoroughness (technical, QA), Defect Detection (technical, QA), and Attention to Detail (technical, QA). Each seeded competency SHALL have a defined cadence, with at least one daily and at least one weekly competency present.

#### Scenario: Defaults available after install
- **WHEN** the application is seeded
- **THEN** the default competencies exist and are active, spanning all four categories, both roles, and both cadences

#### Scenario: QA-only competency excluded for developers
- **WHEN** a developer is evaluated
- **THEN** QA-only competencies such as Defect Detection do not appear for that member

### Requirement: Competency management restricted to admin and CTO
The system SHALL allow only admins and CTOs to create, edit, reorder, activate, or deactivate competencies. The system SHALL prevent team leads, developers, QA, and viewers from managing the catalog. Weight SHALL be a positive value and cadence SHALL be daily or weekly.

#### Scenario: Admin creates a competency
- **WHEN** an admin creates a competency with a name, category, role scope, cadence, and weight
- **THEN** it is added to the catalog and becomes available for evaluation

#### Scenario: Team lead cannot manage the catalog
- **WHEN** a team lead attempts to create or edit a competency
- **THEN** the system forbids the action

#### Scenario: Invalid weight rejected
- **WHEN** a competency is saved with a zero or negative weight
- **THEN** the system rejects it with a validation error

### Requirement: Deactivating a competency preserves history
The system SHALL exclude a deactivated competency from new evaluations while retaining all previously recorded scores for it. Analytics and score history SHALL continue to reflect scores recorded against a competency that was later deactivated.

#### Scenario: Deactivated competency drops off the grid
- **WHEN** a competency is deactivated
- **THEN** it no longer appears on the evaluation grid for new periods

#### Scenario: Historical scores remain
- **WHEN** a competency with existing scores is deactivated
- **THEN** those scores still appear in member and team analytics and in score history
