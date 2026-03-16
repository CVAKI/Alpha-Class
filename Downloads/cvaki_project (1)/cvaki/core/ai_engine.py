"""
CVAKI Core AI Engine
Groq-powered reasoning, tool dispatch, and task routing.
"""

import json
import re
from groq import Groq
from typing import Optional, Generator
from .shell_agent import ShellAgent
from .file_agent import FileAgent
from .project_analyst import ProjectAnalyst


SYSTEM_PROMPT = """You are 𝗖𝗩♞𝗞𝗜 (pronounced "CVAKI"), an advanced AI assistant and system controller. 
You are the "Brain Gods AI" — a highly capable, direct, and efficient assistant.

Your personality:
- Sharp, intelligent, confident — like a fox
- Concise but thorough when needed
- You can execute shell commands, read/write files, analyze projects, control hardware
- You always think step-by-step before acting
- You ALWAYS confirm before destructive operations

You have access to these tools — respond with a JSON tool call when needed:

TOOL: shell_exec
  Run a shell/PowerShell command
  {"tool": "shell_exec", "command": "...", "reason": "why"}

TOOL: file_search  
  Search for files across the system
  {"tool": "file_search", "query": "...", "path": "optional_root"}

TOOL: file_read
  Read a file's content
  {"tool": "file_read", "path": "..."}

TOOL: file_write
  Write/create a file
  {"tool": "file_write", "path": "...", "content": "..."}

TOOL: project_analyze
  Deep-analyze a project directory
  {"tool": "project_analyze", "path": "..."}

TOOL: web_search (future)
  Search the web
  {"tool": "web_search", "query": "..."}

When you need to use a tool, output ONLY the JSON. Otherwise respond normally.
Always be aware: you are running on the user's actual system. Think before you act.
Never run rm -rf or format commands without triple confirmation.

Color coding your responses for the terminal:
- Use [OK] for success
- Use [!] for warnings  
- Use [ERR] for errors
- Use [?] when asking user
"""


class CVAKIEngine:
    def __init__(self, api_key: str):
        self.client = Groq(api_key=api_key)
        self.model = "llama-3.3-70b-versatile"
        self.fast_model = "llama-3.1-8b-instant"
        self.shell = ShellAgent()
        self.file_agent = FileAgent()
        self.analyst = ProjectAnalyst(self)
        self.conversation_history = []
        self.max_history = 20

    def chat(self, user_message: str, stream: bool = True) -> str:
        """Send a message and get a response, handling tool calls automatically."""
        self.conversation_history.append({
            "role": "user",
            "content": user_message
        })

        # Keep history bounded
        if len(self.conversation_history) > self.max_history * 2:
            self.conversation_history = self.conversation_history[-self.max_history * 2:]

        response_text = self._call_llm(stream=stream)

        # Check if response is a tool call
        tool_result = self._try_execute_tool(response_text)
        if tool_result is not None:
            # Feed tool result back to LLM
            self.conversation_history.append({
                "role": "assistant",
                "content": response_text
            })
            self.conversation_history.append({
                "role": "user",
                "content": f"[TOOL RESULT]\n{tool_result}\n\nNow please respond to the user based on this result."
            })
            response_text = self._call_llm(stream=False)

        self.conversation_history.append({
            "role": "assistant",
            "content": response_text
        })

        return response_text

    def chat_stream(self, user_message: str) -> Generator[str, None, None]:
        """Streaming version of chat."""
        self.conversation_history.append({
            "role": "user",
            "content": user_message
        })

        if len(self.conversation_history) > self.max_history * 2:
            self.conversation_history = self.conversation_history[-self.max_history * 2:]

        full_response = ""
        stream = self.client.chat.completions.create(
            model=self.model,
            messages=[{"role": "system", "content": SYSTEM_PROMPT}] + self.conversation_history,
            stream=True,
            max_tokens=2048,
            temperature=0.7,
        )

        for chunk in stream:
            delta = chunk.choices[0].delta.content or ""
            full_response += delta
            yield delta

        # After streaming, check for tool calls
        tool_result = self._try_execute_tool(full_response)
        if tool_result is not None:
            self.conversation_history.append({"role": "assistant", "content": full_response})
            self.conversation_history.append({
                "role": "user",
                "content": f"[TOOL RESULT]\n{tool_result}\n\nNow please respond to the user based on this result."
            })
            yield "\n\n---\n"
            followup = self._call_llm(stream=False)
            yield followup
            full_response = followup

        self.conversation_history.append({
            "role": "assistant",
            "content": full_response
        })

    def quick_query(self, prompt: str) -> str:
        """Fast single-turn query using the smaller model (for background tasks)."""
        response = self.client.chat.completions.create(
            model=self.fast_model,
            messages=[
                {"role": "system", "content": "You are CVAKI, a helpful AI. Be concise."},
                {"role": "user", "content": prompt}
            ],
            max_tokens=512,
            temperature=0.5,
        )
        return response.choices[0].message.content

    def _call_llm(self, stream: bool = False) -> str:
        messages = [{"role": "system", "content": SYSTEM_PROMPT}] + self.conversation_history
        
        if stream:
            full = ""
            response = self.client.chat.completions.create(
                model=self.model,
                messages=messages,
                stream=True,
                max_tokens=2048,
                temperature=0.7,
            )
            for chunk in response:
                full += chunk.choices[0].delta.content or ""
            return full
        else:
            response = self.client.chat.completions.create(
                model=self.model,
                messages=messages,
                max_tokens=2048,
                temperature=0.7,
            )
            return response.choices[0].message.content

    def _try_execute_tool(self, response: str) -> Optional[str]:
        """Try to parse and execute a tool call from the LLM response."""
        # Look for JSON tool call
        json_match = re.search(r'\{[^{}]*"tool"\s*:\s*"[^"]+".+?\}', response, re.DOTALL)
        if not json_match:
            return None

        try:
            call = json.loads(json_match.group())
            tool = call.get("tool")

            if tool == "shell_exec":
                cmd = call.get("command", "")
                return self.shell.execute(cmd)

            elif tool == "file_search":
                query = call.get("query", "")
                root = call.get("path", "/")
                return self.file_agent.search(query, root)

            elif tool == "file_read":
                path = call.get("path", "")
                return self.file_agent.read(path)

            elif tool == "file_write":
                path = call.get("path", "")
                content = call.get("content", "")
                return self.file_agent.write(path, content)

            elif tool == "project_analyze":
                path = call.get("path", ".")
                return self.analyst.analyze(path)

        except (json.JSONDecodeError, Exception) as e:
            return f"[Tool execution error: {e}]"

        return None

    def reset_conversation(self):
        self.conversation_history = []
