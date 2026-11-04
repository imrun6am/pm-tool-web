## Context

`AiGateway::json()` is one-shot structured output. Ask AI (`WorkspaceAsk`) already retrieves records through `visibleTo` scopes and renders cited, escaped answers. Writes live behind form requests, policies and services, and some rules live only in a form request (`KeepsTasksInViewerTeams`, `TaskTimeLogRequest`'s day total). Turbo swaps `<body>` on every visit.

## Goals / Non-Goals

**Goals:** one assistant on every signed-in page; a person's click before any change; no route around the rules a person hits; no noticeable weight for someone who never opens it.

**Non-Goals:** API endpoints (streaming and confirmation need a client design; the services stay reusable), deleting records, bulk changes, file uploads, leaving a conversation running when nobody is signed in, showing scores or lead feedback.

## Decisions

### Native tool use behind the gateway, not a JSON-mode imitation
`AiGateway::converse()` runs the same switches and usage logging as `json()`, and `AiTransport::converse()` sends tools and streams text through a callback. Read tools run straight away and write tools only propose. Tools aren't `strict`: inputs go through the form rules anyway, and a failed rule goes back to the model as a tool error it can fix or ask about.

### Confirmation lives on the server
A proposed change is a row in `assistant_actions`: the person, the tool, the checked input and a preview built from the database (names come from ids, never the model's words). `POST assistant/actions/{id}/confirm` is the only way to run one, and it takes nothing but the id, so neither the model nor a tampered client can change what runs. Status moves from pending to running in one atomic update, so a double click can't run it twice. Rules and policies run again at confirm, because time has passed and the person's access may have changed. Pending actions expire after a day. Sending a new message cancels anything still pending, since "make it 4pm" should replace the draft, not sit beside it. Confirm-all is the same endpoint called once per card. Nothing skips confirmation yet; the first candidate to loosen is the person's own daily note.

### The same form rules, literally
`FormRules::check()` builds the real `FormRequest` (`EventRequest`, `TaskRequest`, `AssignTaskRequest`, `TasksheetEntryRequest`, `TaskTimeLogRequest`, `CommentRequest`, `NoteRequest`, `ReleaseStatusRequest`) with the acting user and route parameters, and runs `validateResolved()`, which covers `authorize`, `prepareForValidation`, rules and after-hooks. So a rule added to a form later applies here too. Policies the controller calls (`update` on a task, `update` on a tasksheet row, `view` on a release) are called the same way. Then the existing service does the write.

### Records are data
Three layers: the system prompt says so; the model only ever gets a proposal, so a note saying "assign this to me" can at most put a draft in front of the person, naming exactly what it will do; output is escaped, and links exist only for references the server issued (`WorkspaceAsk::render`), so there's no markdown or image channel for carrying data out. Tools leave out performance scores and lead feedback entirely.

### The transcript
Stored as JSON on `assistant_conversations` in provider-neutral blocks, one current conversation per person. A pending tool call leaves the last user message holding only the finished results; the rest are appended when the actions resolve, and the model is only called once the set is complete. The history sent is capped at the last 12 user turns, starting at a plain user message so tool pairs are never split, and results older than the last 2 turns are shortened to save tokens. Thinking blocks are replayed unchanged only within the current turn and dropped from earlier ones, so shortening never trips the API's history-editing check. Today's date and the current page go in the user message, keeping the system prompt byte-stable for caching. Conversations are pruned with the usage logs (`LogRetention`).

### Streaming
NDJSON over `fetch`: `status`, `text`, `reset` (the provider's fallback model took over), `action`, `error`, `done`. `done` carries the turn's rendered items, replacing the streamed plain text with cited HTML. A per-conversation lock answers 409 to a second send, and `ignore_user_abort` lets a turn finish if the page unloads, so a reload finds the answer.

### Surviving Turbo, staying light
The launcher is `data-turbo-permanent`, a direct child of `<body>`. Bardo moves it and leaves a clone, and Alpine treats the removal and re-add as a move, so state and the in-flight `fetch` survive. A small `assistantLauncher` component ships in the main bundle. On first open (or first hover) it dynamically imports `assistant.js` (with its own CSS) and fetches the panel's HTML, so a page view costs one button. Open state lives in `sessionStorage`, for real page loads, and the server reloads the conversation.

### Visual direction
A page of its own idea inside the app's tokens. The header and launcher use deep brand ink (`brand-950`), with violet as the existing "AI" accent, used only for the spark and citations. The signature is **the draft that becomes real**: a proposal is drawn as the record itself (a calendar leaf for an event, a board card for a task, sheet lines for a plan) with a dashed outline, and confirming turns the outline solid with a check and a link to the real thing. Answers aren't bubbled; only the person's messages are. Inter only, tabular figures in date tiles. The app is light-only today, so the panel is too. Reduced motion removes the settle animation and the ring's spin.

## Risks / Trade-offs

- **Cost**: each turn can be several model calls. Mitigated by the `ai` rate limit, 6 rounds a turn, 4,096 output tokens a round, prompt caching on tools and system, effort `low` by default, and an admin switch.
- **Long requests hold a PHP worker** while streaming. Acceptable at this team's size; the lock stops one person holding several.
- **The model picks the wrong record**: the card shows the resolved names and dates before anything runs, which is why confirmation stays on.
