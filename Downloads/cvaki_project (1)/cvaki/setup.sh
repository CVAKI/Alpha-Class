#!/usr/bin/env bash
# ============================================================
# CVAKI Auto-Setup — Linux / macOS
# Fully automatic. Detects and installs everything.
# No prompts. No questions. Just run it.
# ============================================================

set -euo pipefail

O='\033[38;2;255;107;0m'   # orange
T='\033[38;2;0;191;165m'   # teal
G='\033[38;2;0;200;100m'   # green
Y='\033[33m'                # yellow
R='\033[0m'                 # reset
D='\033[2m'                 # dim
BOLD='\033[1m'

STEP=0
FAILED=()
OS_TYPE="$(uname -s)"   # Linux | Darwin

step()  { STEP=$((STEP+1)); echo -e "\n${T}[${STEP}] $*${R}"; }
ok()    { echo -e "  ${G}✓${R}  $*"; }
warn()  { echo -e "  ${Y}⚠${R}  $*"; }
skip()  { echo -e "  ${D}─  $* (skipped)${R}"; }
fail()  { echo -e "  \033[31m✗${R}  $*"; FAILED+=("$*"); }

# ── banner ────────────────────────────────────────────────────
echo -e "${O}"
cat << 'BANNER'
    ██████╗██╗   ██╗ █████╗ ██╗  ██╗██╗
   ██╔════╝██║   ██║██╔══██╗██║ ██╔╝██║
   ██║     ██║   ██║███████║█████╔╝ ██║
   ██║     ╚██╗ ██╔╝██╔══██║██╔═██╗ ██║
   ╚██████╗ ╚████╔╝ ██║  ██║██║  ██╗██║
    ╚═════╝  ╚═══╝  ╚═╝  ╚═╝╚═╝  ╚═╝╚═╝
BANNER
echo -e "${T}   Brain Gods AI — Auto-Setup (fully automatic)${R}"
echo ""

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# ══════════════════════════════════════════════════════════════
# 1. SYSTEM PACKAGE MANAGER (detect)
# ══════════════════════════════════════════════════════════════
step "Detecting system..."
if [ "$OS_TYPE" = "Darwin" ]; then
    ok "macOS detected"
    PKG_INSTALL_PY="brew install python@3.12"
    PKG_INSTALL_NODE="brew install node"
    HAS_BREW=false
    if command -v brew &>/dev/null; then HAS_BREW=true; ok "Homebrew found"; fi
elif command -v apt-get &>/dev/null; then
    ok "Debian/Ubuntu detected"
    PKG_INSTALL_PY="sudo apt-get install -y python3 python3-pip python3-venv"
    PKG_INSTALL_NODE="sudo apt-get install -y nodejs npm"
    # Refresh package list silently
    sudo apt-get update -qq 2>/dev/null || true
elif command -v dnf &>/dev/null; then
    ok "Fedora/RHEL detected"
    PKG_INSTALL_PY="sudo dnf install -y python3 python3-pip"
    PKG_INSTALL_NODE="sudo dnf install -y nodejs npm"
elif command -v pacman &>/dev/null; then
    ok "Arch Linux detected"
    PKG_INSTALL_PY="sudo pacman -S --noconfirm python python-pip"
    PKG_INSTALL_NODE="sudo pacman -S --noconfirm nodejs npm"
else
    warn "Unknown system — will try pip directly"
    PKG_INSTALL_PY=""
    PKG_INSTALL_NODE=""
fi

# ══════════════════════════════════════════════════════════════
# 2. PYTHON 3.10+
# ══════════════════════════════════════════════════════════════
step "Python 3.10+..."

PY=""
for cmd in python3.12 python3.11 python3.10 python3 python; do
    if command -v "$cmd" &>/dev/null; then
        VER=$("$cmd" -c "import sys; print(sys.version_info[:2] >= (3,10))" 2>/dev/null)
        if [ "$VER" = "True" ]; then PY="$cmd"; break; fi
    fi
done

