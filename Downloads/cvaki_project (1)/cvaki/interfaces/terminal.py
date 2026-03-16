"""
CVAKI Terminal Interface
Rich-powered interactive terminal with fox branding.
Colors match the fox logo: orange (#FF6B00), teal (#00BFA5), dark bg.
"""

import asyncio
import os
import sys
import platform
from datetime import datetime
from pathlib import Path

try:
    from rich.console import Console
    from rich.prompt import Prompt
    from rich.text import Text
    from rich.panel import Panel
    from rich.markdown import Markdown
    from rich.live import Live
    from rich.spinner import Spinner
    RICH_AVAILABLE = True
except ImportError:
    RICH_AVAILABLE = False


# Terminal color codes (fallback if Rich not available)
ORANGE = "\033[38;2;255;107;0m"
TEAL = "\033[38;2;0;191;165m"
WHITE = "\033[38;2;232;232;232m"
DIM = "\033[2m"
BOLD = "\033[1m"
RESET = "\033[0m"

FOX_MINI = r"""
  /\  /\
 /fox\/  \
 𝗖𝗩♞𝗞𝗜"""


class CVAKITerminal:
    def __init__(self, engine, memory, port: int = 7799):
        self.engine = engine
        self.memory = memory
        self.port = port
        self.running = True
        self.cwd = os.getcwd()

        if RICH_AVAILABLE:
            self.console = Console()
        else:
            self.console = None

    def _get_prompt(self) -> str:
        """Build the custom CVAKI prompt."""
        now = datetime.now().strftime("%H:%M:%S")
        path = self.cwd
        user = os.getenv("USERNAME") or os.getenv("USER", "user")
        hostname = f"localhost:{self.port}"

        # Shorten path for display
        home = str(Path.home())
        if path.startswith(home):
            path = "~" + path[len(home):]
        if len(path) > 35:
            path = "..." + path[-32:]

        line1 = f"{ORANGE}π{RESET}={TEAL}[{now}]{RESET}=({WHITE}{path}{RESET}){ORANGE}ꬹ{RESET}[{WHITE}{user}{RESET}]_{TEAL}{{{hostname}}}{RESET}"
        line2 = f"{ORANGE}|_____{{[{BOLD}𝗖𝗩♞𝗞𝗜.ai{RESET}{ORANGE}]}}{RESET}{TEAL}==>{RESET} "

        return f"\n{line1}\n{line2}"

    def _print_fox_banner(self):
        fox_art = f"""{ORANGE}
    ██████╗██╗   ██╗ █████╗ ██╗  ██╗██╗
   ██╔════╝██║   ██║██╔══██╗██║ ██╔╝██║
   ██║     ██║   ██║███████║█████╔╝ ██║
   ██║     ╚██╗ ██╔╝██╔══██║██╔═██╗ ██║
   ╚██████╗ ╚████╔╝ ██║  ██║██║  ██╗██║
    ╚═════╝  ╚═══╝  ╚═╝  ╚═╝╚═╝  ╚═╝╚═╝
{RESET}
{TEAL}   /\\  /\\   /{ORANGE}Fox{TEAL}\\    𝗖𝗩♞𝗞𝗜 Brain Gods AI{RESET}
{TEAL}  /  \\/  \\/ type: \\   Groq-Powered · v1.0{RESET}
{TEAL} / /\\  /\\ \\  anything \\  http://localhost:{self.port}{RESET}
{TEAL}/____\\/____\\__________{RESET}

{WHITE}  Commands: /help  /reset  /analyze <path>  /find <name>
           /run <cmd>   /tree <path>   /exit{RESET}
"""
        print(fox_art)

    def _handle_special_command(self, cmd: str) -> bool:
        """Handle slash commands. Returns True if handled."""
        cmd = cmd.strip()
        parts = cmd.split(None, 1)
        if not parts:
            return False

        command = parts[0].lower()
        arg = parts[1] if len(parts) > 1 else ""

        if command == "/help":
            self._print_help()
            return True

        elif command == "/reset":
            self.engine.reset_conversation()
            self.memory.clear_history("terminal")
            print(f"{TEAL}[CVAKI]{RESET} Conversation cleared.")
            return True

        elif command == "/analyze":
            path = arg.strip() or self.cwd
            print(f"{TEAL}[CVAKI]{RESET} Analyzing project: {path}")
            result = self.engine.analyst.analyze(path)
            print(result)
            return True

        elif command == "/find":
            if not arg:
                print(f"{ORANGE}[?]{RESET} Usage: /find <filename>")
                return True
            result = self.engine.file_agent.search(arg)
            print(result)
            return True

        elif command == "/run":
            if not arg:
                print(f"{ORANGE}[?]{RESET} Usage: /run <command>")
                return True
            result = self.engine.shell.execute(arg)
            print(result)
            return True

        elif command == "/tree":
            path = arg.strip() or self.cwd
            result = self.engine.file_agent.get_tree(path)
            print(result)
            return True

        elif command == "/cd":
            new_path = arg.strip() or str(Path.home())
            try:
                os.chdir(new_path)
                self.cwd = os.getcwd()
                print(f"{TEAL}[OK]{RESET} Changed to {self.cwd}")
            except Exception as e:
                print(f"{ORANGE}[ERR]{RESET} {e}")
            return True

        elif command in ("/exit", "/quit", "/bye"):
            print(f"\n{ORANGE}[CVAKI]{RESET} Goodbye. —𝗖𝗩♞𝗞𝗜")
            self.running = False
            return True

        elif command == "/sysinfo":
            info = self.engine.shell.get_system_info()
            for k, v in info.items():
                print(f"  {TEAL}{k}{RESET}: {v}")
            return True

        elif command == "/key":
            new_key = arg.strip()
            if new_key:
                self.memory.set_api_key(new_key)
                self.engine.client = __import__('groq').Groq(api_key=new_key)
                print(f"{TEAL}[OK]{RESET} API key updated.")
            else:
                print(f"{ORANGE}[?]{RESET} Usage: /key <new_groq_api_key>")
            return True

        return False

    def _print_help(self):
        help_text = f"""
{ORANGE}╔══════════════════════════════════════╗
║  𝗖𝗩♞𝗞𝗜 Commands                      ║
╚══════════════════════════════════════╝{RESET}

{TEAL}Chat:{RESET}  Just type anything — CVAKI will understand
{TEAL}       and use tools automatically as needed.{RESET}

{ORANGE}/help{RESET}              Show this help
{ORANGE}/reset{RESET}             Clear conversation history
{ORANGE}/analyze [path]{RESET}    Deep-analyze a project directory
{ORANGE}/find <name>{RESET}       Search files across your system
{ORANGE}/run <cmd>{RESET}         Execute a shell command directly
{ORANGE}/tree [path]{RESET}       Show directory tree
{ORANGE}/cd <path>{RESET}         Change working directory
{ORANGE}/sysinfo{RESET}           System information
{ORANGE}/key <apikey>{RESET}      Update Groq API key
{ORANGE}/exit{RESET}              Exit CVAKI terminal

{TEAL}Example prompts:{RESET}
  "Find my resume and tell me what's in it"
  "Look at my project in ~/myapp and check for issues"
  "Install numpy and write a quick test script"
  "What Python files are in my Downloads folder?"
"""
        print(help_text)

    async def run(self):
        """Main terminal event loop."""
        self._print_fox_banner()

        while self.running:
            try:
                prompt = self._get_prompt()
                user_input = input(prompt).strip()

                if not user_input:
                    continue

                # Log to memory
                self.memory.log_message("terminal", "user", user_input)

                # Check for special commands
                if user_input.startswith("/"):
                    if self._handle_special_command(user_input):
                        continue

                # Send to AI (streaming)
                print(f"\n{TEAL}𝗖𝗩♞𝗞𝗜 ❯{RESET} ", end="", flush=True)
                full_response = ""

                for chunk in self.engine.chat_stream(user_input):
                    print(chunk, end="", flush=True)
                    full_response += chunk

                print()  # newline after response
                self.memory.log_message("terminal", "assistant", full_response)

            except KeyboardInterrupt:
                print(f"\n{ORANGE}[CVAKI]{RESET} Interrupted. Type /exit to quit.")
            except EOFError:
                break
            except Exception as e:
                print(f"\n{ORANGE}[ERR]{RESET} Terminal error: {e}")
