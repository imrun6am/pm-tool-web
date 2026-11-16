# Claude Code activity monitor

Records which device uses Claude Code on your account, and when. Each monitored
device posts an event to the PM tool on every session start, prompt and session
end. You read them at **Claude activity** in the sidebar (`/claude-activity`).

The log is append-only: the app has no way to edit or delete a row, so an entry
stays even after the conversation is deleted from Claude.

## What is recorded

| Field | Source |
|---|---|
| Event, session id, project folder | Claude Code's hook input |
| First 300 characters of each prompt | Claude Code's hook input (`CLAUDE_MONITOR_PROMPT_CHARS`) |
| Computer name, OS user, OS, entry point | the device |
| Device id | random id fixed when the hook is installed |
| **IP address** | as seen by the server, so the device can't fake it |

## Server setup

1. Run the migration: `php artisan migrate`
2. Add to `.env` (generate the token with `openssl rand -hex 32`):

   ```
   CLAUDE_MONITOR_TOKEN=<long random secret>
   CLAUDE_MONITOR_VIEWERS=you@example.com
   ```

   `CLAUDE_MONITOR_VIEWERS` lists who may read the log. Leave it blank and
   every PM tool admin can read it.
3. `php artisan config:cache` if config is cached.

## Device setup

Copy this folder to each device, then:

- **macOS / Linux:** `sudo ./install.sh https://your-pm-tool.example.com <token>`
- **Windows** (PowerShell as Administrator):
  `powershell -ExecutionPolicy Bypass -File .\install.ps1 -Url https://your-pm-tool.example.com -Token <token>`

The installer writes the hook into Claude Code's **managed settings**:

- macOS: `/Library/Application Support/ClaudeCode/`
- Linux: `/etc/claude-code/`
- Windows: `C:\Program Files\ClaudeCode\`

Only an administrator can change files there, and a user's own settings can't
turn managed hooks off. The installer sends a test event, so the device appears
on the page straight away. Restart open Claude Code sessions afterwards.

## Limits

- It covers Claude Code: the terminal, the desktop app's Code tab and Remote
  Control sessions on a monitored device. It can't see claude.ai chat in a
  browser or the mobile app.
- A device you didn't install it on reports nothing. If your usage goes up
  with no matching events here, the activity came from such a device.
- Anyone with admin rights on a monitored device can remove the hook. What
  it already sent stays on the server.
- An event sent while the device is offline is lost.

## Uninstall

Delete `managed-settings.json` and `claude-monitor-hook.sh` (`.ps1` on Windows)
from the folder above.
