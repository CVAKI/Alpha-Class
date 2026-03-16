"""
CVAKI Memory Store
SQLite-backed persistence for API keys, settings, and conversation context.
"""

import sqlite3
import json
import os
from pathlib import Path
from datetime import datetime


CVAKI_DIR = Path.home() / ".cvaki"
DB_PATH = CVAKI_DIR / "cvaki.db"


class MemoryStore:
    def __init__(self):
        CVAKI_DIR.mkdir(exist_ok=True)
        self.conn = sqlite3.connect(str(DB_PATH), check_same_thread=False)
        self._init_db()

    def _init_db(self):
        c = self.conn.cursor()
        c.executescript("""
            CREATE TABLE IF NOT EXISTS config (
                key TEXT PRIMARY KEY,
                value TEXT NOT NULL,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS chat_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                channel TEXT NOT NULL,
                role TEXT NOT NULL,
                content TEXT NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS file_index (
                path TEXT PRIMARY KEY,
                name TEXT,
                size INTEGER,
                ext TEXT,
                indexed_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
        """)
        self.conn.commit()

    # --- Config / Settings ---

    def set(self, key: str, value: str):
        c = self.conn.cursor()
        c.execute(
            "INSERT OR REPLACE INTO config (key, value, updated_at) VALUES (?, ?, ?)",
            (key, str(value), datetime.utcnow().isoformat())
        )
        self.conn.commit()

    def get(self, key: str, default: str = None) -> str:
        c = self.conn.cursor()
        c.execute("SELECT value FROM config WHERE key = ?", (key,))
        row = c.fetchone()
        return row[0] if row else default

    def delete(self, key: str):
        self.conn.execute("DELETE FROM config WHERE key = ?", (key,))
        self.conn.commit()

    def all_config(self) -> dict:
        c = self.conn.cursor()
        c.execute("SELECT key, value FROM config")
        return {row[0]: row[1] for row in c.fetchall()}

    # --- API Key (stored encrypted in future, plain for now) ---

    def set_api_key(self, api_key: str):
        self.set("groq_api_key", api_key)

    def get_api_key(self) -> str:
        return self.get("groq_api_key", os.getenv("GROQ_API_KEY", ""))

    def has_api_key(self) -> bool:
        return bool(self.get_api_key())

    # --- Chat Log ---

    def log_message(self, channel: str, role: str, content: str):
        self.conn.execute(
            "INSERT INTO chat_log (channel, role, content) VALUES (?, ?, ?)",
            (channel, role, content)
        )
        self.conn.commit()

    def get_history(self, channel: str = "terminal", limit: int = 50) -> list:
        c = self.conn.cursor()
        c.execute(
            "SELECT role, content, created_at FROM chat_log WHERE channel = ? ORDER BY id DESC LIMIT ?",
            (channel, limit)
        )
        rows = c.fetchall()
        return [{"role": r[0], "content": r[1], "time": r[2]} for r in reversed(rows)]

    def clear_history(self, channel: str = None):
        if channel:
            self.conn.execute("DELETE FROM chat_log WHERE channel = ?", (channel,))
        else:
            self.conn.execute("DELETE FROM chat_log")
        self.conn.commit()

    def close(self):
        self.conn.close()
