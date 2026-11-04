## Why

Most releases need the same tasks: regression passes, sign-offs, release notes. Leads retype them each time, and steps get missed.

## What Changes

- Leads manage templates: named task sets, each task with an optional phase, priority and estimate.
- A release's page can add a template's tasks in one click. Each task is due when its phase ends, and titles the release already has are skipped.
- Web page and API.

## Capabilities

### New Capabilities
- `release-templates`: standard task sets applied to a release.

### Modified Capabilities
<!-- None: `openspec/specs/` has no base specs yet. -->

## Impact

- **Data**: new `release_templates`, `release_template_tasks`.
- **Backend**: models, `ReleaseTemplateService`, request, controllers.
- **Frontend**: new `release-templates/index`, and a control on the release page.
