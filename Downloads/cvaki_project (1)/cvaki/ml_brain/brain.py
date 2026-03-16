"""
CVAKI ML Brain
PyTorch-powered neural controller for keyboard/mouse automation.
Learns from task descriptions to execute UI actions.
"""

import os
import time
import json
from typing import List, Tuple, Optional

try:
    import torch
    import torch.nn as nn
    TORCH_AVAILABLE = True
except ImportError:
    TORCH_AVAILABLE = False

try:
    import pyautogui
    import pygetwindow as gw
    PYAUTOGUI_AVAILABLE = True
    pyautogui.FAILSAFE = True  # Move mouse to corner to abort
    pyautogui.PAUSE = 0.1
except ImportError:
    PYAUTOGUI_AVAILABLE = False


# Action types the brain can execute
ACTION_TYPES = [
    "click", "double_click", "right_click", "type_text",
    "key_press", "move_mouse", "scroll", "screenshot",
    "focus_window", "wait"
]


class ActionNet(nn.Module):
    """
    Simple feed-forward network that maps task embeddings to action sequences.
    Input: task description embedding (512d)
    Output: action type probabilities + coordinates
    """
    def __init__(self, input_dim=512, hidden_dim=256, num_actions=len(ACTION_TYPES)):
        super().__init__()
        self.encoder = nn.Sequential(
            nn.Linear(input_dim, hidden_dim),
            nn.ReLU(),
            nn.Dropout(0.1),
            nn.Linear(hidden_dim, hidden_dim),
            nn.ReLU(),
        )
        self.action_head = nn.Linear(hidden_dim, num_actions)   # Action type
        self.coord_head = nn.Linear(hidden_dim, 2)              # x, y normalized [0,1]
        self.softmax = nn.Softmax(dim=-1)

    def forward(self, x):
        h = self.encoder(x)
        action_probs = self.softmax(self.action_head(h))
        coords = torch.sigmoid(self.coord_head(h))
        return action_probs, coords


