"""
CVAKI Background Service
Runs tasks in the background: WhatsApp polling, auto-healing,
scheduled summaries, and message queue processing.
"""

import time
import threading
import queue
import os
from datetime import datetime


class BackgroundService:
    def __init__(self, engine, memory):
        self.engine = engine
        self.memory = memory
        self.task_queue = queue.Queue()
        self.running = True
        self._status = {
            "started_at": datetime.utcnow().isoformat(),
            "tasks_processed": 0,
            "last_heartbeat": None,
        }

    def run(self):
        """Main background loop."""
        print(f"\033[38;2;0;191;165m[CVAKI-BG]\033[0m Background service active")
        heartbeat_interval = 60  # seconds
        last_hb = time.time()

        while self.running:
            # Process queued tasks
            while not self.task_queue.empty():
                try:
                    task = self.task_queue.get_nowait()
                    self._process_task(task)
                    self._status["tasks_processed"] += 1
                except queue.Empty:
                    break
                except Exception as e:
                    print(f"\033[38;2;255;107;0m[CVAKI-BG]\033[0m Task error: {e}")

            # Heartbeat
            now = time.time()
            if now - last_hb >= heartbeat_interval:
                self._status["last_heartbeat"] = datetime.utcnow().isoformat()
                last_hb = now

            time.sleep(1)

    def enqueue(self, task_type: str, payload: dict):
        """Add a task to the background queue."""
        self.task_queue.put({"type": task_type, "payload": payload, "queued_at": time.time()})

    def _process_task(self, task: dict):
        task_type = task.get("type")
        payload = task.get("payload", {})

        if task_type == "whatsapp_reply":
            self._handle_whatsapp_message(payload)
        elif task_type == "file_index":
            self._index_files(payload.get("path", os.path.expanduser("~")))
        elif task_type == "quick_query":
            result = self.engine.quick_query(payload.get("prompt", ""))
            callback = payload.get("callback")
            if callback:
                callback(result)

    def _handle_whatsapp_message(self, payload: dict):
        """Process an incoming WhatsApp message."""
        sender = payload.get("from", "unknown")
        message = payload.get("message", "")
        my_number = self.memory.get("whatsapp_number", "")

        # Only respond if message is from ourselves (or authorized number)
        if not my_number or sender.replace("+", "").replace(" ", "") in my_number.replace("+", "").replace(" ", ""):
            response = self.engine.chat(message, stream=False)
            self.memory.log_message("whatsapp", "user", message)
            self.memory.log_message("whatsapp", "assistant", response)
            # The actual sending is handled by the Node.js bridge
            # We write response to a temp file that the bridge polls
            response_file = os.path.join(os.path.expanduser("~"), ".cvaki", "wa_response.txt")
            with open(response_file, 'w', encoding='utf-8') as f:
                f.write(response)

    def _index_files(self, path: str):
        """Background file indexing for faster search."""
        count = 0
        for root, dirs, files in os.walk(path):
            dirs[:] = [d for d in dirs if not d.startswith('.') and d not in
                       {'node_modules', '__pycache__', 'venv', 'Windows', 'Program Files'}]
            for f in files:
                fp = os.path.join(root, f)
                try:
                    self.memory.conn.execute(
                        "INSERT OR REPLACE INTO file_index (path, name, size, ext) VALUES (?,?,?,?)",
                        (fp, f, os.path.getsize(fp), os.path.splitext(f)[1].lower())
                    )
                    count += 1
                    if count % 500 == 0:
                        self.memory.conn.commit()
                except:
                    pass
        self.memory.conn.commit()

    def stop(self):
        self.running = False

    @property
    def status(self):
        return self._status
