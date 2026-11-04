## Why

The AI features each sit on their own button or page. Nobody can say "add a design review tomorrow at 3 and put it on my plan" and have it done. People want one companion on every page that answers questions and does the routine work for them, and leads want it to help them run a team.

## What Changes

- A launcher fixed to the bottom right of every signed-in page opens a chat popup. It stays open, with its conversation, across page changes, and becomes a full-screen sheet on phones.
- The chat answers from what the signed-in person may see, through read tools that reuse the existing scopes (`visibleTo`, `WorkspaceAsk::retrieve`).
- It can propose changes: create an event, create a task, add to a tasksheet plan or result, change or assign a task, comment, log time, add a note. Leads also get team overviews, release facts and posted release status updates.
- **Nothing changes until the person clicks.** A proposed change is shown as a draft of the real record, and only a person's click runs it. Every proposal is checked against the same form rules and policies as the page it mirrors, when proposed and again when confirmed.
- New `assistant` AI feature, switchable at `/ai/usage`, with its calls logged. `AiGateway` and `AiTransport` gain a tool-use call, streaming its text.
- New tables for conversations and pending actions, pruned with the other logs.
- Web only for now. No API endpoints, no deleting anything, no bulk changes. See the design for why.

## Capabilities

### New Capabilities
- `ai-assistant`: the floating chat, its read tools, and confirmed actions.

### Modified Capabilities
<!-- None: `openspec/specs/` has no base specs yet. -->

## Impact

- **Data**: new `assistant_conversations`, `assistant_actions`.
- **Backend**: `AiGateway::converse`, `AiTransport::converse`, `ClaudeTransport`, `App\Services\Ai\Assistant\*`, `AssistantController`, `AssistantActionPolicy`, routes in `routes/ai.php`, feature key in `config/ai.php`, `routes/console.php` pruning.
- **Frontend**: `layouts/app` gets a small launcher; the panel's HTML, JS and CSS load the first time it opens. `resources/js/assistant.js`, `resources/css/assistant.css`, `resources/views/assistant/*`.
- **Tests**: `tests/Feature/Ai/AssistantTest.php`, plus `FakeAiTransport` learning to answer tool-use calls.