if [ -n "$PY" ]; then
    ok "$PY $($PY --version)"
else
    warn "Python 3.10+ not found. Installing..."
    if [ -n "$PKG_INSTALL_PY" ]; then
        eval "$PKG_INSTALL_PY" >/dev/null 2>&1 && ok "Python installed via system package manager" || fail "Python installation failed"
    fi
    # Re-check
    for cmd in python3.12 python3.11 python3.10 python3; do
        command -v "$cmd" &>/dev/null && PY="$cmd" && break || true
    done
    [ -z "$PY" ] && { fail "Python not available — install from https://python.org"; PY="python3"; }
fi

# ══════════════════════════════════════════════════════════════
# 3. PIP / ENSUREPIP
# ══════════════════════════════════════════════════════════════
step "pip..."

if ! "$PY" -m pip --version &>/dev/null; then
    warn "pip missing — running ensurepip..."
    "$PY" -m ensurepip --upgrade 2>/dev/null || \
    curl -sS https://bootstrap.pypa.io/get-pip.py | "$PY" - --quiet
fi

"$PY" -m pip install --upgrade pip --quiet --disable-pip-version-check 2>/dev/null
ok "pip ready"

# ══════════════════════════════════════════════════════════════
# 4. PYTHON PACKAGES
# ══════════════════════════════════════════════════════════════
step "Python packages (requirements.txt)..."

"$PY" -m pip install -r requirements.txt --quiet --disable-pip-version-check && \
    ok "All packages installed" || {
    warn "Bulk install had issues. Retrying one-by-one..."
    while IFS= read -r line || [ -n "$line" ]; do
        [[ "$line" =~ ^#.*$ || -z "$line" ]] && continue
        PKG=$(echo "$line" | cut -d'>' -f1 | cut -d'<' -f1 | cut -d'=' -f1 | cut -d'!' -f1 | xargs)
        "$PY" -m pip install "$line" --quiet --disable-pip-version-check 2>/dev/null && \
            ok "$PKG" || warn "skipped: $PKG (non-fatal)"
    done < requirements.txt
}

# ══════════════════════════════════════════════════════════════
# 5. NODE.JS
# ══════════════════════════════════════════════════════════════
step "Node.js (WhatsApp bridge)..."

if command -v node &>/dev/null; then
    ok "Node.js $(node --version)"
else
    warn "Node.js not found. Installing..."
    if [ "$OS_TYPE" = "Darwin" ] && $HAS_BREW; then
        brew install node --quiet && ok "Node installed via Homebrew"
    elif [ -n "$PKG_INSTALL_NODE" ]; then
        eval "$PKG_INSTALL_NODE" >/dev/null 2>&1 && ok "Node installed via package manager" || {
            # Try nvm as fallback
            warn "Package manager failed. Trying nvm..."
            curl -sS https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.7/install.sh | bash >/dev/null 2>&1
            export NVM_DIR="$HOME/.nvm"
            # shellcheck source=/dev/null
            [ -s "$NVM_DIR/nvm.sh" ] && \. "$NVM_DIR/nvm.sh"
            nvm install --lts --silent 2>/dev/null && ok "Node installed via nvm" || warn "Node.js unavailable (WhatsApp bridge disabled)"
        }
    else
        warn "Node.js unavailable — WhatsApp bridge will not work"
        warn "  Install manually: https://nodejs.org"
    fi
fi

# ══════════════════════════════════════════════════════════════
# 6. NPM PACKAGES (WhatsApp bridge)
# ══════════════════════════════════════════════════════════════
step "WhatsApp npm packages..."

if command -v node &>/dev/null && [ -f "whatsapp/package.json" ]; then
    if [ ! -d "whatsapp/node_modules" ]; then
        pushd whatsapp >/dev/null
        npm install --silent 2>/dev/null && ok "npm packages installed" || warn "npm install had issues (non-fatal)"
        popd >/dev/null
    else
        ok "Already installed"
    fi
else
    skip "Node.js not available"
fi

# ══════════════════════════════════════════════════════════════
# 7. SHELL ALIAS (auto-append, no prompt)
# ══════════════════════════════════════════════════════════════
step "Shell alias (cvaki command)..."

SHELL_NAME=$(basename "$SHELL")
case "$SHELL_NAME" in
    zsh)  PROFILE="$HOME/.zshrc"  ;;
    bash) PROFILE="${BASH_PROFILE:-$HOME/.bashrc}" ;;
    fish) PROFILE="$HOME/.config/fish/config.fish" ;;
    *)    PROFILE="$HOME/.profile" ;;
