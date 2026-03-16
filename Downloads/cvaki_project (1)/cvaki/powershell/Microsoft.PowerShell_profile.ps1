# ============================================================
# CVAKI PowerShell Profile
# Place in: $PROFILE (usually Documents\PowerShell\Microsoft.PowerShell_profile.ps1)
# Colors match the CVAKI fox logo: Orange + Teal + Dark
# ============================================================

# ---- Color Shortcuts ----
$ORANGE  = "`e[38;2;255;107;0m"
$TEAL    = "`e[38;2;0;191;165m"
$WHITE   = "`e[38;2;232;232;232m"
$DIM     = "`e[38;2;100;100;100m"
$BOLD    = "`e[1m"
$RESET   = "`e[0m"

# ---- ASCII Fox Banner ----
function Show-CVAKIBanner {
    $fox = @"
$ORANGE
    /\  /\    /```\
   /  \/  \  / FOX \   ███████╗██╗   ██╗ █████╗ ██╗  ██╗██╗
  /  fox\  \/  ai  \  ██╔════╝██║   ██║██╔══██╗██║ ██╔╝██║
 / /\  /\  \        \ ██║     ██║   ██║███████║█████╔╝ ██║
/_/  \/  \_/         \██║     ╚██╗ ██╔╝██╔══██║██╔═██╗ ██║
                       ╚██████╗ ╚████╔╝ ██║  ██║██║  ██╗██║
$TEAL  𝗖𝗩♞𝗞𝗜 Brain Gods $RESET$DIM  ╚═════╝  ╚═══╝  ╚═╝  ╚═╝╚═╝  ╚═╝╚═╝$RESET

$TEAL  Type 'cvaki' to start the AI shell   |   'cvaki --help' for options$RESET
"@
    Write-Host $fox
}

# ---- Custom Prompt ----
function prompt {
    $time     = Get-Date -Format "HH:mm:ss"
    $path     = $ExecutionContext.SessionState.Path.CurrentLocation.Path

    # Shorten path
    $home     = $HOME
    if ($path.StartsWith($home)) { $path = "~" + $path.Substring($home.Length) }
    if ($path.Length -gt 40)    { $path = "..." + $path.Substring($path.Length - 37) }

    $user     = $env:USERNAME
    $hostname = "localhost:7799"

    # Line 1: π=[time]=(path)ꬹ[user]_{host}
    Write-Host ""
    Write-Host -NoNewline "${ORANGE}π${RESET}"
    Write-Host -NoNewline "=${TEAL}[${time}]${RESET}"
    Write-Host -NoNewline "=(${WHITE}${path}${RESET})"
    Write-Host -NoNewline "${ORANGE}ꬹ${RESET}"
    Write-Host -NoNewline "[${WHITE}${user}${RESET}]"
    Write-Host -NoNewline "_${TEAL}{${hostname}}${RESET}"
    Write-Host ""

    # Line 2: |_____{[𝗖𝗩♞𝗞𝗜.ai]}==>
    Write-Host -NoNewline "${ORANGE}|_____"
    Write-Host -NoNewline "{[${BOLD}𝗖𝗩♞𝗞𝗜.ai${RESET}${ORANGE}]}"
    Write-Host -NoNewline "==>${RESET} "

    return " "
}

# ---- CVAKI Launcher ----
function cvaki {
    param(
        [string]$Command = "",
        [switch]$Help,
        [switch]$Setup,
        [switch]$Background,
        [switch]$Dashboard
    )

    $cvakiPath = "$HOME\.cvaki\cvaki"

    # Try to find CVAKI installation
    $pythonScript = $null
    $searchPaths = @(
        "$HOME\.cvaki\cvaki\main.py",
        "$HOME\cvaki\main.py",
        ".\main.py",
        (Get-Command cvaki_main -ErrorAction SilentlyContinue)?.Source
    )

    foreach ($p in $searchPaths) {
        if ($p -and (Test-Path $p)) {
            $pythonScript = $p
            break
        }
    }

    if (-not $pythonScript) {
        Write-Host "${ORANGE}[CVAKI]${RESET} Cannot find CVAKI installation."
        Write-Host "${TEAL}  Install: git clone https://github.com/cvaki/cvaki.git ~/cvaki${RESET}"
        Write-Host "${TEAL}  Then:   cd ~/cvaki && pip install -r requirements.txt${RESET}"
        return
    }

    $args = @()
    if ($Help)       { $args += "--help" }
    if ($Setup)      { $args += "--setup" }
    if ($Background) { $args += "--background" }
    if ($Dashboard)  { $args += "--dashboard-only" }
    if ($Command)    { $args += $Command }

    python $pythonScript @args
}

# ---- Utility Aliases ----
Set-Alias -Name "ll"   -Value Get-ChildItem   -Force -ErrorAction SilentlyContinue
Set-Alias -Name "cls"  -Value Clear-Host       -Force -ErrorAction SilentlyContinue

function which { (Get-Command $args[0]).Source }
function touch { New-Item -ItemType File -Path $args[0] -Force | Out-Null }

function grep {
    param([string]$Pattern, [string]$Path = ".")
    Select-String -Pattern $Pattern -Path $Path -Recurse
}

# ---- Color test ----
function cvaki-colors {
    Write-Host "${ORANGE}  ORANGE  ${TEAL}  TEAL  ${WHITE}  WHITE  ${DIM}  DIM  ${RESET}"
    Write-Host "  𝗖𝗩♞𝗞𝗜 color theme active"
}

# ---- Show Banner on Startup ----
Show-CVAKIBanner

# ---- Tab Completion for cvaki ----
Register-ArgumentCompleter -CommandName cvaki -ParameterName Command -ScriptBlock {
    param($commandName, $parameterName, $wordToComplete, $commandAst, $fakeBoundParameters)
    @("--setup", "--background", "--dashboard-only", "--no-dashboard", "--help") |
    Where-Object { $_ -like "$wordToComplete*" } |
    ForEach-Object { [System.Management.Automation.CompletionResult]::new($_, $_, 'ParameterValue', $_) }
}

Write-Host ""