class CVAKIBrain:
    """
    The ML hardware control brain.
    Uses AI to parse task descriptions into action sequences,
    then executes them via pyautogui.
    """

    def __init__(self, engine=None):
        self.engine = engine
        self.model = None
        self.screen_w = 1920
        self.screen_h = 1080
        self.action_log = []

        if PYAUTOGUI_AVAILABLE:
            self.screen_w, self.screen_h = pyautogui.size()

        self._load_or_init_model()

    def _load_or_init_model(self):
        if not TORCH_AVAILABLE:
            return

        model_path = os.path.join(os.path.expanduser("~"), ".cvaki", "brain.pt")
        self.model = ActionNet()

        if os.path.exists(model_path):
            try:
                self.model.load_state_dict(torch.load(model_path, map_location='cpu'))
                print("\033[38;2;0;191;165m[CVAKI-BRAIN]\033[0m Loaded trained model")
            except:
                print("\033[38;2;255;107;0m[CVAKI-BRAIN]\033[0m Starting with fresh model")
        else:
            print("\033[38;2;0;191;165m[CVAKI-BRAIN]\033[0m ML brain initialized (untrained)")

        self.model.eval()

    def execute_task(self, task_description: str) -> str:
        """
        Parse a natural language task and execute it via keyboard/mouse.
        E.g. "Click the Start button", "Type 'Hello World' in Notepad"
        """
        if not PYAUTOGUI_AVAILABLE:
            return "[ML BRAIN] pyautogui not available. Install: pip install pyautogui pygetwindow"

        # Use AI to convert task to action steps
        if self.engine:
            action_plan = self._plan_actions_via_ai(task_description)
        else:
            action_plan = self._simple_action_parse(task_description)

        if not action_plan:
            return f"[ML BRAIN] Could not parse task: {task_description}"

        results = []
        for action in action_plan:
            result = self._execute_action(action)
            results.append(result)
            self.action_log.append({
                "task": task_description,
                "action": action,
                "result": result,
                "time": time.time()
            })
            time.sleep(0.2)  # Small pause between actions

        return "\n".join(results)

    def _plan_actions_via_ai(self, task: str) -> List[dict]:
        """Use AI to convert a task description into structured action steps."""
        prompt = f"""Convert this UI automation task into a JSON list of actions.
Task: {task}

Available actions: click, double_click, right_click, type_text, key_press, move_mouse, scroll, screenshot, wait

Return ONLY a JSON array like:
[
  {{"action": "click", "x": 100, "y": 200, "description": "click Start button"}},
  {{"action": "type_text", "text": "hello", "description": "type hello"}},
  {{"action": "key_press", "key": "enter", "description": "press enter"}}
]

If you need a screenshot first to determine coordinates, start with:
{{"action": "screenshot", "description": "take screenshot to see screen"}}

Return ONLY the JSON array, nothing else."""

        try:
            response = self.engine.quick_query(prompt)
            import re
            json_match = re.search(r'\[.*\]', response, re.DOTALL)
            if json_match:
                return json.loads(json_match.group())
        except:
            pass
        return []

    def _simple_action_parse(self, task: str) -> List[dict]:
        """Simple rule-based action parser for when AI is unavailable."""
        task_lower = task.lower()
        actions = []

        if "screenshot" in task_lower or "screen" in task_lower:
            actions.append({"action": "screenshot"})
        elif "type" in task_lower:
            import re
            text_match = re.search(r"type ['\"](.+?)['\"]", task, re.IGNORECASE)
            if text_match:
                actions.append({"action": "type_text", "text": text_match.group(1)})
        elif "press" in task_lower:
            import re
            key_match = re.search(r"press (\w+)", task, re.IGNORECASE)
            if key_match:
                actions.append({"action": "key_press", "key": key_match.group(1)})

        return actions

    def _execute_action(self, action: dict) -> str:
        """Execute a single action dict."""
        act = action.get("action", "")
        desc = action.get("description", act)

        try:
            if act == "screenshot":
                path = os.path.join(os.path.expanduser("~"), ".cvaki", "screenshot.png")
                screenshot = pyautogui.screenshot()
                screenshot.save(path)
                return f"[OK] Screenshot saved: {path}"

            elif act == "click":
                x, y = action.get("x", self.screen_w//2), action.get("y", self.screen_h//2)
                pyautogui.click(x, y)
                return f"[OK] Clicked ({x}, {y}) — {desc}"

            elif act == "double_click":
                x, y = action.get("x", self.screen_w//2), action.get("y", self.screen_h//2)
                pyautogui.doubleClick(x, y)
                return f"[OK] Double-clicked ({x}, {y})"

            elif act == "right_click":
                x, y = action.get("x", self.screen_w//2), action.get("y", self.screen_h//2)
                pyautogui.rightClick(x, y)
                return f"[OK] Right-clicked ({x}, {y})"

            elif act == "type_text":
                text = action.get("text", "")
                pyautogui.typewrite(text, interval=0.05)
                return f"[OK] Typed: {text[:40]}"

            elif act == "key_press":
                key = action.get("key", "")
                pyautogui.press(key)
                return f"[OK] Pressed: {key}"

            elif act == "key_hotkey":
                keys = action.get("keys", [])
                pyautogui.hotkey(*keys)
                return f"[OK] Hotkey: {'+'.join(keys)}"

            elif act == "move_mouse":
                x, y = action.get("x", self.screen_w//2), action.get("y", self.screen_h//2)
                pyautogui.moveTo(x, y, duration=0.3)
                return f"[OK] Moved mouse to ({x}, {y})"

            elif act == "scroll":
                amount = action.get("amount", 3)
                pyautogui.scroll(amount)
                return f"[OK] Scrolled {amount}"

            elif act == "wait":
                seconds = action.get("seconds", 1)
                time.sleep(seconds)
                return f"[OK] Waited {seconds}s"

            else:
                return f"[WARN] Unknown action: {act}"

        except Exception as e:
            return f"[ERR] Action '{act}' failed: {e}"

    def save_model(self):
        """Save the current model state."""
        if not TORCH_AVAILABLE or not self.model:
            return
        model_path = os.path.join(os.path.expanduser("~"), ".cvaki", "brain.pt")
        torch.save(self.model.state_dict(), model_path)
        print("[CVAKI-BRAIN] Model saved")

    def get_screen_info(self) -> dict:
        """Get current screen information."""
        if not PYAUTOGUI_AVAILABLE:
            return {"available": False}
        x, y = pyautogui.position()
        return {
            "width": self.screen_w,
            "height": self.screen_h,
            "mouse_x": x,
            "mouse_y": y,
            "available": True
        }
