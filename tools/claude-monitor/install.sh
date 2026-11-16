#!/bin/bash
#
# Claude Code activity monitor — installer for macOS and Linux.
#
# Installs a hook that reports every Claude Code session start, prompt, model
# switch, browser action (Claude in Chrome) and session end on this device to
# the PM tool's /api/claude-activity endpoint.
# It goes in Claude Code's *managed* settings, which only an administrator can
# change and which a user's own settings can't switch off.
#
# Usage:
#   sudo ./install.sh https://pm.example.com      # asks for the token (not echoed)
#   sudo ./install.sh https://pm.example.com <CLAUDE_MONITOR_TOKEN>
#   sudo ./install.sh --uninstall
#
# Or straight from GitHub, without cloning:
#   curl -fsSL https://raw.githubusercontent.com/imrun6am/claude-monitor/main/install.sh | sudo bash -s -- https://pm.example.com
#
set -euo pipefail

if [ "$(id -u)" -ne 0 ]; then
    echo "Run this with sudo: it writes Claude Code's managed settings." >&2
    exit 1
fi

case "$(uname -s)" in
    Darwin) DIR="/Library/Application Support/ClaudeCode" ;;
    Linux)  DIR="/etc/claude-code" ;;
    *)      echo "Unsupported OS. On Windows use install.ps1." >&2; exit 1 ;;
esac

if [ "${1:-}" = "--uninstall" ]; then
    if [ -f "$DIR/managed-settings.json" ] && ! grep -q "claude-monitor-hook" "$DIR/managed-settings.json"; then
        echo "$DIR/managed-settings.json isn't ours; leaving it. Removing only the hook." >&2
    else
        rm -f "$DIR/managed-settings.json"
    fi
    rm -f "$DIR/claude-monitor-hook.sh"
    echo "Claude monitor removed. Restart open Claude Code sessions."
    exit 0
fi

URL="${1:-}"
TOKEN="${2:-}"
if [ -z "$URL" ]; then
    echo "Usage: sudo $0 <pm-tool URL> [CLAUDE_MONITOR_TOKEN]" >&2
    echo "       sudo $0 --uninstall" >&2
    exit 1
fi
# Ask for the token on the terminal (works under `curl | sudo bash` too), so it
# stays out of shell history.
if [ -z "$TOKEN" ]; then
    if [ -r /dev/tty ]; then
        printf 'CLAUDE_MONITOR_TOKEN (input hidden): ' > /dev/tty
        IFS= read -rs TOKEN < /dev/tty
        printf '\n' > /dev/tty
    fi
    [ -n "$TOKEN" ] || { echo "No token given." >&2; exit 1; }
fi
command -v curl >/dev/null || { echo "curl is required." >&2; exit 1; }

ENDPOINT="${URL%/}/api/claude-activity"
HOOK="$DIR/claude-monitor-hook.sh"
SETTINGS="$DIR/managed-settings.json"

# Never overwrite managed settings that something else put there.
if [ -f "$SETTINGS" ] && ! grep -q "claude-monitor-hook" "$SETTINGS"; then
    echo "$SETTINGS already exists and isn't ours." >&2
    echo "Add the hooks from managed-settings.example.json to it by hand." >&2
    exit 1
fi

# Keep the device id across reinstalls so the device stays one row in the log.
DEVICE_ID=""
if [ -f "$HOOK" ]; then
    DEVICE_ID="$(sed -n 's/^DEVICE_ID=//p' "$HOOK" | tr -d "'\"")"
fi
if [ -z "$DEVICE_ID" ]; then
    DEVICE_ID="$(uuidgen 2>/dev/null || cat /proc/sys/kernel/random/uuid)"
    DEVICE_ID="$(echo "$DEVICE_ID" | tr '[:upper:]' '[:lower:]')"
fi

mkdir -p "$DIR"

