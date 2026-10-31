## Why

Notes and meeting minutes are often typed quickly: fragments, typos, no structure. Before saving, users want to polish and organize what they wrote, without rewriting it by hand or copying it into another tool.

## What Changes

- **New "Enhance with Claude" button** next to the rich-text editor in:
  - the new-note composer and the inline note editor (Notes page);
  - the meeting-note form, used by both create and edit.
- **What a click does:** the current editor content is sent to Claude, which returns a polished, organized version, and the editor content is replaced in place. **Nothing is saved**: the user reviews and still presses Save.
  - **Notes:** fix spelling and grammar, tighten wording, and group related points into short paragraphs or bullet lists. Meaning, names, numbers and links are kept; nothing is invented.
  - **Meetings:** the same polish, organized into the sections the content supports (Summary, Discussion, Decisions, Action items with owners and dates when they are mentioned). The meeting title and type are given as context.
- **Undo:** "Undo" restores the previous text in one click, and the editor's own undo (⌘Z) works too.
- **States:**
  - busy ("Enhancing…"), with the editor locked while the request runs;
  - an error message if Claude is unavailable or not configured;
  - disabled while the editor is empty.
- **Backend:** a JSON endpoint, `POST ai/enhance`, for any signed-in user. It is rate-limited per user, and its output is sanitized with the existing `HtmlSanitizer` to the tags Trix supports.
- **Configuration:** `services.anthropic.enhance_model` (default `claude-opus-5-5`) and `enhance_effort` (default `low`), reusing `ANTHROPIC_API_KEY` and the `anthropic-ai/sdk` integration.

## Capabilities

### New Capabilities
- `ai-enhance-notes`: Claude-powered enhancement of note and meeting-note text before saving. It covers the endpoint, the output rules, the editor integration (replace/undo/states), limits and failures.

### Modified Capabilities

<!-- None (no archived specs). -->

## Impact

- **Backend:**
  - `App\Services\Ai\TextEnhancer` interface and `ClaudeTextEnhancer`, bound in `AppServiceProvider`;
  - `App\Http\Controllers\AiEnhanceController`;
  - an `ai-enhance` rate limiter;
  - a route in the authenticated group.
- **Frontend:** a new `x-ai-enhance` Blade component, plus an Alpine `aiEnhance` component in `resources/js/app.js`. Used in `notes/index`, `notes/_card` and `meeting-notes/form`.
- **Config:** two keys plus `.env.example` entries.
- **External:** one Claude call per click; the note text is sent to Anthropic.
- **Tests:** feature tests with a fake enhancer.
