# Claude Code activity monitor — installer for Windows.
#
# Installs a hook that reports every Claude Code session start, prompt and
# session end on this device to the PM tool's /api/claude-activity endpoint,
# in Claude Code's managed settings (admin-only, can't be switched off by a user).
#
# Run in PowerShell *as Administrator*:
#   powershell -ExecutionPolicy Bypass -File .\install.ps1 -Url https://pm.example.com -Token <CLAUDE_MONITOR_TOKEN>

param(
    [Parameter(Mandatory = $true)][string]$Url,
    [Parameter(Mandatory = $true)][string]$Token
)
$ErrorActionPreference = 'Stop'

$isAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole(
    [Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $isAdmin) { Write-Error 'Run PowerShell as Administrator: this writes Claude Code''s managed settings.' }

$Dir = 'C:\Program Files\ClaudeCode'
$Hook = Join-Path $Dir 'claude-monitor-hook.ps1'
$Settings = Join-Path $Dir 'managed-settings.json'
$Endpoint = $Url.TrimEnd('/') + '/api/claude-activity'

if ((Test-Path $Settings) -and -not (Select-String -Path $Settings -Pattern 'claude-monitor-hook' -Quiet)) {
    Write-Error "$Settings already exists and isn't ours. Add the hooks from managed-settings.example.json to it by hand."
}

# Keep the device id across reinstalls.
$DeviceId = $null
if (Test-Path $Hook) {
    $m = Select-String -Path $Hook -Pattern "^\`$DeviceId = '(.+)'" | Select-Object -First 1
    if ($m) { $DeviceId = $m.Matches[0].Groups[1].Value }
}
if (-not $DeviceId) { $DeviceId = [guid]::NewGuid().ToString() }

New-Item -ItemType Directory -Force -Path $Dir | Out-Null

$q = { param($s) "'" + ($s -replace "'", "''") + "'" }   # PowerShell single-quoted literal
@"
# Claude Code activity monitor hook. Written by tools/claude-monitor/install.ps1.
`$Endpoint = $(& $q $Endpoint)
`$Token = $(& $q $Token)
`$DeviceId = '$DeviceId'
"@ + @'

# Claude Code passes the event as JSON on stdin. Forward it as-is, with who and
# where in headers. Prints nothing and never fails the session.
try {
    $payload = [Console]::In.ReadToEnd()
    $headers = @{
        'Authorization'      = "Bearer $Token"
        'Accept'             = 'application/json'
        'X-Claude-Device-Id' = $DeviceId
        'X-Claude-Host'      = $env:COMPUTERNAME
        'X-Claude-User'      = $env:USERNAME
        'X-Claude-Platform'  = 'Windows'
    }
    if ($env:CLAUDE_CODE_ENTRYPOINT) { $headers['X-Claude-Entrypoint'] = $env:CLAUDE_CODE_ENTRYPOINT }
    Invoke-RestMethod -Uri $Endpoint -Method Post -Headers $headers -ContentType 'application/json' `
        -Body ([Text.Encoding]::UTF8.GetBytes($payload)) -TimeoutSec 8 | Out-Null
} catch { }
exit 0
'@ | Set-Content -Path $Hook -Encoding UTF8

$command = "powershell -NoProfile -ExecutionPolicy Bypass -File `"$Hook`""
$entry = @(@{ hooks = @(@{ type = 'command'; command = $command }) })
@{ hooks = [ordered]@{ SessionStart = $entry; UserPromptSubmit = $entry; SessionEnd = $entry } } |
    ConvertTo-Json -Depth 6 | Set-Content -Path $Settings -Encoding UTF8

# Only administrators may change the hook or the settings.
icacls $Dir /inheritance:r /grant:r 'Administrators:(OI)(CI)F' 'SYSTEM:(OI)(CI)F' 'Users:(OI)(CI)RX' | Out-Null

try {
    $headers = @{
        'Authorization' = "Bearer $Token"; 'Accept' = 'application/json'
        'X-Claude-Device-Id' = $DeviceId; 'X-Claude-Host' = $env:COMPUTERNAME
        'X-Claude-User' = $env:USERNAME; 'X-Claude-Platform' = 'Windows'; 'X-Claude-Entrypoint' = 'installer'
    }
    Invoke-RestMethod -Uri $Endpoint -Method Post -Headers $headers -ContentType 'application/json' `
        -Body '{"hook_event_name":"MonitorInstalled"}' -TimeoutSec 10 | Out-Null
    Write-Host "Installed. Device id $DeviceId. Test event sent - this device now shows on the Claude activity page."
    Write-Host 'Restart any open Claude Code sessions so they pick up the hook.'
} catch {
    Write-Warning "Installed, but the test event failed: $($_.Exception.Message). Check the URL, the token and that the PM tool is reachable."
    exit 2
}
