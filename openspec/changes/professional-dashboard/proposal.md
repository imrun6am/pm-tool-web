## Why

The dashboard is titled "Release timeline", but the timeline itself, the most useful thing on the page, sits at the bottom, under four oversized KPI cards, a progress card, two charts and a loose legend. On a 900px-tall screen it isn't visible at all. The developer/QA "My day" page has the opposite problem: three sparse cards with no summary of the day. Neither page reads like the overview screen of a professional project-management tool.

## What Changes

- **Lead/viewer dashboard**, reordered so the timeline is the main element:
  - **Header:** a "Dashboard" title, the active period and its date range as the description, and "New release" for managers.
  - **Filter bar:** Group by becomes a compact Team/Project segmented toggle. Quarter stays a select.
  - **KPI strip:** one card divided into four compact stats instead of four large cards. Conflicts turn amber and link to the timeline.
  - **Timeline card:** a header holding the title, the release count, the phase legend and a conflict notice; a refined month axis; a **"Today" marker**; cleaner bars; and a standard empty state.
  - **Insights row** of three equal cards below the timeline:
    - **Delivery progress** as a donut, with the percentage done in the middle and a legend of status counts;
    - **Release load** by month, with gridlines and the current month highlighted;
    - **Team workload**, with bars in each team's color linking to the team.
- **"My day" (developer/QA):**
  - a greeting with the date;
  - a summary strip of open tasks, overdue tasks and meetings in the next 14 days;
  - a task list with a release chip and due-date pills (overdue in red);
  - tasksheet status per team;
  - upcoming meetings shown with calendar-style date tiles.
- **Nothing outside the views changes:** the controller, the service, the data passed to the views, routes and permissions all stay as they are, and every existing dashboard test passes unchanged.

## Capabilities

### New Capabilities
- `dashboard`: Layout and behavior of the lead/viewer overview and the developer/QA "My day" page. It covers section order, the KPI strip, the timeline (today marker, legend, conflicts, empty state), the insight charts, the member summary, and the existing data contract that must not change.

### Modified Capabilities

<!-- None. No archived specs exist. Builds on ui-components / page-layouts from modernize-ui-components-and-pages. -->

## Impact

- **Views:** `resources/views/dashboard.blade.php` and `resources/views/dashboard-member.blade.php`.
- **CSS:** a small `.segmented` control and a `.stat-strip` layout in `resources/css/app.css`.
- **Tests:** new feature tests for section order, the today marker and the member summary. Existing tests (quarter `value="N" selected`, completed releases hidden, member tasksheet wording, web/API parity) must pass untouched.
- **No backend or dependency changes.**
