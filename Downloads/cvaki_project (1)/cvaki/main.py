"""
 ██████╗██╗   ██╗ █████╗ ██╗  ██╗██╗
██╔════╝██║   ██║██╔══██╗██║ ██╔╝██║
██║     ██║   ██║███████║█████╔╝ ██║
██║     ╚██╗ ██╔╝██╔══██║██╔═██╗ ██║
╚██████╗ ╚████╔╝ ██║  ██║██║  ██╗██║
 ╚═════╝  ╚═══╝  ╚═╝  ╚═╝╚═╝  ╚═╝╚═╝
        𝗖𝗩♞𝗞𝗜  —  Brain Gods AI
"""

import sys
import os
from pathlib import Path

# ── Step 0: Auto-install any missing dependencies BEFORE other imports ────────
sys.path.insert(0, str(Path(__file__).parent))
from auto_setup import run_autosetup
run_autosetup(verbose=True)
# ─────────────────────────────────────────────────────────────────────────────

import asyncio
import argparse
import threading

from core.ai_engine import CVAKIEngine
from core.memory import MemoryStore
from interfaces.terminal import CVAKITerminal
from interfaces.dashboard import start_dashboard
from services.background import BackgroundService


ASCII_FOX = r"""
    /\  /\    ___
   /  \/  \  / __\
  / /\  /\ \/ /   
 / /  \/  \ \ \__ 
/_/        \_\___/ 
  𝗖𝗩♞𝗞𝗜 .ai  v1.0
"""

BANNER = """
\033[38;2;255;107;0m{fox}\033[0m
\033[38;2;0;191;165m  ╔══════════════════════════════╗
  ║   𝗖𝗩♞𝗞𝗜  Brain Gods AI v1.0   ║
  ║   Groq-Powered · Always On     ║
  ╚══════════════════════════════╝\033[0m
""".format(fox=ASCII_FOX)


def parse_args():
    parser = argparse.ArgumentParser(description="𝗖𝗩♞𝗞𝗜 AI System")
    parser.add_argument("--setup", action="store_true", help="First-time setup wizard")
    parser.add_argument("--background", action="store_true", help="Start as background daemon")
    parser.add_argument("--dashboard-only", action="store_true", help="Start only the web dashboard")
    parser.add_argument("--no-dashboard", action="store_true", help="Skip starting web dashboard")
    parser.add_argument("--port", type=int, default=7799, help="Dashboard port (default: 7799)")
    return parser.parse_args()


async def main():
    args = parse_args()

    print(BANNER)

    # Init memory/config store
    memory = MemoryStore()

    # First-time setup
    if args.setup or not memory.has_api_key():
        print("\033[38;2;255;107;0m[CVAKI]\033[0m First-time setup...")
        api_key = input("  Enter your Groq API key: ").strip()
        if not api_key:
            print("  No API key provided. Get one free at https://console.groq.com")
            sys.exit(1)
        memory.set_api_key(api_key)
        wa_number = input("  Enter your WhatsApp number (e.g. +919876543210) [optional]: ").strip()
        if wa_number:
            memory.set("whatsapp_number", wa_number)
        print("\033[38;2;0;191;165m  ✓ CVAKI configured!\033[0m\n")

    # Init AI engine
    engine = CVAKIEngine(api_key=memory.get_api_key())

    # Start background service
    bg_service = BackgroundService(engine, memory)
    bg_thread = threading.Thread(target=bg_service.run, daemon=True)
    bg_thread.start()
    print("\033[38;2;0;191;165m[CVAKI]\033[0m Background service started")

    # Start web dashboard
    if not args.no_dashboard:
        dashboard_thread = threading.Thread(
            target=start_dashboard,
            args=(engine, memory, args.port),
            daemon=True
        )
        dashboard_thread.start()
        print(f"\033[38;2;0;191;165m[CVAKI]\033[0m CVAKI Doctor dashboard → http://localhost:{args.port}")

    if args.dashboard_only:
        print("\033[38;2;255;107;0m[CVAKI]\033[0m Dashboard-only mode. Press Ctrl+C to stop.")
        try:
            while True:
                await asyncio.sleep(1)
        except KeyboardInterrupt:
            print("\n\033[38;2;255;107;0m[CVAKI]\033[0m Shutting down...")
        return

    # Start interactive terminal
    terminal = CVAKITerminal(engine, memory, port=args.port)
    await terminal.run()


if __name__ == "__main__":
    try:
        asyncio.run(main())
    except KeyboardInterrupt:
        print("\n\033[38;2;255;107;0m[CVAKI]\033[0m See you. —𝗖𝗩♞𝗞𝗜")
