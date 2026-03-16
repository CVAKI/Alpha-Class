"""
CVAKI Auto-Healer / Dependency Bootstrap
Runs before anything else. Detects missing packages,
installs them silently, then continues.
No prompts. No failures. Just fixes.
"""

import sys
import os
import subprocess
import importlib
import platform
from pathlib import Path

ORANGE = "\033[38;2;255;107;0m"
TEAL   = "\033[38;2;0;191;165m"
GREEN  = "\033[38;2;0;200;100m"
DIM    = "\033[2m"
RESET  = "\033[0m"

OS = platform.system()   # Windows | Linux | Darwin

# ── Core packages that must exist before CVAKI can even start ─────────────────
REQUIRED = [
    # (import_name, pip_package, extra_args)
    ("groq",            "groq",                    []),
    ("fastapi",         "fastapi",                 []),
    ("uvicorn",         "uvicorn[standard]",        []),
    ("rich",            "rich",                    []),
    ("websockets",      "websockets",              []),
    ("dotenv",          "python-dotenv",           []),
    ("psutil",          "psutil",                  []),
    ("colorama",        "colorama",                []),
]

# ── Optional packages (installed in background if missing) ────────────────────
OPTIONAL = [
    ("torch",           "torch",                   ["--index-url", "https://download.pytorch.org/whl/cpu"]),
    ("pyautogui",       "pyautogui",               []),
    ("pygetwindow",     "pygetwindow",             []),
    ("PIL",             "Pillow",                  []),
    ("docx",            "python-docx",             []),
    ("fitz",            "PyMuPDF",                 []),
    ("numpy",           "numpy",                   []),
    ("requests",        "requests",                []),
    ("watchdog",        "watchdog",                []),
]


def _pip_install(package: str, extra_args: list = None, silent: bool = True) -> bool:
    """Run pip install. Returns True on success."""
    cmd = [sys.executable, "-m", "pip", "install", "--upgrade", package]
    if extra_args:
        cmd.extend(extra_args)
    if silent:
        cmd.extend(["--quiet", "--disable-pip-version-check"])

    try:
        result = subprocess.run(cmd, capture_output=True, text=True, timeout=180)
        return result.returncode == 0
    except Exception:
        return False


def _is_importable(name: str) -> bool:
    """Try importing a module to see if it's available."""
    try:
        importlib.import_module(name)
        return True
    except ImportError:
        return False


def _check_python_version():
    """Enforce Python 3.10+."""
    if sys.version_info < (3, 10):
        print(f"{ORANGE}[CVAKI AUTO-SETUP]{RESET} Python 3.10+ required. You have {sys.version}")
        print(f"{TEAL}  Download: https://python.org/downloads{RESET}")
        sys.exit(1)


def _ensure_pip():
    """Make sure pip itself is available."""
    try:
        import pip  # noqa
    except ImportError:
        print(f"{ORANGE}[AUTO-SETUP]{RESET} pip not found — installing...")
        subprocess.run([sys.executable, "-m", "ensurepip", "--upgrade"],
                       capture_output=True)


def _check_node() -> bool:
    """Check if Node.js is available."""
    try:
        result = subprocess.run(["node", "--version"], capture_output=True, text=True)
        return result.returncode == 0
    except FileNotFoundError:
        return False


def _install_node_deps():
    """Run npm install in the whatsapp folder if node_modules missing."""
    wa_dir = Path(__file__).parent / "whatsapp"
    node_modules = wa_dir / "node_modules"
    package_json = wa_dir / "package.json"

    if not package_json.exists():
        return

    if not node_modules.exists():
        print(f"{TEAL}[AUTO-SETUP]{RESET} Installing WhatsApp bridge packages...")
        try:
            result = subprocess.run(
                ["npm", "install", "--silent"],
                cwd=str(wa_dir),
                capture_output=True, text=True, timeout=120
            )
            if result.returncode == 0:
                print(f"{GREEN}[AUTO-SETUP]{RESET} ✓ WhatsApp node packages installed")
            else:
                print(f"{ORANGE}[AUTO-SETUP]{RESET} npm install had issues (non-fatal):\n{result.stderr[:200]}")
        except FileNotFoundError:
            print(f"{DIM}[AUTO-SETUP] npm not found — WhatsApp bridge unavailable{RESET}")
        except subprocess.TimeoutExpired:
            print(f"{ORANGE}[AUTO-SETUP]{RESET} npm install timed out")


def _install_ps_profile():
    """Auto-install the PowerShell profile on Windows if not present."""
    if OS != "Windows":
        return

    profile_dir = Path.home() / "Documents" / "PowerShell"
    profile_file = profile_dir / "Microsoft.PowerShell_profile.ps1"
    source = Path(__file__).parent / "powershell" / "Microsoft.PowerShell_profile.ps1"

    if not source.exists():
        return

    if not profile_file.exists():
        try:
            profile_dir.mkdir(parents=True, exist_ok=True)
            import shutil
            shutil.copy2(str(source), str(profile_file))
            print(f"{GREEN}[AUTO-SETUP]{RESET} ✓ PowerShell profile installed → {profile_file}")
        except Exception as e:
            print(f"{ORANGE}[AUTO-SETUP]{RESET} Could not install PS profile: {e}")