esac

CVAKI_BLOCK='
# ── CVAKI Brain Gods ─────────────────────────────────────
alias cvaki="python3 $HOME/.cvaki/cvaki/main.py"
alias cvaki-setup="python3 $HOME/.cvaki/cvaki/main.py --setup"
alias cvaki-bg="python3 $HOME/.cvaki/cvaki/main.py --background &"
# CVAKI prompt (uncomment to activate):
# CVAKI_PS1=true
# [ "$CVAKI_PS1" = "true" ] && PS1='"'"'\n\[\033[38;2;255;107;0m\]π\[\033[0m\]=\[\033[38;2;0;191;165m\][\t]\[\033[0m\]=(\w)\[\033[38;2;255;107;0m\]ꬹ\[\033[0m\][\u]_{localhost:7799}\n\[\033[38;2;255;107;0m\]|_____{\[\033[1m\]𝗖𝗩♞𝗞𝗜.ai\[\033[0m\]\[\033[38;2;255;107;0m\]}\[\033[0m\]\[\033[38;2;0;191;165m\]==>\[\033[0m\] '"'"'
# ─────────────────────────────────────────────────────────'

if grep -q "CVAKI Brain Gods" "$PROFILE" 2>/dev/null; then
    ok "Already in $PROFILE"
else
    printf '%s\n' "$CVAKI_BLOCK" >> "$PROFILE"
    ok "Added to $PROFILE"
fi

# ══════════════════════════════════════════════════════════════
# 8. DATA DIRECTORIES
# ══════════════════════════════════════════════════════════════
step "Data directories..."
mkdir -p "$HOME/.cvaki/logs" "$HOME/.cvaki/exports" "$HOME/.cvaki/wa_session"
ok "~/.cvaki ready"

# ══════════════════════════════════════════════════════════════
# 9. GLOBAL INSTALL
# ══════════════════════════════════════════════════════════════
step "Installing to ~/.cvaki/cvaki..."
mkdir -p "$HOME/.cvaki/cvaki"
cp -r . "$HOME/.cvaki/cvaki/" 2>/dev/null || rsync -a --exclude=node_modules . "$HOME/.cvaki/cvaki/" 2>/dev/null || true
ok "Installed"

# Make main.py executable
chmod +x "$HOME/.cvaki/cvaki/main.py" 2>/dev/null || true

# ══════════════════════════════════════════════════════════════
# 10. PYTHON VERIFIER
# ══════════════════════════════════════════════════════════════
step "Final dependency verification..."
"$PY" auto_setup.py

# ══════════════════════════════════════════════════════════════
# DONE
# ══════════════════════════════════════════════════════════════
echo ""
echo -e "${O}══════════════════════════════════════════════${R}"
if [ ${#FAILED[@]} -eq 0 ]; then
    echo -e "${G}  𝗖𝗩♞𝗞𝗜 is ready. Everything installed.${R}"
else
    echo -e "${Y}  Setup complete (with warnings):${R}"
    for f in "${FAILED[@]}"; do echo -e "    ${Y}⚠${R} $f"; done
fi
echo -e "${O}══════════════════════════════════════════════${R}"
echo ""
echo -e "  Reload shell:  ${T}source $PROFILE${R}"
echo -e "  First run:     ${O}cvaki --setup${R}  (enter your Groq key)"
echo -e "  Start:         ${O}cvaki${R}"
echo ""
echo -e "  Free Groq key: ${T}https://console.groq.com${R}"
echo ""
