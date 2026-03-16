"""
CVAKI Project Analyst
Reads entire project directories, builds understanding,
validates file connections, and finds errors. Retries until 99.8% confidence.
"""

import os
import json
from pathlib import Path
from typing import TYPE_CHECKING

if TYPE_CHECKING:
    from .ai_engine import CVAKIEngine

from .file_agent import FileAgent, READABLE_EXTENSIONS, SKIP_DIRS


MAX_FILE_CHARS = 3000
MAX_FILES_PER_ANALYSIS = 50


class ProjectAnalyst:
    def __init__(self, engine: "CVAKIEngine"):
        self.engine = engine
        self.file_agent = FileAgent()

    def analyze(self, project_path: str, retry_until_confident: bool = True) -> str:
        """
        Deeply analyze a project:
        1. Map all files and their roles
        2. Build a summary via AI
        3. Re-check connections and potential errors
        4. Retry if confidence < 99.8%
        """
        project_path = os.path.expandvars(os.path.expanduser(project_path))

        if not os.path.isdir(project_path):
            return f"[ERROR] Not a directory: {project_path}"

        print(f"\033[38;2;255;107;0m[CVAKI]\033[0m Scanning project: {project_path}")

        # Step 1: Collect all readable files
        files_content = self._collect_files(project_path)
        print(f"\033[38;2;0;191;165m[CVAKI]\033[0m Found {len(files_content)} readable files")

        if not files_content:
            return f"No readable source files found in {project_path}"

        # Step 2: Generate a project summary via AI
        print("\033[38;2;0;191;165m[CVAKI]\033[0m Generating project understanding...")
        summary = self._generate_summary(project_path, files_content)

        # Step 3: Validate connections
        print("\033[38;2;0;191;165m[CVAKI]\033[0m Checking file connections and issues...")
        validation = self._validate_project(project_path, files_content, summary)

        if retry_until_confident:
            confidence = self._extract_confidence(validation)
            attempt = 1
            while confidence < 99.8 and attempt <= 5:
                print(f"\033[38;2;255;107;0m[CVAKI]\033[0m Confidence {confidence:.1f}% — retrying analysis (attempt {attempt+1}/5)...")
                validation = self._validate_project(project_path, files_content, summary, previous=validation)
                confidence = self._extract_confidence(validation)
                attempt += 1

        result = f"""
╔══════════════════════════════════════════════╗
║  𝗖𝗩♞𝗞𝗜  PROJECT ANALYSIS                    ║
║  Path: {project_path[:38].ljust(38)} ║
╚══════════════════════════════════════════════╝

{summary}

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
VALIDATION & CONNECTIONS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

{validation}
"""
        return result

    def _collect_files(self, root: str) -> dict:
        """Collect all readable files and their contents."""
        files = {}
        count = 0

        for dirpath, dirnames, filenames in os.walk(root):
            dirnames[:] = [d for d in dirnames if d not in SKIP_DIRS and not d.startswith('.')]

            for filename in filenames:
                if count >= MAX_FILES_PER_ANALYSIS:
                    break
                ext = Path(filename).suffix.lower()
                if ext in READABLE_EXTENSIONS:
                    full_path = os.path.join(dirpath, filename)
                    rel_path = os.path.relpath(full_path, root)
                    try:
                        with open(full_path, 'r', encoding='utf-8', errors='replace') as f:
                            content = f.read(MAX_FILE_CHARS)
                        files[rel_path] = content
                        count += 1
                    except:
                        pass

            if count >= MAX_FILES_PER_ANALYSIS:
                break

        return files

    def _generate_summary(self, project_path: str, files: dict) -> str:
        """Use AI to generate a comprehensive project summary."""
        file_listing = ""
        for rel_path, content in list(files.items())[:30]:
            preview = content[:500].replace('\n', '\n    ')
            file_listing += f"\n--- {rel_path} ---\n    {preview}\n"

        prompt = f"""Analyze this project and provide:
1. Project type and purpose (what does it do?)
2. Main technologies and frameworks used
3. Architecture overview  
4. Key entry points / main files
5. Dependencies and external services
6. What each major file/module does

Project directory: {project_path}
Files ({len(files)} total):
{file_listing}

Be specific and technical. Format with clear sections."""

        return self.engine.quick_query(prompt)

    def _validate_project(self, project_path: str, files: dict, summary: str, previous: str = None) -> str:
        """Use AI to validate the project's connections and find issues."""
        imports_info = self._extract_imports(files)

        prev_context = f"\nPrevious validation found issues:\n{previous}\n\nPlease re-examine and fix these.\n" if previous else ""

        prompt = f"""{prev_context}
Project: {project_path}
Summary: {summary[:500]}

File import/dependency map:
{json.dumps(imports_info, indent=2)[:2000]}

All files: {list(files.keys())}

Please check:
1. Are all imports resolvable? List any broken imports.
2. Are there circular dependencies?
3. Do the main files correctly reference each other?
4. Are there missing files that are referenced but don't exist?
5. Config files present? (requirements.txt, package.json, etc.)
6. Any obvious bugs or issues in the code?
7. What is your confidence level (0-100%) that the project is correctly connected?

End your response with exactly:
CONFIDENCE: XX.X%"""

        return self.engine.quick_query(prompt)

    def _extract_imports(self, files: dict) -> dict:
        """Quick static analysis of imports per file."""
        imports = {}
        for path, content in files.items():
            found = []
            for line in content.split('\n')[:50]:
                line = line.strip()
                if (line.startswith('import ') or line.startswith('from ') or
                        line.startswith('require(') or line.startswith('const ') and 'require' in line):
                    found.append(line[:100])
            if found:
                imports[path] = found
        return imports

    def _extract_confidence(self, text: str) -> float:
        """Parse the CONFIDENCE: XX.X% from validation output."""
        import re
        match = re.search(r'CONFIDENCE:\s*(\d+\.?\d*)%', text, re.IGNORECASE)
        if match:
            return float(match.group(1))
        return 50.0  # Default if not found