def _setup_cvaki_dir():
    """Create ~/.cvaki directory structure."""
    cvaki_dir = Path.home() / ".cvaki"
    for sub in ["", "wa_session", "logs", "exports"]:
        (cvaki_dir / sub).mkdir(parents=True, exist_ok=True)


def _install_optional_background(packages: list):
    """Install optional packages silently in a background thread."""
    import threading

    def worker():
        for import_name, pip_pkg, extra in packages:
            if not _is_importable(import_name):
                success = _pip_install(pip_pkg, extra, silent=True)
                if success:
                    pass  # Silent success for optional
    t = threading.Thread(target=worker, daemon=True)
    t.start()


def run_autosetup(verbose: bool = True) -> bool:
    """
    Main entry point. Call this at the top of main.py.
    Returns True if all REQUIRED packages are available.
    """
    _check_python_version()
    _ensure_pip()
    _setup_cvaki_dir()

    missing_required = []
    for import_name, pip_pkg, extra in REQUIRED:
        if not _is_importable(import_name):
            missing_required.append((import_name, pip_pkg, extra))

    if missing_required:
        print(f"\n{ORANGE}[𝗖𝗩♞𝗞𝗜 AUTO-SETUP]{RESET} Installing {len(missing_required)} missing package(s)...\n")
        all_ok = True
        for import_name, pip_pkg, extra in missing_required:
            print(f"  {TEAL}→{RESET} Installing {pip_pkg}...", end=" ", flush=True)
            ok = _pip_install(pip_pkg, extra, silent=True)
            if ok:
                print(f"{GREEN}✓{RESET}")
            else:
                # Retry once without --quiet to see errors
                print(f"{ORANGE}retrying...{RESET}", end=" ", flush=True)
                ok2 = _pip_install(pip_pkg, extra, silent=False)
                if ok2:
                    print(f"{GREEN}✓{RESET}")
                else:
                    print(f"{ORANGE}✗ failed{RESET}")
                    all_ok = False

        if not all_ok:
            print(f"\n{ORANGE}[AUTO-SETUP]{RESET} Some packages failed. Try manually:")
            print(f"  {TEAL}pip install -r requirements.txt{RESET}\n")
            return False

        print(f"\n{GREEN}[AUTO-SETUP]{RESET} All required packages installed.\n")

    # Optional packages installed quietly in background
    missing_optional = [
        (n, p, e) for n, p, e in OPTIONAL if not _is_importable(n)
    ]
    if missing_optional:
        if verbose:
            names = ", ".join(p for _, p, _ in missing_optional)
            print(f"{DIM}[AUTO-SETUP] Installing optional packages in background: {names}{RESET}")
        _install_optional_background(missing_optional)

    # Node / WhatsApp deps
    if _check_node():
        _install_node_deps()
    else:
        if verbose:
            print(f"{DIM}[AUTO-SETUP] Node.js not found — WhatsApp bridge disabled. "
                  f"Install from https://nodejs.org{RESET}")

    # PowerShell profile (Windows only)
    _install_ps_profile()

    return True


# ── Standalone mode: python auto_setup.py ────────────────────────────────────
if __name__ == "__main__":
    print(f"""
{ORANGE}    ██████╗██╗   ██╗ █████╗ ██╗  ██╗██╗
   ██╔════╝██║   ██║██╔══██╗██║ ██╔╝██║
   ██║     ██║   ██║███████║█████╔╝ ██║
   ██║     ╚██╗ ██╔╝██╔══██║██╔═██╗ ██║
   ╚██████╗ ╚████╔╝ ██║  ██║██║  ██╗██║
    ╚═════╝  ╚═══╝  ╚═╝  ╚═╝╚═╝  ╚═╝╚═╝{RESET}
{TEAL}   𝗖𝗩♞𝗞𝗜 Auto-Setup — detecting & fixing everything...{RESET}
""")

    # Full verbose install when run directly
    _check_python_version()
    _ensure_pip()
    _setup_cvaki_dir()

    all_packages = REQUIRED + OPTIONAL
    print(f"{TEAL}Checking {len(all_packages)} packages...{RESET}\n")

    failed = []
    for import_name, pip_pkg, extra in all_packages:
        already = _is_importable(import_name)
        if already:
            print(f"  {GREEN}✓{RESET} {pip_pkg:<30} {DIM}already installed{RESET}")
        else:
            print(f"  {TEAL}→{RESET} {pip_pkg:<30}", end=" ", flush=True)
            ok = _pip_install(pip_pkg, extra, silent=True)
            if ok:
                print(f"{GREEN}✓ installed{RESET}")
            else:
                print(f"{ORANGE}✗ failed{RESET}")
                failed.append(pip_pkg)

    print()

    if _check_node():
        print(f"  {GREEN}✓{RESET} Node.js found")
        _install_node_deps()
    else:
        print(f"  {ORANGE}✗{RESET} Node.js not found — get it at https://nodejs.org")

    _install_ps_profile()

    print()
    if failed:
        print(f"{ORANGE}[!]{RESET} {len(failed)} package(s) failed: {', '.join(failed)}")
        print(f"    Try: pip install {' '.join(failed)}")
    else:
        print(f"{GREEN}[𝗖𝗩♞𝗞𝗜]{RESET} All dependencies satisfied. Run: python main.py")
