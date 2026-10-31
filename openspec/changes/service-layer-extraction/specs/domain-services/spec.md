## ADDED Requirements

### Requirement: One implementation per domain behaviour
The system SHALL implement each domain behaviour exactly once, in a shared class, whenever that
behaviour is reachable from more than one delivery mechanism. A behaviour available through both
the web application and the REST API SHALL resolve to a single code path. The system SHALL NOT
carry a second copy of a query, a calculation, or a write sequence in a parallel controller.

#### Scenario: A rule reachable from both surfaces has one implementation
- **WHEN** a behaviour is available through both the web app and the API
- **THEN** both delivery layers call the same service method, and neither contains its own copy of the query or calculation

#### Scenario: Fixing a rule fixes it everywhere
- **WHEN** a defect in a shared domain rule is corrected in its service
- **THEN** the correction takes effect on both the web app and the API without a second edit

#### Scenario: Adding a delivery mechanism adds no logic
- **WHEN** a third consumer of an existing behaviour is added
- **THEN** it calls the existing service and introduces no new copy of the rule

### Requirement: Controllers are thin
The system SHALL confine a controller to resolving its input, delegating to a service, and
presenting the result. A controller SHALL NOT build a domain query, perform date or geometry
arithmetic, sequence a multi-step write, or decide a domain guard. Reading request parameters,
authorizing, choosing a response shape, and mapping to a view or a resource SHALL remain the
controller's work.

#### Scenario: Controller delegates a query
- **WHEN** an endpoint lists filtered records
- **THEN** the controller passes the resolved filters to a service and does not assemble the query itself

#### Scenario: Controller delegates a multi-step write
- **WHEN** saving a record also reconciles its child records
- **THEN** the controller calls one service method and the service owns the ordering and the transaction

#### Scenario: Presentation stays in the controller
- **WHEN** the same service result is served to a Blade view and to a JSON client
- **THEN** each delivery layer maps the result to its own format and the service is unaware of either

### Requirement: Services return domain values, not formatted output
A service SHALL return Eloquent models, Carbon instances, collections, or plain arrays of those.
A service SHALL NOT return HTTP responses, redirects, API resources, rendered views, or
format-specific field names. A service SHALL NOT read the request or the authenticated session
directly; anything it needs about the actor SHALL be passed in as an argument.

#### Scenario: Service returns a model, not a resource
- **WHEN** a service creates a record
- **THEN** it returns the model, and the caller decides whether that becomes a view, a redirect, or a JSON resource

#### Scenario: Service takes the actor as an argument
- **WHEN** a behaviour depends on who is acting
- **THEN** the acting user is passed in as a parameter rather than read from the request inside the service

#### Scenario: Dates are returned unformatted
- **WHEN** a service computes a date or a period
- **THEN** it returns Carbon instances and leaves string formatting to the delivery layer

### Requirement: Authorization stays outside the services
The system SHALL keep authorization in middleware, policies, and gates. A service SHALL NOT decide
whether the actor is permitted to perform the operation. A service MAY apply visibility scoping to
a query for a given user — restricting *which records* are returned — since that shapes the result
rather than granting or refusing the action.

#### Scenario: Permission is decided before the service is called
- **WHEN** an actor lacks permission for an operation
- **THEN** the request is refused by middleware or a policy and the service is never reached

#### Scenario: Visibility scoping is allowed in a service
- **WHEN** a service builds a collection whose records differ per viewer
- **THEN** it scopes the query to that viewer, and the records the viewer may not see are never loaded

### Requirement: Extracted calculations are independently testable
The system SHALL make each extracted calculation testable without an HTTP request. A service
method that performs date arithmetic, geometry, grouping, or aggregation SHALL be callable directly
in a test.

#### Scenario: Calculation tested without a request
- **WHEN** a timeline, trend, or roll-up calculation is under test
- **THEN** the service method is called directly and asserted on its return value, with no route involved

### Requirement: Behaviour is preserved by the extraction
The system SHALL keep every existing outcome unchanged when logic moves into a service: the same
routes, request shapes, validation messages, JSON payloads, view data, permissions, and recorded
activity. The existing test suite SHALL pass without modification to its assertions.

#### Scenario: Existing tests pass unchanged
- **WHEN** logic is moved out of a controller into a service
- **THEN** the pre-existing tests for that behaviour pass without their assertions being altered

#### Scenario: The published API contract is unaffected
- **WHEN** an API endpoint's logic moves into a service
- **THEN** its JSON payload keeps the same keys, types, and values
