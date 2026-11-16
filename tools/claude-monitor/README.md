# Claude Code activity monitor

Records which device uses Claude Code on your account, and when. Each monitored
device posts an event to the PM tool on every session start, prompt, model
switch, browser action and session end. You read them at **Claude activity** in the sidebar (`/claude-activity`).

Nothing can edit an entry, so it stays even after the conversation is deleted
from Claude. Whoever may read the log can delete entries: a whole device from
the device list (it comes back if it reports again), or on a device's page one
event at a time, the ticked ones, or every event matching the filters.
Deleting a whole device, or every match, asks for your password. Each
deletion is noted in the app's log (`storage/logs`) with who did it and how
many.

The **Claude activity** page lists the devices, with the IP addresses, models
and latest MAC address each one used. Click **Show events** on one for its own
page: its names, IP and MAC addresses, models, counts, and its events with
filters for text, event, OS user, entry point, model, IP, MAC address, session
and dates.

Click an event for a drawer with everything recorded about it.

**Habit reports** (button on the Claude activity page): link each device to the
person who uses it, then ask for an AI review of that person's prompts and
sessions over a period: what kind of work they ask for, which projects, good
practice, mistakes, where they went in circles, and what to improve. The
counts are worked out by the app; the AI reads only the stored start of each
prompt, never Claude's answers. It's written in the background (the queue
worker must be running) and is switched on or off, with its model, on the AI
usage page as "Claude Code habit reports".

**AI assistant:** admins and the CTO can ask the assistant about Claude Code
usage ("who used Claude Code most this week?", "what did Rahim work on
yesterday?"). It reads the same log, by person, device, period or text.

## What is recorded

| Field | Source |
|---|---|
| Event, session id, project folder | Claude Code's hook input |
| First 300 characters of each prompt | Claude Code's hook input (`CLAUDE_MONITOR_PROMPT_CHARS`, at most 1,000) |
| Computer name, OS user, OS, entry point | the device (on a Mac, the name set in System Settings) |
| Model | Claude Code's hook input: on session start and on each model switch (`/model`); prompts and session ends take the latest model their session reported. Sessions started before the hook was installed show none |
| Browser actions | Claude Code's hook input, for each Claude in Chrome tool Claude Code uses: the tool and action (`navigate`, `computer: left_click`, …) and the page address without its query string. Text typed and scripts run in the page aren't kept |
| MAC address | the device: the adapter its default route uses (on a Mac, the hardware address, not a private Wi-Fi one). Reported by the device, so it could be faked |
| Device id | random id fixed when the hook is installed |
| **IP address** | as seen by the server, so the device can't fake it |

## Server setup

1. Run the migration: `php artisan migrate`
2. Add to `.env` (generate the token with `openssl rand -hex 32`):

   ```
   CLAUDE_MONITOR_TOKEN=<long random secret>
   CLAUDE_MONITOR_VIEWERS=1
   ```

   `CLAUDE_MONITOR_VIEWERS` lists the user ids that may read the log,
   comma-separated. A user's id is the number in the address of their page
   under **Users** (`/users/1/edit`). Ids, not emails: anyone can change their
   own email in Profile, so an email no account holds yet would let in
   whoever takes it first. An entry that isn't an id matches nobody. Leave it
   blank and every PM tool admin can read the log.
3. `php artisan config:cache` if config is cached.

## Device setup

Copy this folder to each device, then:

- **macOS / Linux:** `sudo ./install.sh https://your-pm-tool.example.com` (asks for the token, hidden)
- **Windows** (PowerShell as Administrator):
  `powershell -ExecutionPolicy Bypass -File .\install.ps1 -Url https://your-pm-tool.example.com`

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
- Claude in Chrome shows up only when Claude Code drives Chrome through it.
  Chatting with the extension directly in Chrome doesn't run Claude Code's
  hooks, so it isn't recorded; your Claude account's admin tools are the place
  to look for that.
- A device you didn't install it on reports nothing. If your usage goes up
  with no matching events here, the activity came from such a device.
- Anyone with admin rights on a monitored device can remove the hook. What
  it already sent stays on the server.
- An event sent while the device is offline is lost.
- The token is the same on every device and sits in the hook, which every
  user on the device can read (the hook runs as them). Anyone holding it can
  post events with any device name, so the **IP address** is the one field
  they can't fake. If a device leaks it, set a new `CLAUDE_MONITOR_TOKEN` and
  reinstall on every device.
- The hook never holds up Claude Code: on macOS and Linux it posts in the
  background, and on Windows it hands the event to a hidden copy of itself.

## Uninstall

`sudo ./install.sh --uninstall` (`.\install.ps1 -Uninstall` on Windows). It
removes the hook and the managed settings.
