## ADDED Requirements

### Requirement: Versioned API namespace
The system SHALL expose the API under a version prefix of `/api/v1`. Every API route SHALL live under that prefix, and the version SHALL be part of the URL path so a future `/api/v2` can be introduced alongside it without changing existing clients. API routes SHALL be registered from route files separate from the web routes, and `routes/web.php` SHALL remain unchanged in behaviour.

#### Scenario: Endpoint reachable under the version prefix
- **WHEN** a client requests a documented endpoint under `/api/v1`
- **THEN** it is routed to the v1 API and never to a web route

#### Scenario: Web app unaffected
- **WHEN** the API is installed
- **THEN** every existing web route continues to behave exactly as before

### Requirement: Stateless JSON transport
The system SHALL treat every API request as stateless: authentication SHALL come from a bearer token, not a session cookie, and no CSRF token SHALL be required. The API SHALL respond with JSON for every outcome, including errors, regardless of the client's `Accept` header.

#### Scenario: No session or CSRF needed
- **WHEN** a client sends a valid bearer token with no cookies and no CSRF token
- **THEN** the request is authenticated and processed

#### Scenario: Errors are JSON
- **WHEN** any API request fails, including an unhandled server error or a 404
- **THEN** the response body is JSON and not an HTML error page

### Requirement: Single-resource envelope
The system SHALL wrap a single resource in a top-level `data` object. Date-only fields SHALL be serialized as `YYYY-MM-DD` and timestamps as ISO-8601 strings, so clients in any timezone parse them unambiguously.

#### Scenario: Resource is wrapped
- **WHEN** a client fetches one release
- **THEN** the response body is `{"data": { ...release fields... }}`

#### Scenario: Dates are unambiguous
- **WHEN** a resource carries a date-only field and a timestamp field
- **THEN** the date is `YYYY-MM-DD` and the timestamp is ISO-8601

### Requirement: Collection envelope and pagination
The system SHALL wrap collections in a top-level `data` array. Collections that can grow without bound SHALL be paginated and SHALL include `meta` (current page, per page, total, last page) and `links` (first, last, prev, next). Clients SHALL be able to request a page with `page` and a page size with `per_page`, and the system SHALL cap `per_page` at a maximum so a client cannot request an unbounded page. Fixed-size collections that are meaningless when split — such as the four phases of a release — SHALL be returned unpaginated.

#### Scenario: Paginated collection carries meta and links
- **WHEN** a client lists releases
- **THEN** the response contains a `data` array plus `meta` and `links` describing the pagination

#### Scenario: Page size is capped
- **WHEN** a client requests a `per_page` above the maximum
- **THEN** the system serves the capped page size rather than the requested one

#### Scenario: Bounded sub-collection is not paginated
- **WHEN** a client fetches a release's phases
- **THEN** all four phases are returned in one unpaginated `data` array

### Requirement: Error format and status codes
The system SHALL return errors as JSON with a human-readable `message`, and, for validation failures, an `errors` object keyed by field name with an array of messages per field. The system SHALL use conventional status codes: 401 when no valid token is presented, 403 when the authenticated user lacks permission, 404 when a record does not exist or is not visible to the caller, 422 for validation failures, and 429 when throttled.

#### Scenario: Validation failure is field-keyed
- **WHEN** a client posts a release with a missing name and an end date before the start date
- **THEN** the response is 422 with an `errors` object naming `name` and `end_date`

#### Scenario: Unauthenticated request
- **WHEN** a client sends no token or an invalid token
- **THEN** the response is 401 with a `message` and no resource data

#### Scenario: Forbidden request
- **WHEN** an authenticated user requests something their role does not permit
- **THEN** the response is 403 with a `message` explaining the restriction

### Requirement: Consistent write responses
The system SHALL return the created resource with status 201 for a successful create, the updated resource with status 200 for a successful update, and status 200 with a confirmation message for a successful delete. Write endpoints that produce no resource SHALL return a `message`.

#### Scenario: Create returns the new record
- **WHEN** a lead creates a project
- **THEN** the response is 201 and its body is the created project resource

#### Scenario: Delete confirms
- **WHEN** a lead deletes a project
- **THEN** the response is 200 with a confirmation message

### Requirement: Filtering and sorting conventions
The system SHALL accept filters as query parameters named after the field they filter (for example `project_id`, `team_id`, `year`, `status`, `assignee_id`, `from`, `to`), SHALL ignore unknown query parameters rather than failing, and SHALL reject a filter whose value is of the wrong type with a 422.

#### Scenario: Filters narrow a collection
- **WHEN** a client lists releases with `team_id` and `year`
- **THEN** only releases for that team and year are returned

#### Scenario: Unknown parameter ignored
- **WHEN** a client sends a query parameter the endpoint does not define
- **THEN** the request succeeds and the parameter is ignored

### Requirement: Throttling
The system SHALL rate-limit API requests per authenticated token, and unauthenticated requests per IP address. When a caller exceeds the limit the system SHALL respond 429 and indicate when the caller may retry.

#### Scenario: Excessive requests throttled
- **WHEN** a client exceeds the request limit for its token
- **THEN** further requests receive 429 until the window resets

### Requirement: Responses never leak restricted fields
The system SHALL omit from every API payload any field the authenticated user is not permitted to see, rather than returning it and relying on the client to hide it. This SHALL apply at minimum to tasksheet lead feedback, performance scores and their private notes, and any record whose visibility rules exclude the caller.

#### Scenario: Feedback hidden from a non-lead
- **WHEN** a developer fetches a tasksheet row that carries lead feedback
- **THEN** the feedback field is absent from the payload

#### Scenario: Lead sees the restricted field
- **WHEN** a lead fetches the same tasksheet row
- **THEN** the feedback field is present

### Requirement: Domain metadata endpoint
The system SHALL expose an endpoint returning the domain's enumerations so a client renders menus and pickers without hard-coding them. It SHALL include at minimum: user roles, task statuses with their display colors, release phases with their labels and colors, calendar event types with their colors, daily-note visibilities, meeting-note visibilities, quick-link visibilities, tasksheet leave types, performance competency categories, role scopes and cadences, and the 1–5 performance scale with its anchor labels.

#### Scenario: Client renders pickers from metadata
- **WHEN** a client fetches the metadata endpoint
- **THEN** it receives every enumeration with machine keys and human labels

#### Scenario: New enum value reaches clients without a client release
- **WHEN** a value is added to a domain enumeration server-side
- **THEN** the metadata endpoint includes it on the next request
