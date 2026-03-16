"""
CVAKI File Agent
Intelligent file system search, read, write, and indexing.
"""

import os
import fnmatch
from pathlib import Path
from typing import List, Optional


# File types CVAKI can read and understand
READABLE_EXTENSIONS = {
    '.py', '.js', '.ts', '.jsx', '.tsx', '.html', '.css', '.scss',
    '.json', '.yaml', '.yml', '.toml', '.ini', '.cfg', '.env',
    '.md', '.txt', '.rst', '.csv', '.xml',
    '.c', '.cpp', '.h', '.java', '.go', '.rs', '.rb', '.php',
    '.sh', '.bat', '.ps1', '.cmd',
    '.sql', '.graphql',
    '.pdf',  # will need PyMuPDF
    '.docx',  # will need python-docx
}

# Directories to skip when searching
SKIP_DIRS = {
    'node_modules', '__pycache__', '.git', '.svn', '.hg',
    'venv', '.venv', 'env', '.env', 'dist', 'build',
    '.idea', '.vscode', 'vendor', 'bower_components',
    'System Volume Information', '$Recycle.Bin',
    'Windows', 'Program Files', 'Program Files (x86)',
}


class FileAgent:
    def __init__(self):
        self.index = {}  # path -> metadata cache

    def search(self, query: str, root: str = None, max_results: int = 30) -> str:
        """Search for files matching a query (name, extension, or content keyword)."""
        if root is None:
            root = os.path.expanduser("~")

        root = os.path.expandvars(root)
        query_lower = query.lower()
        matches = []

        try:
            for dirpath, dirnames, filenames in os.walk(root):
                # Skip system/junk dirs
                dirnames[:] = [d for d in dirnames if d not in SKIP_DIRS and not d.startswith('.')]

                for filename in filenames:
                    full_path = os.path.join(dirpath, filename)
                    name_lower = filename.lower()

                    # Match by filename
                    if query_lower in name_lower:
                        matches.append((full_path, "name match"))
                        if len(matches) >= max_results:
                            break

                if len(matches) >= max_results:
                    break

        except PermissionError:
            pass
        except Exception as e:
            return f"[File search error: {e}]"

        if not matches:
            return f"No files found matching '{query}' under {root}"

        result = f"Found {len(matches)} file(s) matching '{query}':\n\n"
        for path, reason in matches:
            size = self._get_size(path)
            result += f"  📄 {path}  ({size})\n"

        return result

    def read(self, path: str, max_chars: int = 8000) -> str:
        """Read a file and return its contents."""
        path = os.path.expandvars(os.path.expanduser(path))

        if not os.path.exists(path):
            return f"[FILE NOT FOUND] {path}"

        if not os.path.isfile(path):
            return self.list_dir(path)

        ext = Path(path).suffix.lower()
        size = os.path.getsize(path)

        if ext not in READABLE_EXTENSIONS:
            return f"[UNSUPPORTED] Cannot read binary file: {path} ({self._fmt_size(size)})"

        try:
            with open(path, 'r', encoding='utf-8', errors='replace') as f:
                content = f.read(max_chars)

            if len(content) == max_chars:
                content += f"\n\n[... truncated — file is {self._fmt_size(size)} total]"

            return f"📄 {path} ({self._fmt_size(size)}):\n\n{content}"

        except Exception as e:
            return f"[READ ERROR] {e}"

    def write(self, path: str, content: str) -> str:
        """Write content to a file, creating parent directories as needed."""
        path = os.path.expandvars(os.path.expanduser(path))

        try:
            parent = os.path.dirname(path)
            if parent:
                os.makedirs(parent, exist_ok=True)

            with open(path, 'w', encoding='utf-8') as f:
                f.write(content)

            size = os.path.getsize(path)
            return f"[OK] Written {self._fmt_size(size)} to {path}"

        except Exception as e:
            return f"[WRITE ERROR] {e}"

    def list_dir(self, path: str = ".") -> str:
        """List directory contents."""
        path = os.path.expandvars(os.path.expanduser(path))

        if not os.path.exists(path):
            return f"[NOT FOUND] {path}"

        try:
            entries = os.listdir(path)
            entries.sort()
            result = f"📁 {path}  ({len(entries)} entries)\n\n"

            dirs = [e for e in entries if os.path.isdir(os.path.join(path, e))]
            files = [e for e in entries if os.path.isfile(os.path.join(path, e))]

            for d in dirs[:50]:
                result += f"  📂 {d}/\n"
            for f in files[:100]:
                fp = os.path.join(path, f)
                result += f"  📄 {f}  ({self._get_size(fp)})\n"

            if len(entries) > 150:
                result += f"\n  ... and {len(entries) - 150} more items"

            return result

        except PermissionError:
            return f"[PERMISSION DENIED] Cannot read {path}"
        except Exception as e:
            return f"[LIST ERROR] {e}"

    def get_tree(self, root: str, max_depth: int = 3) -> str:
        """Get a tree view of a directory."""
        root = os.path.expandvars(os.path.expanduser(root))
        lines = [f"📁 {root}"]
        self._tree_recurse(root, lines, "", 0, max_depth)
        return "\n".join(lines)

    def _tree_recurse(self, path: str, lines: list, prefix: str, depth: int, max_depth: int):
        if depth >= max_depth:
            return
        try:
            entries = sorted(os.listdir(path))
            entries = [e for e in entries if e not in SKIP_DIRS and not e.startswith('.')]
            for i, entry in enumerate(entries):
                full = os.path.join(path, entry)
                connector = "└── " if i == len(entries) - 1 else "├── "
                if os.path.isdir(full):
                    lines.append(f"{prefix}{connector}📂 {entry}/")
                    ext_prefix = prefix + ("    " if i == len(entries) - 1 else "│   ")
                    self._tree_recurse(full, lines, ext_prefix, depth + 1, max_depth)
                else:
                    size = self._get_size(full)
                    lines.append(f"{prefix}{connector}📄 {entry} ({size})")
        except PermissionError:
            lines.append(f"{prefix}  [permission denied]")

    def _get_size(self, path: str) -> str:
        try:
            return self._fmt_size(os.path.getsize(path))
        except:
            return "?"

    def _fmt_size(self, size: int) -> str:
        for unit in ['B', 'KB', 'MB', 'GB']:
            if size < 1024:
                return f"{size:.0f}{unit}"
            size /= 1024
        return f"{size:.1f}TB"
