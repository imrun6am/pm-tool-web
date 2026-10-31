## Context

- **Editors:** notes and meeting notes use Trix editors bound to hidden inputs. The model setters run `HtmlSanitizer::clean`, whose allow-list covers what Trix emits.
- **Existing Claude integration:** the "Add to board" feature already uses `anthropic-ai/sdk`, with an interface plus a Claude implementation bound in `AppServiceProvider` and the shared `MissingApiKeyException`.
- **What's different here:** the result must land in the editor *before* saving, so this feature uses a JSON endpoint rather than a form post.

## Goals / Non-Goals

**Goals:**
- one-click polish of the current editor content, reviewable before saving;
- works in create and edit flows for notes and meetings;
- safe output and easy undo.

**Non-Goals:**
- streaming the output token by token;
- automatic enhancement on save;
- translation and tone presets (possible follow-up);
- enhancing titles.

## Decisions

### 1. JSON endpoint
`POST ai/enhance` (`ai.enhance`) → `AiEnhanceController`.
- Validation: `kind` in `note|meeting`; `body` required, string, max 20,000, and not visually empty; `title` and `type` nullable strings.
- Responses: `{html}` on success; 503 `{message}` for a missing key or a Claude failure; 422 for invalid input.
- Middleware: `throttle:ai-enhance`, which is 10 per minute per user id.

*Alternative considered:* a form submit with a redirect. It would lose the user's unsaved state across the other fields.

### 2. TextEnhancer
`TextEnhancer::enhance(string $kind, string $html, array $context = []): string`.

`ClaudeTextEnhancer` makes one non-streaming `beta->messages->create`, following the Add-to-board extractor:
- model and effort from config;
- `maxTokens: 16000`;
- json_schema output `{html: string}`;
- `fallbacks: 'default'` with `server-side-fallback-2026-07-01`;
- a 60-second timeout;
- fallback-aware text reading and stop-reason checks.

The input is sent as the editor's HTML, which keeps the structure the user already made, wrapped in `<note>` tags. The system prompt per `kind`:
- defines the polish rules;
- forbids inventing facts;
- restricts output to Trix tags, with `h1` for section headings and `div`/`br` for lines, and no `p`, `h2` or `h3`, since Trix has one heading level;
- treats the content as data.

The returned HTML goes through `HtmlSanitizer::clean`. An empty result after sanitizing counts as unreadable.

### 3. Editor integration
`<x-ai-enhance input="<hidden input id>" kind="note|meeting" [title-field="title"] [type-field="type"] />` renders the button row. It is placed under each Trix editor.

The Alpine `aiEnhance` component:
- finds the `trix-editor[input=…]`;
- tracks emptiness through the `trix-change` event;
- on click, POSTs `{kind, body: hidden input value, title, type}` with the CSRF header.

On success it keeps `previous` = the old HTML, then calls `editor.recordUndoEntry('Enhance')`, selects all and `insertHTML(html)`, so ⌘Z works. The "Undo" link calls `editor.loadHTML(previous)`.

While busy, the editor gets `contenteditable=false` and `aria-busy`. Errors appear inline, in a `role="alert"` line.

### 4. Context for meetings
The meeting title (`#title`) and the selected type label (`#type`) are read when the button is clicked and sent as context. This helps Claude frame the Summary. They are optional.

## Risks / Trade-offs

- **Hallucinated content.**
  - *Mitigation:* strict prompt rules, the user reviews before saving, and one-click undo.
- **Cost per click.**
  - *Mitigation:* low effort, the per-user rate limit, and an explicit button.
- **Data leaves the app.** It is the same posture as the existing Claude features and uses the same key.

## Migration Plan

- No migrations.
- `ANTHROPIC_API_KEY` must be set (it already is for the other Claude features).
- Run `npm run build`.
- Rollback is a revert.
