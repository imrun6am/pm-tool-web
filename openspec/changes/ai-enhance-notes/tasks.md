## 1. Backend

- [x] 1.1 Config `services.anthropic.enhance_model` / `enhance_effort` (+ `.env.example`)
- [x] 1.2 `TextEnhancer` interface + `ClaudeTextEnhancer` (per-kind prompt, Trix-tag output, json_schema, refusal fallback, stop checks, sanitize), bound in `AppServiceProvider`
- [x] 1.3 `ai-enhance` rate limiter (10/min per user), `AiEnhanceController` + `POST ai/enhance` route (validation, 503 on missing key or failure)

## 2. Frontend

- [x] 2.1 Alpine `aiEnhance` component: empty tracking, POST with CSRF, undoable replace (`recordUndoEntry` + `insertHTML`), one-click Undo, busy lock, inline error
- [x] 2.2 `x-ai-enhance` Blade component (button + status row), placed in the notes composer, the note inline editor, and the meeting-note form (create + edit) with title/type context

## 3. Tests & Verification

- [x] 3.1 Feature tests with a fake enhancer: returns sanitized HTML, passes kind/context, empty body 422 without a call, invalid kind 422, guest redirected, missing key 503, rate limit 429, buttons render in all three places
- [x] 3.2 Run `php artisan test`: the full suite passes
- [x] 3.3 Live smoke call (sample note and sample minutes) and a browser check: enhance → editor replaced → Undo restores, in the composer, the inline edit, and the meeting edit