# --- the hook -------------------------------------------------------------
{
    echo '#!/bin/bash'
    echo '# Claude Code activity monitor hook. Written by tools/claude-monitor/install.sh.'
    printf 'ENDPOINT=%q\n' "$ENDPOINT"
    printf 'TOKEN=%q\n' "$TOKEN"
    printf 'DEVICE_ID=%q\n' "$DEVICE_ID"
    cat <<'HOOK'

# Claude Code passes the event as JSON on stdin. Forward it as-is, with who and
# where in headers. The post runs in the background with a short timeout and
# prints nothing, so it never slows Claude down or adds to the prompt.
payload="$(cat)"
(
    # On macOS, the computer name set in System Settings: the hostname there can
    # change with the network the Mac is on.
    host="$(scutil --get ComputerName 2>/dev/null || hostname)"
    # The MAC address of the adapter the default route uses (on a Mac, its
    # hardware address, not a private Wi-Fi one).
    mac=""
    case "$(uname -s)" in
        Darwin)
            iface="$(route -n get default 2>/dev/null | awk '/interface:/ { print $2; exit }')"
            mac="$(networksetup -getmacaddress "${iface:-en0}" 2>/dev/null | awk '{ print $3 }')"
            case "$mac" in ??:??:??:??:??:??) ;; *) mac="$(networksetup -getmacaddress en0 2>/dev/null | awk '{ print $3 }')" ;; esac
            ;;
        Linux)
            iface="$(ip route show default 2>/dev/null | awk '{ for (i = 1; i < NF; i++) if ($i == "dev") { print $(i + 1); exit } }')"
            [ -n "$iface" ] && mac="$(cat "/sys/class/net/$iface/address" 2>/dev/null)"
            ;;
    esac
    curl -sS -m 8 -X POST "$ENDPOINT" \
        -H "Authorization: Bearer $TOKEN" \
        -H "Content-Type: application/json" \
        -H "Accept: application/json" \
        -H "X-Claude-Device-Id: $DEVICE_ID" \
        -H "X-Claude-Host: $host" \
        -H "X-Claude-Mac: $mac" \
        -H "X-Claude-User: $(id -un)" \
        -H "X-Claude-Platform: $(uname -s)" \
        -H "X-Claude-Entrypoint: ${CLAUDE_CODE_ENTRYPOINT:-}" \
        --data-binary "$payload"
) </dev/null >/dev/null 2>&1 &
exit 0
HOOK
} > "$HOOK"
chown root "$HOOK"
chmod 755 "$HOOK"

# --- managed settings -----------------------------------------------------
CMD="\\\"$HOOK\\\""   # the path, quoted for the shell, escaped for JSON
cat > "$SETTINGS" <<JSON
{
  "hooks": {
    "SessionStart": [{ "hooks": [{ "type": "command", "command": "$CMD" }] }],
    "UserPromptSubmit": [{ "hooks": [{ "type": "command", "command": "$CMD" }] }],
    "SessionEnd": [{ "hooks": [{ "type": "command", "command": "$CMD" }] }],
    "PostModelSwitch": [{ "hooks": [{ "type": "command", "command": "$CMD" }] }],
    "PreToolUse": [{ "matcher": "mcp__claude-in-chrome__.*", "hooks": [{ "type": "command", "command": "$CMD" }] }]
  }
}
JSON
chown root "$SETTINGS"
chmod 644 "$SETTINGS"

# --- check it reaches the server ------------------------------------------
STATUS="$(curl -sS -m 10 -o /dev/null -w '%{http_code}' -X POST "$ENDPOINT" \
    -H "Authorization: Bearer $TOKEN" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -H "X-Claude-Device-Id: $DEVICE_ID" \
    -H "X-Claude-Host: $(scutil --get ComputerName 2>/dev/null || hostname)" \
    -H "X-Claude-User: ${SUDO_USER:-$(id -un)}" \
    -H "X-Claude-Platform: $(uname -s)" \
    -H "X-Claude-Entrypoint: installer" \
    --data-binary '{"hook_event_name":"MonitorInstalled"}' || true)"

echo "Installed:"
echo "  hook      $HOOK"
echo "  settings  $SETTINGS"
echo "  device id $DEVICE_ID"
if [ "$STATUS" = "201" ]; then
    echo "Test event sent — this device now shows on the Claude activity page."
else
    echo "WARNING: test event failed (HTTP $STATUS). Check the URL, the token and that the PM tool is reachable." >&2
    exit 2
fi
echo "Restart any open Claude Code sessions so they pick up the hook."
