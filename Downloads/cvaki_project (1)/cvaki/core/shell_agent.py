"""
CVAKI Shell Agent
Executes shell/PowerShell commands safely with output capture.
"""

import subprocess
import platform
import shlex
import os
from typing import Tuple


# Commands that require explicit confirmation
DANGEROUS_PATTERNS = [
    "rm -rf", "del /f", "format", "mkfs", "dd if=",
    ":(){:|:&};:", "shutdown", "reboot", "halt",
    "DROP TABLE", "DROP DATABASE", "truncate",
]


class ShellAgent:
    def __init__(self):
        self.os = platform.system()  # Windows / Linux / Darwin
        self.shell = "powershell" if self.os == "Windows" else "bash"
        self.history = []

    def is_dangerous(self, command: str) -> bool:
        cmd_lower = command.lower()
        return any(pat.lower() in cmd_lower for pat in DANGEROUS_PATTERNS)

    def execute(self, command: str, timeout: int = 60, confirm: bool = True) -> str:
        """Execute a shell command and return its output."""
        if self.is_dangerous(command):
            return (
                f"[CVAKI SAFETY] Dangerous command detected:\n  {command}\n\n"
                "This command was blocked. Confirm manually or rephrase your request."
            )

        self.history.append(command)

        try:
            if self.os == "Windows":
                result = subprocess.run(
                    ["powershell", "-NoProfile", "-Command", command],
                    capture_output=True,
                    text=True,
                    timeout=timeout,
                    encoding="utf-8",
                    errors="replace"
                )
            else:
                result = subprocess.run(
                    command,
                    shell=True,
                    capture_output=True,
                    text=True,
                    timeout=timeout,
                    encoding="utf-8",
                    errors="replace"
                )

            output = result.stdout.strip()
            error = result.stderr.strip()
            code = result.returncode

            if error and code != 0:
                return f"[return code {code}]\nSTDOUT:\n{output}\n\nSTDERR:\n{error}"
            elif error:
                return f"{output}\n[STDERR]: {error}"
            return output if output else f"[Command completed with exit code {code}]"

        except subprocess.TimeoutExpired:
            return f"[TIMEOUT] Command exceeded {timeout}s timeout."
        except Exception as e:
            return f"[EXEC ERROR] {type(e).__name__}: {e}"

    def install_package(self, package: str, manager: str = "auto") -> str:
        """Install a package using the appropriate package manager."""
        if manager == "auto":
            if self.os == "Windows":
                manager = "pip"
            else:
                manager = "pip"

        if manager == "pip":
            return self.execute(f"pip install {package}")
        elif manager == "npm":
            return self.execute(f"npm install {package}")
        elif manager == "apt":
            return self.execute(f"sudo apt-get install -y {package}")
        elif manager == "winget":
            return self.execute(f"winget install {package}")
        else:
            return f"Unknown package manager: {manager}"

    def get_system_info(self) -> dict:
        """Get basic system information."""
        info = {
            "os": self.os,
            "cwd": os.getcwd(),
            "user": os.getenv("USERNAME") or os.getenv("USER", "unknown"),
            "home": str(os.path.expanduser("~")),
        }

        if self.os == "Windows":
            info["hostname"] = self.execute("hostname")
            info["uptime"] = self.execute("(Get-Date) - (gcim Win32_OperatingSystem).LastBootUpTime")
        else:
            info["hostname"] = self.execute("hostname")
            info["uptime"] = self.execute("uptime -p 2>/dev/null || uptime")

        return info
